<?php
/**
 * GameHub SEO: meta description, canonical, Open Graph / Twitter cards, and
 * JSON-LD structured data. All values are derived dynamically from the live
 * site, post, term, and settings — nothing is hard-coded.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_SEO {

	public static function init() {
		// Replace WP's default canonical with our own (avoids duplicates).
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( __CLASS__, 'output' ), 1 );

		// Keep the XML sitemap focused on content — drop the authors sitemap.
		add_filter(
			'wp_sitemaps_add_provider',
			function ( $provider, $name ) {
				return 'users' === $name ? false : $provider;
			},
			10,
			2
		);
	}

	private static function settings() {
		return class_exists( 'GameHub_Settings' ) ? GameHub_Settings::get() : array();
	}

	/**
	 * Collapse to plain text and clamp to a length for meta descriptions.
	 */
	private static function clean( $text, $len = 160 ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( mb_strlen( $text ) > $len ) {
			$text = rtrim( mb_substr( $text, 0, $len - 1 ) ) . '…';
		}
		return $text;
	}

	private static function default_image() {
		$id = get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		if ( has_site_icon() ) {
			return get_site_icon_url( 512 );
		}
		return '';
	}

	/**
	 * Build the per-context SEO payload, or null if this page isn't one of ours.
	 *
	 * @return array|null
	 */
	private static function context() {
		$site = get_bloginfo( 'name' );
		$s    = self::settings();

		if ( is_singular( 'game' ) ) {
			$game = function_exists( 'ghub_get_game' ) ? ghub_get_game( get_post() ) : null;
			$name = get_the_title();
			$desc = self::clean( get_the_content() );
			if ( '' === $desc ) {
				$cat  = ( $game && ! empty( $game['categories'] ) ) ? $game['categories'][0] : '';
				$desc = self::clean( sprintf(
					/* translators: 1: game, 2: site, 3: category clause */
					__( 'Play %1$s online for free on %2$s. %3$sEnjoy this game in your browser — no download required.', 'gamehub' ),
					$name,
					$site,
					$cat ? sprintf( __( 'A fun %s game. ', 'gamehub' ), $cat ) : ''
				) );
			}
			return array(
				'title'       => $name,
				'description' => $desc,
				'url'         => get_permalink(),
				'image'       => $game['icon'] ?? '',
				'type'        => 'article',
				'game'        => $game,
			);
		}

		if ( is_tax( 'game_category' ) ) {
			$term = get_queried_object();
			$desc = self::clean( $term->description ?? '' );
			if ( '' === $desc ) {
				$desc = self::clean( sprintf(
					/* translators: 1: category, 2: site, 3: count */
					__( 'Play the best %1$s online for free on %2$s. %3$d games to play now in your browser.', 'gamehub' ),
					$term->name,
					$site,
					(int) $term->count
				) );
			}
			return array(
				'title'       => $term->name,
				'description' => $desc,
				'url'         => get_term_link( $term ),
				'image'       => self::default_image(),
				'type'        => 'website',
			);
		}

		if ( is_post_type_archive( 'game' ) ) {
			return array(
				'title'       => wp_get_document_title(),
				'description' => self::clean( sprintf( /* translators: site */ __( 'Browse and play all the best free online games on %s.', 'gamehub' ), $site ) ),
				'url'         => get_post_type_archive_link( 'game' ),
				'image'       => self::default_image(),
				'type'        => 'website',
			);
		}

		if ( is_front_page() ) {
			$tagline = ! empty( $s['site_tagline'] ) ? $s['site_tagline'] : get_bloginfo( 'description' );
			$src     = ! empty( $s['homepage_content'] ) ? $s['homepage_content'] : ( $tagline ? $tagline : sprintf( __( 'Play free online games on %s.', 'gamehub' ), $site ) );
			return array(
				'title'       => $site,
				'description' => self::clean( $src ),
				'url'         => home_url( '/' ),
				'image'       => self::default_image(),
				'type'        => 'website',
			);
		}

		return null;
	}

	public static function output() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return;
		}
		$site  = get_bloginfo( 'name' );
		$title = wp_get_document_title();

		echo "\n<!-- GameHub SEO -->\n";
		if ( $ctx['description'] ) {
			echo '<meta name="description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		}
		echo '<link rel="canonical" href="' . esc_url( $ctx['url'] ) . '">' . "\n";

		echo '<meta property="og:type" content="' . esc_attr( $ctx['type'] ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $ctx['description'] ) {
			echo '<meta property="og:description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $ctx['url'] ) . '">' . "\n";
		if ( $ctx['image'] ) {
			echo '<meta property="og:image" content="' . esc_url( $ctx['image'] ) . '">' . "\n";
		}

		echo '<meta name="twitter:card" content="' . ( $ctx['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $ctx['description'] ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		}
		if ( $ctx['image'] ) {
			echo '<meta name="twitter:image" content="' . esc_url( $ctx['image'] ) . '">' . "\n";
		}

		self::json_ld( $ctx );
	}

	private static function json_ld( $ctx ) {
		$site  = get_bloginfo( 'name' );
		$home  = home_url( '/' );
		$graph = array();

		if ( is_front_page() ) {
			$graph[] = array( '@type' => 'WebSite', 'name' => $site, 'url' => $home, 'description' => $ctx['description'] );
			$org     = array( '@type' => 'Organization', 'name' => $site, 'url' => $home );
			if ( $ctx['image'] ) {
				$org['logo'] = $ctx['image'];
			}
			$graph[] = $org;
		} elseif ( is_singular( 'game' ) ) {
			$game = $ctx['game'];
			$vg   = array(
				'@type'               => 'VideoGame',
				'name'                => $ctx['title'],
				'url'                 => $ctx['url'],
				'description'         => $ctx['description'],
				'applicationCategory' => 'Game',
				'operatingSystem'     => 'Web Browser',
				'publisher'           => array( '@type' => 'Organization', 'name' => $site ),
			);
			if ( $game && ! empty( $game['categories'] ) ) {
				$vg['genre'] = array_values( $game['categories'] );
			}
			if ( $ctx['image'] ) {
				$vg['image'] = $ctx['image'];
			}
			if ( $game && $game['rating_count'] > 0 ) {
				$vg['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => (string) $game['rating'],
					'ratingCount' => (string) $game['rating_count'],
					'bestRating'  => '5',
					'worstRating' => '0',
				);
			}
			$graph[] = $vg;
			$graph[] = self::breadcrumbs_game( $game );
		} elseif ( is_tax( 'game_category' ) ) {
			$term    = get_queried_object();
			$graph[] = array( '@type' => 'CollectionPage', 'name' => $ctx['title'], 'url' => $ctx['url'], 'description' => $ctx['description'] );
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => __( 'Home', 'gamehub' ), 'item' => $home ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => $term->name, 'item' => get_term_link( $term ) ),
				),
			);
		} elseif ( is_post_type_archive( 'game' ) ) {
			$graph[] = array( '@type' => 'CollectionPage', 'name' => $ctx['title'], 'url' => $ctx['url'], 'description' => $ctx['description'] );
		}

		if ( $graph ) {
			$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	private static function breadcrumbs_game( $game ) {
		$home  = home_url( '/' );
		$items = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => __( 'Home', 'gamehub' ), 'item' => $home ) );
		$pos   = 2;
		if ( $game && ! empty( $game['categories'] ) ) {
			$term = get_term_by( 'name', $game['categories'][0], 'game_category' );
			if ( $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $term->name, 'item' => $link );
				}
			}
		}
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title(), 'item' => get_permalink() );
		return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}
}
