<?php
/**
 * Imports games from a remote JSON URL into the `game` post type.
 *
 * Idempotent: games are matched (in order) by feed `game_id`, then iframe URL,
 * then slug, so repeated syncs update rather than duplicate. Stats live in
 * separate tables keyed by post ID, so re-imports never disturb counters.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Importer {

	const CRON_HOOK = 'ghub_sync_games';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_from_settings' ) );
	}

	/* -------------------------------------------------------------------- */
	/* Cron scheduling                                                       */
	/* -------------------------------------------------------------------- */

	public static function schedule_default() {
		$interval = GameHub_Settings::get()['sync_interval'] ?? 'disabled';
		self::reschedule( $interval );
	}

	public static function reschedule( $interval ) {
		self::unschedule();
		if ( in_array( $interval, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			wp_schedule_event( time() + 60, $interval, self::CRON_HOOK );
		}
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
			$ts = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/* -------------------------------------------------------------------- */
	/* Entry points                                                          */
	/* -------------------------------------------------------------------- */

	/**
	 * Fetch the configured JSON URL and import it.
	 *
	 * @return array|WP_Error Import stats or error.
	 */
	public function run_from_settings() {
		$s = GameHub_Settings::get();
		return $this->import_from_url(
			trim( (string) $s['json_url'] ),
			array(
				'update_existing'    => ! empty( $s['update_existing'] ),
				'deactivate_missing' => ! empty( $s['deactivate_missing'] ),
			)
		);
	}

	/**
	 * Fetch a JSON URL and import it.
	 *
	 * @param string $url     Feed URL.
	 * @param array  $options update_existing, deactivate_missing.
	 * @return array|WP_Error
	 */
	public function import_from_url( $url, $options = array() ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new WP_Error( 'no_url', __( 'No JSON URL was provided.', 'gamehub-engine' ) );
		}

		$body = $this->fetch_url( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$stats = $this->import_json( $body, $options );
		if ( ! is_wp_error( $stats ) ) {
			update_option( 'ghub_last_sync', time() );
		}
		return $stats;
	}

	/**
	 * GET a URL and return its body, or a WP_Error.
	 *
	 * @param string $url URL.
	 * @return string|WP_Error
	 */
	private function fetch_url( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'user-agent' => 'GameHub-Engine/' . GHUB_ENGINE_VERSION . '; ' . home_url( '/' ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_error', sprintf( /* translators: HTTP status */ __( 'Feed returned HTTP %d.', 'gamehub-engine' ), $code ) );
		}
		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Import a raw JSON string.
	 *
	 * @param string $json    Raw JSON text.
	 * @param array  $options update_existing, deactivate_missing.
	 * @return array|WP_Error
	 */
	public function import_json( $json, $options = array() ) {
		$stats = array(
			'inserted'    => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'deactivated' => 0,
			'errors'      => 0,
			'error_lines' => array(),
		);

		$json = trim( (string) $json );
		if ( '' === $json ) {
			return new WP_Error( 'empty', __( 'Empty feed body.', 'gamehub-engine' ) );
		}

		$payload = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json', __( 'JSON parse error: ', 'gamehub-engine' ) . json_last_error_msg() );
		}

		$items = $this->extract_items( $payload );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$seen_ids = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$stats['errors']++;
				$stats['error_lines'][] = 'Item ' . ( $index + 1 ) . ': not an object.';
				continue;
			}
			$this->import_one( $this->map_item( $item ), 'Item ' . ( $index + 1 ), $options, $stats, $seen_ids );
		}

		$this->finalize( $options, $stats, $seen_ids );

		// Reflect new/updated games immediately by clearing page + object caches.
		if ( $stats['inserted'] || $stats['updated'] || $stats['deactivated'] ) {
			$this->purge_caches();
		}
		return $stats;
	}

	/**
	 * Flush object cache and trigger a full page-cache purge (nginx-helper /
	 * Webinoly FastCGI cache), so imported games appear without waiting for TTL.
	 */
	private function purge_caches() {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		// nginx-helper listens for this and purges the FastCGI/Redis page cache.
		do_action( 'rt_nginx_helper_purge_all' );
	}

	/* -------------------------------------------------------------------- */
	/* Parsing helpers                                                       */
	/* -------------------------------------------------------------------- */

	private function extract_items( $payload ) {
		if ( is_array( $payload ) && ghub_is_list( $payload ) ) {
			return $payload;
		}
		if ( is_array( $payload ) && isset( $payload['games'] ) && is_array( $payload['games'] ) ) {
			return $payload['games'];
		}
		if ( is_array( $payload ) && ( isset( $payload['name'] ) || isset( $payload['gamename'] ) || isset( $payload['title'] ) ) ) {
			return array( $payload );
		}
		return new WP_Error( 'shape', __( 'JSON must be an array of games or an object with a "games" array.', 'gamehub-engine' ) );
	}

	/**
	 * Map a loose feed item to a normalised game record.
	 */
	private function map_item( $item ) {
		$name   = trim( (string) ghub_pick( $item, array( 'gamename', 'name', 'title' ) ) );
		$iframe = trim( (string) ghub_pick( $item, array( 'iframeurl', 'gameurl', 'game_url', 'url', 'embed' ) ) );
		$icon   = trim( (string) ghub_pick( $item, array( 'gameicon', 'game_icon', 'icon', 'iconurl', 'icon_url', 'image', 'thumbnail', 'thumb' ) ) );

		// "1 + 2 = 3 – 155 thousand plays – play Playable" style titles: keep only the game name.
		if ( false !== strpos( $name, ' – ' ) ) {
			$name = trim( explode( ' – ', $name )[0] );
		}

		$categories = ghub_pick( $item, array( 'categories', 'category', 'genre', 'genres', 'type' ), '' );
		if ( is_string( $categories ) ) {
			$categories = array_filter( array_map( 'trim', preg_split( '/[|,]/', $categories ) ) );
		} elseif ( ! is_array( $categories ) ) {
			$categories = array();
		}

		return array(
			'source_id'   => trim( (string) ghub_pick( $item, array( 'game_id', 'gameid', 'id' ) ) ),
			'name'        => $name,
			'iframe_url'  => $iframe,
			'icon'        => $icon,
			'categories'  => array_values( array_unique( $categories ) ),
			'slug'        => sanitize_title( (string) ghub_pick( $item, array( 'slug' ) ) ),
			'description' => trim( (string) ghub_pick( $item, array( 'description', 'content', 'about', 'desc', 'summary', 'text' ) ) ),
		);
	}

	/* -------------------------------------------------------------------- */
	/* Upsert                                                                */
	/* -------------------------------------------------------------------- */

	/**
	 * Find an existing game post for a feed record.
	 *
	 * @return int Post ID or 0.
	 */
	private function find_existing( $data ) {
		// 1) By feed source id.
		if ( '' !== $data['source_id'] ) {
			$found = get_posts(
				array(
					'post_type'      => 'game',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => GHUB_META_SOURCE_ID,
					'meta_value'     => $data['source_id'],
					'no_found_rows'  => true,
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		// 2) By iframe URL.
		if ( '' !== $data['iframe_url'] ) {
			$found = get_posts(
				array(
					'post_type'      => 'game',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => GHUB_META_IFRAME,
					'meta_value'     => $data['iframe_url'],
					'no_found_rows'  => true,
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		// 3) By slug.
		$slug = $data['slug'] ?: sanitize_title( $data['name'] );
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'game' );
			if ( $post ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}

	private function import_one( $data, $label, $options, &$stats, &$seen_ids ) {
		if ( '' === $data['name'] || '' === $data['iframe_url'] ) {
			$stats['skipped']++;
			return;
		}

		$existing = $this->find_existing( $data );

		if ( $existing && empty( $options['update_existing'] ) ) {
			$stats['skipped']++;
			$seen_ids[] = $existing;
			// Re-publish if it was auto-drafted by a previous sync.
			if ( 'draft' === get_post_status( $existing ) ) {
				wp_update_post( array( 'ID' => $existing, 'post_status' => 'publish' ) );
			}
			return;
		}

		$postarr = array(
			'post_type'   => 'game',
			'post_status' => 'publish',
			'post_title'  => $data['name'],
		);
		if ( $data['slug'] ) {
			$postarr['post_name'] = $data['slug'];
		}
		if ( ! empty( $data['description'] ) ) {
			$postarr['post_content'] = wp_kses_post( $data['description'] );
		}
		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$stats['errors']++;
			$stats['error_lines'][] = $label . ': ' . $post_id->get_error_message();
			return;
		}

		// Meta.
		update_post_meta( $post_id, GHUB_META_IFRAME, esc_url_raw( $data['iframe_url'] ) );
		if ( $data['icon'] ) {
			update_post_meta( $post_id, GHUB_META_ICON, esc_url_raw( $data['icon'] ) );
		}
		if ( $data['source_id'] ) {
			update_post_meta( $post_id, GHUB_META_SOURCE_ID, sanitize_text_field( $data['source_id'] ) );
		}

		// Categories → terms.
		if ( ! empty( $data['categories'] ) ) {
			$term_ids = array();
			foreach ( $data['categories'] as $cat_name ) {
				$cat_name = sanitize_text_field( $cat_name );
				if ( '' === $cat_name ) {
					continue;
				}
				$term = term_exists( $cat_name, 'game_category' );
				if ( ! $term ) {
					$term = wp_insert_term( $cat_name, 'game_category' );
				}
				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term['term_id'];
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( $post_id, $term_ids, 'game_category', false );
				// The feed's first category is the primary one (unless already set).
				if ( ! get_post_meta( $post_id, GHUB_META_PRIMARY_CAT, true ) ) {
					update_post_meta( $post_id, GHUB_META_PRIMARY_CAT, (int) $term_ids[0] );
				}
			}
		}

		GameHub_Stats::ensure_row( $post_id );
		$seen_ids[] = (int) $post_id;
		if ( $existing ) {
			$stats['updated']++;
		} else {
			$stats['inserted']++;
		}
	}

	/**
	 * Optionally draft games absent from the current feed.
	 */
	private function finalize( $options, &$stats, $seen_ids ) {
		if ( empty( $options['deactivate_missing'] ) || ! empty( $stats['errors'] ) ) {
			return;
		}
		$all = get_posts(
			array(
				'post_type'      => 'game',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$seen = array_map( 'intval', $seen_ids );
		foreach ( $all as $pid ) {
			if ( ! in_array( (int) $pid, $seen, true ) ) {
				wp_update_post( array( 'ID' => (int) $pid, 'post_status' => 'draft' ) );
				$stats['deactivated']++;
			}
		}
	}
}
