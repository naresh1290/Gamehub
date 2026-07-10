<?php
/**
 * Sitemap support.
 *
 * WordPress core (>= 5.5) already exposes public post types and taxonomies at
 * /wp-sitemap.xml, so games and categories are included automatically. This
 * class ensures they stay included and provides a data helper the theme uses
 * to render a human-readable HTML sitemap page.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Sitemap {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Games can be numerous; cap XML sitemap page size sensibly.
		add_filter( 'wp_sitemaps_max_urls', array( $this, 'max_urls' ), 10, 2 );
	}

	public function max_urls( $max, $type ) {
		return $max; // Keep core default (2000); overridable by site owners.
	}

	/**
	 * Structured data for a rendered HTML sitemap: categories, each with games.
	 *
	 * @return array{categories: array<int, array>, uncategorized: array}
	 */
	public static function html_data() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'game_category',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		$categories = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$games = get_posts(
					array(
						'post_type'      => 'game',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'no_found_rows'  => true,
						'tax_query'      => array(
							array(
								'taxonomy' => 'game_category',
								'field'    => 'term_id',
								'terms'    => $term->term_id,
							),
						),
					)
				);
				$categories[] = array(
					'name'  => $term->name,
					'url'   => get_term_link( $term ),
					'games' => array_map(
						static function ( $p ) {
							return array( 'name' => get_the_title( $p ), 'url' => get_permalink( $p ) );
						},
						$games
					),
				);
			}
		}
		return array( 'categories' => $categories );
	}
}
