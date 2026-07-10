<?php
/**
 * GameHub theme bootstrap.
 *
 * Presentation only. All game data and metrics come from the GameHub Engine
 * plugin; the theme degrades to a clear admin notice when it is inactive.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEHUB_THEME_VERSION', '1.3.0' );

/**
 * True when the GameHub Engine plugin is active and exposing its API.
 */
function gamehub_engine_active() {
	return function_exists( 'ghub_get_game' ) && post_type_exists( 'game' );
}

/* Theme setup ---------------------------------------------------------- */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'custom-logo', array( 'height' => 40, 'width' => 200, 'flex-width' => true, 'flex-height' => true ) );
		add_image_size( 'gamehub-thumb', 400, 400, true );
		register_nav_menus( array( 'footer' => __( 'Footer Menu', 'gamehub' ) ) );
	}
);

/* Assets --------------------------------------------------------------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		$dir = get_template_directory();

		wp_enqueue_style( 'gamehub', get_stylesheet_uri(), array(), gamehub_asset_ver( 'style.css' ) );

		wp_register_script( 'gamehub', get_template_directory_uri() . '/assets/js/gamehub.js', array(), gamehub_asset_ver( 'assets/js/gamehub.js' ), true );

		wp_localize_script(
			'gamehub',
			'GAMEHUB',
			array(
				'restBase' => esc_url_raw( rest_url( 'gamehub/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'homeUrl'  => home_url( '/' ),
			)
		);
		wp_enqueue_script( 'gamehub' );
	}
);

/**
 * Cache-bust assets by file mtime.
 */
function gamehub_asset_ver( $rel ) {
	$path = get_template_directory() . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? GAMEHUB_THEME_VERSION . '.' . filemtime( $path ) : GAMEHUB_THEME_VERSION;
}

/* SEO meta titles ------------------------------------------------------ */
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$site = get_bloginfo( 'name' );

		$suffix  = '';
		$tagline = get_bloginfo( 'description' );
		if ( class_exists( 'GameHub_Settings' ) ) {
			$s       = GameHub_Settings::get();
			$suffix  = (string) ( $s['meta_suffix'] ?? '' );
			$tagline = ! empty( $s['site_tagline'] ) ? $s['site_tagline'] : $tagline;
		}
		// Placeholders keep the suffix dynamic if the site title/tagline change.
		$suffix = str_replace(
			array( '%site%', '%sitetitle%', '%tagline%' ),
			array( $site, $site, $tagline ),
			$suffix
		);

		if ( is_front_page() ) {
			return $tagline ? ( $site . ' - ' . $tagline ) : $site;
		}
		if ( is_singular( 'game' ) ) {
			return get_the_title() . $suffix;
		}
		if ( is_tax( 'game_category' ) ) {
			$term = get_queried_object();
			return ( $term ? $term->name : __( 'Games', 'gamehub' ) ) . $suffix;
		}
		if ( is_post_type_archive( 'game' ) ) {
			$is_new = isset( $_GET['sort'] ) && 'new' === sanitize_key( wp_unslash( $_GET['sort'] ) );
			return $is_new
				? sprintf( /* translators: site name */ __( 'New Games - Play Now on %s', 'gamehub' ), $site )
				: sprintf( /* translators: site name */ __( 'Checkout All Available Games on %s', 'gamehub' ), $site );
		}
		return $title;
	},
	20
);

/* Body classes for the player page ------------------------------------- */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( 'game' ) ) {
			$classes[] = 'gh-player-page';
		}
		return $classes;
	}
);

/* Dependency notice ---------------------------------------------------- */
add_action(
	'admin_notices',
	function () {
		if ( gamehub_engine_active() ) {
			return;
		}
		$engine = 'gamehub-engine/gamehub-engine.php';
		$installed = array_key_exists( $engine, get_plugins() );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'GameHub theme:', 'gamehub' ) . '</strong> ';
		if ( $installed && current_user_can( 'activate_plugins' ) ) {
			$url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $engine ) ), 'activate-plugin_' . $engine );
			printf(
				/* translators: activation link */
				esc_html__( 'requires the GameHub Engine plugin. %s', 'gamehub' ),
				'<a href="' . esc_url( $url ) . '" class="button button-primary" style="margin-left:6px">' . esc_html__( 'Activate GameHub Engine', 'gamehub' ) . '</a>'
			);
		} else {
			esc_html_e( 'requires the GameHub Engine plugin. Please install and activate it to add and display games.', 'gamehub' );
		}
		echo '</p></div>';
	}
);

/* Customizer (colors + footer text) ------------------------------------ */
add_action(
	'customize_register',
	function ( $wp_customize ) {
		$wp_customize->add_section( 'gamehub_options', array( 'title' => __( 'GameHub Options', 'gamehub' ), 'priority' => 30 ) );

		$wp_customize->add_setting( 'gamehub_accent', array( 'default' => '#6c2bd9', 'sanitize_callback' => 'sanitize_hex_color' ) );
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'gamehub_accent',
				array( 'label' => __( 'Accent color', 'gamehub' ), 'section' => 'gamehub_options' )
			)
		);

		$wp_customize->add_setting( 'gamehub_footer_text', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
		$wp_customize->add_control( 'gamehub_footer_text', array( 'label' => __( 'Footer text', 'gamehub' ), 'section' => 'gamehub_options', 'type' => 'textarea' ) );
	}
);

/* Inject accent color override ----------------------------------------- */
add_action(
	'wp_head',
	function () {
		$accent = get_theme_mod( 'gamehub_accent', '' );
		if ( $accent ) {
			echo '<style id="gamehub-accent">:root{--gh-accent:' . esc_attr( $accent ) . ';}</style>';
		}
	}
);

/* Self-update (GitHub releases) ---------------------------------------- */
require_once get_template_directory() . '/inc/class-gamehub-updater.php';
add_action(
	'admin_init',
	function () {
		if ( ! class_exists( 'GameHub_Updater' ) ) {
			return;
		}
		$repo  = 'naresh1290/Gamehub';
		$token = '';
		if ( class_exists( 'GameHub_Settings' ) ) {
			$s     = GameHub_Settings::get();
			$repo  = ! empty( $s['github_repo_theme'] ) ? $s['github_repo_theme'] : $repo;
			$token = ! empty( $s['github_token'] ) ? $s['github_token'] : '';
		}
		new GameHub_Updater(
			array(
				'type'       => 'theme',
				'file'       => get_template(),
				'slug'       => get_template(),
				'version'    => GAMEHUB_THEME_VERSION,
				'repo'       => $repo,
				'token'      => $token,
				'asset_name' => 'gamehub.zip',
				'tag_prefix' => 'theme-',
			)
		);
	}
);

require_once get_template_directory() . '/inc/template-tags.php';

require_once get_template_directory() . '/inc/class-gamehub-seo.php';
GameHub_SEO::init();
