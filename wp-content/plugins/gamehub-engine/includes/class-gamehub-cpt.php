<?php
/**
 * Registers the `game` post type and `game_category` taxonomy.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_CPT {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		// Keep pretty archives; make sure the archive is orderable/filterable.
		add_action( 'pre_get_posts', array( $this, 'adjust_archives' ) );
	}

	/**
	 * Slugs are configurable so the suite stays white-label across sites.
	 */
	private function slugs() {
		$s = GameHub_Settings::get();
		return array(
			'single'  => sanitize_title( $s['slug_game'] ?? 'g' ) ?: 'g',
			'archive' => sanitize_title( $s['slug_archive'] ?? 'games' ) ?: 'games',
			'cat'     => sanitize_title( $s['slug_category'] ?? 'c' ) ?: 'c',
		);
	}

	public function register() {
		$slugs = $this->slugs();

		register_post_type(
			'game',
			array(
				'labels'            => array(
					'name'               => __( 'Games', 'gamehub-engine' ),
					'singular_name'      => __( 'Game', 'gamehub-engine' ),
					'add_new'            => __( 'Add New', 'gamehub-engine' ),
					'add_new_item'       => __( 'Add New Game', 'gamehub-engine' ),
					'edit_item'          => __( 'Edit Game', 'gamehub-engine' ),
					'new_item'           => __( 'New Game', 'gamehub-engine' ),
					'view_item'          => __( 'View Game', 'gamehub-engine' ),
					'search_items'       => __( 'Search Games', 'gamehub-engine' ),
					'not_found'          => __( 'No games found', 'gamehub-engine' ),
					'not_found_in_trash' => __( 'No games found in Trash', 'gamehub-engine' ),
					'all_items'          => __( 'All Games', 'gamehub-engine' ),
					'menu_name'          => __( 'Games', 'gamehub-engine' ),
				),
				'public'            => true,
				'has_archive'       => $slugs['archive'],
				'menu_icon'         => 'dashicons-games',
				'menu_position'     => 25,
				'show_in_rest'      => true,
				'supports'          => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes' ),
				'rewrite'           => array(
					'slug'       => $slugs['single'],
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			'game_category',
			'game',
			array(
				'labels'            => array(
					'name'          => __( 'Game Categories', 'gamehub-engine' ),
					'singular_name' => __( 'Game Category', 'gamehub-engine' ),
					'search_items'  => __( 'Search Categories', 'gamehub-engine' ),
					'all_items'     => __( 'All Categories', 'gamehub-engine' ),
					'edit_item'     => __( 'Edit Category', 'gamehub-engine' ),
					'update_item'   => __( 'Update Category', 'gamehub-engine' ),
					'add_new_item'  => __( 'Add New Category', 'gamehub-engine' ),
					'new_item_name' => __( 'New Category Name', 'gamehub-engine' ),
					'menu_name'     => __( 'Categories', 'gamehub-engine' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'         => $slugs['cat'],
					'with_front'   => false,
					'hierarchical' => true,
				),
			)
		);

		// Register the meta so it is available in the REST API / block editor.
		foreach ( array( GHUB_META_IFRAME, GHUB_META_ICON, GHUB_META_SOURCE_ID ) as $key ) {
			register_post_meta(
				'game',
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Default ordering + posts-per-page for game archives and category pages.
	 */
	public function adjust_archives( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'game' ) || $query->is_tax( 'game_category' ) ) {
			$per_page = (int) ( GameHub_Settings::get()['per_page'] ?? 60 );
			$query->set( 'posts_per_page', $per_page > 0 ? $per_page : 60 );

			$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
			if ( 'new' === $sort ) {
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
			} else {
				$query->set( 'orderby', 'menu_order title' );
				$query->set( 'order', 'ASC' );
			}
		}
	}
}
