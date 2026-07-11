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

define( 'GAMEHUB_THEME_VERSION', '1.5.1' );

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

/* Edit static pages with the Classic Editor (like the engine does for games). */
add_filter(
	'use_block_editor_for_post_type',
	function ( $use, $post_type ) {
		return 'page' === $post_type ? false : $use;
	},
	10,
	2
);

/* Default utility pages ------------------------------------------------- */
/**
 * Create About, Contact, Privacy Policy and Terms pages on theme activation,
 * linked automatically in the sidebar footer. Existing pages (matched by
 * slug) are left untouched so edits are never overwritten.
 */
add_action( 'after_switch_theme', 'gamehub_install_pages' );

function gamehub_install_pages() {
	$site   = get_bloginfo( 'name' );
	$host   = wp_parse_url( home_url(), PHP_URL_HOST );
	$mail   = 'hello@' . ( $host ? preg_replace( '/^www\./', '', $host ) : 'example.com' );

	$about = sprintf(
		"<p>Welcome to %s — your home for free online games you can play instantly in your browser, with no downloads and no installs.</p>\n<p>We bring together hundreds of games across every category, from action and puzzles to sports, racing and adventure, all in one place. New games are added regularly, so there is always something fresh to play.</p>\n<p>Have feedback or a suggestion? Head over to our <a href=\"%s\">Contact</a> page — we would love to hear from you.</p>",
		esc_html( $site ),
		esc_url( home_url( '/contact/' ) )
	);

	$contact = sprintf(
		"<p>We would love to hear from you. Whether it is feedback, a game suggestion, a business enquiry, or a question about %s, get in touch and we will get back to you as soon as we can.</p>\n<p><strong>Email:</strong> %s</p>\n<p><em>Replace this text with your preferred contact details or embed a contact form.</em></p>",
		esc_html( $site ),
		esc_html( $mail )
	);

	$privacy = sprintf(
		"<p>This Privacy Policy explains how %s collects, uses and protects any information you provide when you use this website.</p>\n<h2>Information We Collect</h2>\n<p>We may collect anonymous usage data such as the games you play and general analytics to improve the site. We do not require you to create an account to play.</p>\n<h2>Cookies</h2>\n<p>We use cookies and similar technologies to remember your preferences and to measure how the site is used.</p>\n<h2>Third-Party Content</h2>\n<p>Games are embedded from third-party providers who may set their own cookies. Please review their policies for details.</p>\n<h2>Contact</h2>\n<p>Questions about this policy? Reach us at %s.</p>\n<p><em>This is a starter template — please review and adapt it to your legal requirements.</em></p>",
		esc_html( $site ),
		esc_html( $mail )
	);

	$terms = sprintf(
		"<p>By accessing and using %s, you agree to the following terms. Please read them carefully.</p>\n<h2>Use of the Site</h2>\n<p>The games and content on this site are provided for personal, non-commercial entertainment. You agree not to misuse the site or interfere with its normal operation.</p>\n<h2>Intellectual Property</h2>\n<p>Games are the property of their respective owners and are embedded here under the terms permitted by their providers.</p>\n<h2>Disclaimer</h2>\n<p>The site is provided \"as is\" without warranties of any kind. We are not responsible for the content or availability of third-party games.</p>\n<h2>Changes</h2>\n<p>We may update these terms from time to time. Continued use of the site means you accept any changes.</p>\n<p><em>This is a starter template — please review and adapt it to your legal requirements.</em></p>",
		esc_html( $site )
	);

	$pages = array(
		array( 'slug' => 'about', 'title' => __( 'About', 'gamehub' ), 'order' => 1, 'content' => $about ),
		array( 'slug' => 'contact', 'title' => __( 'Contact', 'gamehub' ), 'order' => 2, 'content' => $contact ),
		array( 'slug' => 'privacy-policy', 'title' => __( 'Privacy Policy', 'gamehub' ), 'order' => 3, 'content' => $privacy ),
		array( 'slug' => 'terms', 'title' => __( 'Terms', 'gamehub' ), 'order' => 4, 'content' => $terms ),
	);

	foreach ( $pages as $p ) {
		$existing = get_page_by_path( $p['slug'] );
		if ( $existing ) {
			// Make sure a pre-existing (often draft) page is live and ordered
			// consistently in the footer — without touching its content.
			$fix = array();
			if ( 'publish' !== $existing->post_status ) {
				$fix['post_status'] = 'publish';
			}
			if ( (int) $existing->menu_order !== $p['order'] ) {
				$fix['menu_order'] = $p['order'];
			}
			if ( $fix ) {
				$fix['ID'] = $existing->ID;
				wp_update_post( $fix );
			}
			if ( 'privacy-policy' === $p['slug'] ) {
				update_option( 'wp_page_for_privacy_policy', $existing->ID );
			}
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_content' => $p['content'],
				'menu_order'   => $p['order'],
				'comment_status' => 'closed',
				'ping_status'  => 'closed',
			)
		);

		if ( 'privacy-policy' === $p['slug'] && $id && ! is_wp_error( $id ) ) {
			update_option( 'wp_page_for_privacy_policy', $id );
		}
	}
}

/**
 * Remove the default "Uncategorized" post category on theme activation. A
 * games site uses the `game_category` taxonomy, so the default blog category is
 * just clutter. Only removed when empty, and the site default is cleared first
 * (WordPress refuses to delete the category that is set as the default).
 */
add_action( 'after_switch_theme', 'gamehub_remove_default_category' );

function gamehub_remove_default_category() {
	if ( ! taxonomy_exists( 'category' ) ) {
		return;
	}

	$default_id = (int) get_option( 'default_category' );
	$term       = $default_id ? get_term( $default_id, 'category' ) : get_term_by( 'slug', 'uncategorized', 'category' );

	if ( ! $term || is_wp_error( $term ) || (int) $term->count > 0 ) {
		return;
	}

	if ( (int) get_option( 'default_category' ) === (int) $term->term_id ) {
		update_option( 'default_category', 0 );
	}

	wp_delete_term( (int) $term->term_id, 'category' );
}

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
