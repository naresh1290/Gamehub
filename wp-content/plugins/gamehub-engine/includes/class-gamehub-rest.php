<?php
/**
 * Public REST API for front-end metrics.
 *
 * Namespace: gamehub/v1
 *   GET  /games                      List published games.
 *   GET  /games/(id)                 Single game payload.
 *   POST /games/(id)/play            Count a play (IP-throttled).
 *   POST /games/(id)/visit           Count a page visit (IP-throttled).
 *   POST /games/(id)/like            Toggle like (per-voter dedupe).
 *   POST /games/(id)/dislike         Toggle dislike (per-voter dedupe).
 *   POST /games/(id)/rate  {rating}  Set a 1-5 star rating (per-voter).
 *   POST /games/(id)/session {seconds} Add a play-session duration.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_REST {

	const NS = 'gamehub/v1';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			self::NS,
			'/games',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'list_games' ),
			)
		);

		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array( 'type' => 'string', 'required' => true ),
				),
				'callback'            => array( $this, 'search' ),
			)
		);

		$id_arg = array(
			'id' => array(
				'required'          => true,
				'validate_callback' => static function ( $v ) {
					return is_numeric( $v ) && (int) $v > 0;
				},
			),
		);

		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $id_arg,
				'callback'            => array( $this, 'get_game' ),
			)
		);

		foreach ( array( 'play', 'visit' ) as $event ) {
			register_rest_route(
				self::NS,
				'/games/(?P<id>\d+)/' . $event,
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'args'                => $id_arg,
					'callback'            => function ( $req ) use ( $event ) {
						return $this->count_event( $req, $event );
					},
				)
			);
		}

		foreach ( array( 'like', 'dislike' ) as $vote ) {
			register_rest_route(
				self::NS,
				'/games/(?P<id>\d+)/' . $vote,
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'args'                => $id_arg,
					'callback'            => function ( $req ) use ( $vote ) {
						return $this->vote( $req, $vote );
					},
				)
			);
		}

		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/session',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => $id_arg + array(
					'seconds' => array(
						'required'          => true,
						'validate_callback' => static function ( $v ) {
							return is_numeric( $v ) && (int) $v > 0;
						},
					),
				),
				'callback'            => array( $this, 'session' ),
			)
		);
	}

	/* -------------------------------------------------------------------- */

	private function resolve_game( $req ) {
		$id   = (int) $req->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'game' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		return $post;
	}

	public function list_games() {
		$posts = get_posts(
			array(
				'post_type'      => 'game',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = ghub_get_game( $p );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Instant search across game titles and category names.
	 * Returns light payloads (name, url, icon) for a navigate-on-click dropdown.
	 */
	public function search( $req ) {
		$q   = trim( (string) $req->get_param( 'q' ) );
		$out = array( 'games' => array(), 'categories' => array() );
		if ( '' === $q || mb_strlen( $q ) < 1 ) {
			return rest_ensure_response( $out );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'game',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				's'              => $q,
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $p ) {
			$icon = get_post_meta( $p->ID, GHUB_META_ICON, true );
			if ( ! $icon ) {
				$icon = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
			}
			$out['games'][] = array(
				'name' => get_the_title( $p ),
				'url'  => get_permalink( $p ),
				'icon' => ghub_proxy_icon_url( (string) $icon ),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'game_category',
				'hide_empty' => false,
				'name__like' => $q,
				'number'     => 6,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$link = get_term_link( $t );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$out['categories'][] = array(
					'name'  => $t->name,
					'url'   => $link,
					'count' => (int) $t->count,
				);
			}
		}

		return rest_ensure_response( $out );
	}

	public function get_game( $req ) {
		$post = $this->resolve_game( $req );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		return rest_ensure_response( ghub_get_game( $post ) );
	}

	/**
	 * IP-throttled play/visit counter.
	 */
	private function count_event( $req, $event ) {
		$post = $this->resolve_game( $req );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		$cooldown = ( 'play' === $event ) ? 30 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS;
		$key      = 'ghub_' . $event . '_' . md5( ghub_client_ip() . '|' . $post->ID );
		$stats    = GameHub_Stats::get( $post->ID );
		$column   = ( 'play' === $event ) ? 'plays' : 'visits';

		if ( get_transient( $key ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'throttled' => true, $column => (int) $stats[ $column ] ) );
		}
		set_transient( $key, 1, $cooldown );

		$new = GameHub_Stats::bump( $post->ID, $column );
		return new WP_REST_Response( array( 'ok' => true, $column => (int) $new ) );
	}

	/**
	 * Like/dislike with per-voter dedupe (toggle semantics).
	 */
	private function vote( $req, $vote ) {
		$post = $this->resolve_game( $req );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		global $wpdb;
		$votes_table = GameHub_Stats::votes_table();
		$voter       = ghub_voter_hash();
		$current     = GameHub_Stats::get_vote( $post->ID, $voter );
		$prev        = $current['vote'] ?? '';

		// Re-clicking the same vote is a no-op (idempotent) — one vote per visitor.
		if ( $prev === $vote ) {
			return $this->vote_response( $vote, false, GameHub_Stats::get( $post->ID ) );
		}

		$like_delta    = 0;
		$dislike_delta = 0;
		if ( 'like' === $prev ) {
			$like_delta--;
		} elseif ( 'dislike' === $prev ) {
			$dislike_delta--;
		}
		if ( 'like' === $vote ) {
			$like_delta++;
		} else {
			$dislike_delta++;
		}

		if ( $current ) {
			$wpdb->update(
				$votes_table,
				array( 'vote' => $vote, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => (int) $current['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$votes_table,
				array(
					'post_id'    => $post->ID,
					'voter_hash' => $voter,
					'vote'       => $vote,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		GameHub_Stats::apply_vote_delta( $post->ID, $like_delta, $dislike_delta );

		return $this->vote_response( $vote, true, GameHub_Stats::get( $post->ID ) );
	}

	/**
	 * Uniform like/dislike response including the derived rating.
	 *
	 * @param string $user_vote Current voter's vote.
	 * @param bool   $changed   Whether counts changed.
	 * @param array  $stats     Fresh stats row.
	 * @return WP_REST_Response
	 */
	private function vote_response( $user_vote, $changed, $stats ) {
		$likes    = (int) $stats['likes'];
		$dislikes = (int) $stats['dislikes'];
		$votes    = $likes + $dislikes;

		return new WP_REST_Response(
			array(
				'ok'           => true,
				'changed'      => (bool) $changed,
				'user_vote'    => $user_vote,
				'likes'        => $likes,
				'dislikes'     => $dislikes,
				'rating'       => $votes > 0 ? round( $likes / $votes * 5, 2 ) : 0,
				'rating_count' => $votes,
				'like_ratio'   => $votes > 0 ? (int) round( $likes / $votes * 100 ) : 0,
			)
		);
	}

	/**
	 * Add a play-session duration (seconds). Clamped to a sane maximum so a
	 * stuck beacon can't inflate totals.
	 */
	public function session( $req ) {
		$post = $this->resolve_game( $req );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		$seconds = min( (int) $req->get_param( 'seconds' ), 4 * HOUR_IN_SECONDS );
		GameHub_Stats::add_session( $post->ID, $seconds );
		return new WP_REST_Response( array( 'ok' => true ) );
	}
}
