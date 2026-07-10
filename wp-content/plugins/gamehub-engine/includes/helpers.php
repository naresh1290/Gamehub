<?php
/**
 * Shared helper functions for GameHub Engine.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys used on the `game` post type.
 */
const GHUB_META_SOURCE_ID   = '_ghub_source_id';
const GHUB_META_IFRAME      = '_ghub_iframe_url';
const GHUB_META_ICON        = '_ghub_icon_url';
const GHUB_META_FLAGS       = '_ghub_flags';
const GHUB_META_PRIMARY_CAT = '_ghub_primary_category';

/**
 * Pick the first non-empty value from $data for any of the candidate $keys.
 *
 * @param array        $data    Source associative array.
 * @param string[]     $keys    Candidate keys in priority order.
 * @param mixed        $default Fallback.
 * @return mixed
 */
function ghub_pick( $data, $keys, $default = '' ) {
	foreach ( (array) $keys as $key ) {
		if ( isset( $data[ $key ] ) && '' !== $data[ $key ] && null !== $data[ $key ] ) {
			return $data[ $key ];
		}
	}
	return $default;
}

/**
 * Determine whether an array is a JSON list (sequential integer keys).
 *
 * @param mixed $value Value to test.
 * @return bool
 */
function ghub_is_list( $value ) {
	if ( ! is_array( $value ) ) {
		return false;
	}
	if ( function_exists( 'array_is_list' ) ) {
		return array_is_list( $value );
	}
	if ( array() === $value ) {
		return true;
	}
	return array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Normalise a loosely-typed truthy value ("1", "true", "yes", true, 1) to bool.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function ghub_truthy( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_numeric( $value ) ) {
		return 1 === (int) $value;
	}
	$value = strtolower( trim( (string) $value ) );
	return in_array( $value, array( '1', 'true', 'yes', 'y', 'on' ), true );
}

/**
 * Best-effort client IP resolution behind Cloudflare / proxies.
 *
 * @return string
 */
function ghub_client_ip() {
	$candidates = array(
		$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
		$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
		$_SERVER['REMOTE_ADDR'] ?? '',
	);
	foreach ( $candidates as $candidate ) {
		foreach ( array_map( 'trim', explode( ',', (string) $candidate ) ) as $part ) {
			if ( filter_var( $part, FILTER_VALIDATE_IP ) ) {
				return $part;
			}
		}
	}
	return '';
}

/**
 * Detect an HTTPS request even behind a terminating proxy.
 *
 * @return bool
 */
function ghub_is_https() {
	if ( is_ssl() ) {
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && false !== stripos( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'], 'https' ) ) {
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CF_VISITOR'] ) && false !== stripos( (string) $_SERVER['HTTP_CF_VISITOR'], 'https' ) ) {
		return true;
	}
	return false;
}

/**
 * Return (creating if needed) a stable per-visitor id stored in a cookie.
 * Used to deduplicate votes/plays for logged-out users.
 *
 * @return string
 */
function ghub_visitor_id() {
	$cookie = 'ghub_vid';
	$cur    = isset( $_COOKIE[ $cookie ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) ) : '';
	if ( preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $cur ) ) {
		return $cur;
	}
	$vid = 'g-' . wp_generate_password( 24, false, false );
	if ( ! headers_sent() ) {
		setcookie(
			$cookie,
			$vid,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => '/',
				'secure'   => ghub_is_https(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
	$_COOKIE[ $cookie ] = $vid;
	return $vid;
}

/**
 * A hashed identity for the current voter (logged-in user or cookie visitor).
 *
 * @return string 64-char sha256 hash.
 */
function ghub_voter_hash() {
	if ( is_user_logged_in() ) {
		return hash( 'sha256', 'user:' . get_current_user_id() );
	}
	$vid = ghub_visitor_id();
	if ( '' !== $vid ) {
		return hash( 'sha256', 'guest:' . $vid );
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return hash( 'sha256', 'fallback:' . ghub_client_ip() . '|' . $ua );
}

/**
 * Rewrite a raw playable/iframe URL through the configured proxy origin.
 * When no proxy origin is configured, the URL is returned unchanged — the
 * theme is fully usable with plain, directly-embeddable game URLs.
 *
 * @param string $url Raw game URL.
 * @return string
 */
function ghub_proxy_iframe_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$settings = GameHub_Settings::get();
	$origin   = trim( (string) ( $settings['proxy_origin'] ?? '' ) );
	if ( '' === $origin ) {
		return $url;
	}
	$origin = untrailingslashit( $origin );

	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return $url;
	}
	$host = strtolower( (string) $parts['host'] );

	// Already pointing at the proxy origin host.
	if ( $host === strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) ) ) {
		return $url;
	}

	$prefix = '';
	if ( preg_match( '/^([0-9]+)\.playables\.usercontent\.goog$/i', $host, $m ) ) {
		$prefix = 'p';
	} elseif ( preg_match( '/^([0-9]+)\.allownetworkplayables\.usercontent\.goog$/i', $host, $m ) ) {
		$prefix = 'ap';
	} else {
		return $url;
	}

	$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
	if ( 0 === strpos( $path, '/v/' ) ) {
		$path = substr( $path, 2 );
	}
	if ( '' === $path || '/' !== $path[0] ) {
		$path = '/' . ltrim( $path, '/' );
	}

	$proxied = $origin . '/' . $prefix . $m[1] . $path;
	if ( ! empty( $parts['query'] ) ) {
		$proxied .= '?' . $parts['query'];
	}

	$hash = array();
	if ( ! empty( $parts['fragment'] ) ) {
		parse_str( (string) $parts['fragment'], $hash );
	}
	if ( empty( $hash['flags'] ) ) {
		$hash['flags'] = wp_json_encode( array( 'enableServiceWorker' => false ) );
	}
	$hash['origin'] = $origin;

	$fragment = http_build_query( $hash, '', '&', PHP_QUERY_RFC3986 );
	if ( '' !== $fragment ) {
		$proxied .= '#' . $fragment;
	}

	return $proxied;
}

/**
 * Rewrite a CDN icon URL to route through this site's own nginx reverse proxy.
 *
 * With the proxy enabled, an icon at `https://{cdn_host}/PATH` is served as
 * `https://{this-site}/{proxy_path}/PATH`, so images load from your own domain
 * instead of hotlinking the CDN. The original CDN URL is what's stored in post
 * meta; this rewrite happens only at render, so toggling the proxy needs no
 * re-import. Requires the matching nginx location (see deploy/nginx).
 *
 * @param string $url Raw icon URL.
 * @return string
 */
function ghub_proxy_icon_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$s          = GameHub_Settings::get();
	$cdn        = strtolower( trim( (string) ( $s['icon_cdn_host'] ?? '' ) ) );
	$proxy_on   = ! empty( $s['icon_proxy'] );
	$proxy_path = '/' . trim( (string) ( $s['icon_proxy_path'] ?? 'img' ), '/' ) . '/';

	// Case 1: a root-relative path (e.g. "/cdn-cgi/image/.../logo.png") — the feed
	// gives only the path; the CDN host is implicit.
	if ( '/' === $url[0] && '//' !== substr( $url, 0, 2 ) ) {
		if ( $proxy_on ) {
			return home_url( $proxy_path . ltrim( $url, '/' ) );
		}
		return $cdn ? 'https://' . $cdn . $url : $url;
	}

	// Case 2/3: an absolute URL — only rewrite when it points at the CDN host.
	if ( ! $proxy_on || '' === $cdn ) {
		return $url;
	}
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) || strtolower( (string) $parts['host'] ) !== $cdn ) {
		return $url;
	}
	$path = isset( $parts['path'] ) ? ltrim( (string) $parts['path'], '/' ) : '';
	$out  = home_url( $proxy_path . $path );
	if ( ! empty( $parts['query'] ) ) {
		$out .= '?' . $parts['query'];
	}
	return $out;
}

/**
 * Order game queries by "top played and highest rated first".
 *
 * Applies to any WP_Query flagged with `ghub_order => 'popular'`, and to the
 * main game archive / category pages by default (unless ?sort=new is set).
 *
 * @param array    $clauses SQL clauses.
 * @param WP_Query $query   Query.
 * @return array
 */
function ghub_popular_order_clauses( $clauses, $query ) {
	$apply = false;
	if ( 'popular' === $query->get( 'ghub_order' ) ) {
		$apply = true;
	} elseif ( ! is_admin() && $query->is_main_query() && ( $query->is_post_type_archive( 'game' ) || $query->is_tax( 'game_category' ) ) ) {
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
		if ( 'new' !== $sort ) {
			$apply = true;
		}
	}
	if ( ! $apply ) {
		return $clauses;
	}

	global $wpdb;
	$stats = $wpdb->prefix . 'gh_stats';
	if ( false === strpos( $clauses['join'], ' ghs ' ) ) {
		$clauses['join'] .= " LEFT JOIN $stats ghs ON ghs.post_id = {$wpdb->posts}.ID ";
	}
	$clauses['orderby'] =
		'COALESCE(ghs.plays,0) DESC, ' .
		'(COALESCE(ghs.likes,0) / NULLIF(COALESCE(ghs.likes,0) + COALESCE(ghs.dislikes,0), 0)) DESC, ' .
		'COALESCE(ghs.likes,0) DESC, ' .
		"{$wpdb->posts}.post_title ASC";
	return $clauses;
}
add_filter( 'posts_clauses', 'ghub_popular_order_clauses', 10, 2 );

/**
 * Game category terms ordered by total plays, then game count — popular first.
 *
 * @param int $limit Max terms (0 = all).
 * @return WP_Term[]
 */
function ghub_categories_by_popularity( $limit = 0 ) {
	global $wpdb;
	$stats = $wpdb->prefix . 'gh_stats';
	$sql   = "
		SELECT tt.term_id, SUM(COALESCE(s.plays,0)) AS plays, COUNT(p.ID) AS games
		FROM {$wpdb->term_taxonomy} tt
		INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
		INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'game' AND p.post_status = 'publish'
		LEFT JOIN $stats s ON s.post_id = p.ID
		WHERE tt.taxonomy = 'game_category'
		GROUP BY tt.term_id
		ORDER BY plays DESC, games DESC, MIN(tt.term_id) ASC
	";
	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
	}

	$rows  = $wpdb->get_results( $sql );
	$terms = array();
	foreach ( (array) $rows as $row ) {
		$term = get_term( (int) $row->term_id, 'game_category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$terms[] = $term;
		}
	}
	return $terms;
}

/**
 * Fetch and shape the public-facing data for a single game post.
 *
 * @param int|WP_Post $post Post or ID.
 * @return array|null
 */
function ghub_get_game( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'game' !== $post->post_type ) {
		return null;
	}

	$stats     = GameHub_Stats::get( $post->ID );
	$icon      = get_post_meta( $post->ID, GHUB_META_ICON, true );
	$iframe    = get_post_meta( $post->ID, GHUB_META_IFRAME, true );
	$thumb     = get_the_post_thumbnail_url( $post->ID, 'large' );
	$icon_src  = $icon ? $icon : ( $thumb ? $thumb : '' );
	// Categories, ordered with the primary category first.
	$term_objs = wp_get_post_terms( $post->ID, 'game_category' );
	$term_objs = is_wp_error( $term_objs ) ? array() : $term_objs;
	$primary_id = (int) get_post_meta( $post->ID, GHUB_META_PRIMARY_CAT, true );
	if ( $primary_id ) {
		usort(
			$term_objs,
			static function ( $a, $b ) use ( $primary_id ) {
				return ( (int) $b->term_id === $primary_id ) - ( (int) $a->term_id === $primary_id );
			}
		);
	}
	$terms = wp_list_pluck( $term_objs, 'name' );
	// Rating is derived from likes vs dislikes — no separate rating input.
	$likes     = (int) $stats['likes'];
	$dislikes  = (int) $stats['dislikes'];
	$votes     = $likes + $dislikes;
	$rating    = $votes > 0 ? round( $likes / $votes * 5, 2 ) : 0.0;
	$avg_sess  = ( $stats['session_count'] > 0 ) ? (int) round( $stats['session_seconds'] / $stats['session_count'] ) : 0;

	return array(
		'id'              => $post->ID,
		'source_id'       => get_post_meta( $post->ID, GHUB_META_SOURCE_ID, true ),
		'name'            => get_the_title( $post ),
		'slug'            => $post->post_name,
		'permalink'       => get_permalink( $post ),
		'iframe_url'      => ghub_proxy_iframe_url( $iframe ),
		'raw_iframe_url'  => $iframe,
		'icon'            => ghub_proxy_icon_url( $icon_src ),
		'raw_icon'        => $icon_src,
		'categories'      => $terms,
		'primary_category' => $terms ? $terms[0] : '',
		'plays'           => (int) $stats['plays'],
		'visits'          => (int) $stats['visits'],
		'likes'           => $likes,
		'dislikes'        => $dislikes,
		'rating'          => (float) $rating,
		'rating_count'    => $votes,
		'like_ratio'      => $votes > 0 ? (int) round( $likes / $votes * 100 ) : 0,
		'avg_session'     => $avg_sess,
	);
}
