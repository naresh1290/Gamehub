<?php
/**
 * Header — app shell: fixed left sidebar (nav + categories + pages) and a
 * top bar with instant search.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gh_cats = function_exists( 'gamehub_categories' ) ? gamehub_categories() : array();
$gh_archive = get_post_type_archive_link( 'game' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="gh-app">

	<aside class="gh-sidebar" id="gh-sidebar">
		<div class="gh-sidebar-head">
			<a class="gh-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( has_site_icon() ) : ?>
					<img class="gh-logo-icon" src="<?php echo esc_url( get_site_icon_url( 96 ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="36" height="36">
				<?php else : ?>
					<span class="gh-logo-mark">🎮</span>
				<?php endif; ?>
				<span class="gh-logo-text"><?php bloginfo( 'name' ); ?></span>
			</a>
			<button class="gh-sidebar-close" type="button" data-gh-sidebar-close aria-label="<?php esc_attr_e( 'Close menu', 'gamehub' ); ?>">✕</button>
		</div>

		<nav class="gh-nav" aria-label="<?php esc_attr_e( 'Main', 'gamehub' ); ?>">
			<a class="gh-nav-item<?php echo is_front_page() ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php esc_attr_e( 'Home', 'gamehub' ); ?>">
				<span class="gh-nav-ico">🏠</span><span class="gh-nav-txt"><?php esc_html_e( 'Home', 'gamehub' ); ?></span>
			</a>
			<a class="gh-nav-item" href="<?php echo esc_url( add_query_arg( 'sort', 'new', $gh_archive ) ); ?>" title="<?php esc_attr_e( 'New', 'gamehub' ); ?>">
				<span class="gh-nav-ico">✨</span><span class="gh-nav-txt"><?php esc_html_e( 'New', 'gamehub' ); ?></span>
			</a>
			<a class="gh-nav-item<?php echo is_post_type_archive( 'game' ) && ! isset( $_GET['sort'] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $gh_archive ); ?>" title="<?php esc_attr_e( 'All Games', 'gamehub' ); ?>">
				<span class="gh-nav-ico">🔥</span><span class="gh-nav-txt"><?php esc_html_e( 'All Games', 'gamehub' ); ?></span>
			</a>

			<?php if ( $gh_cats ) : ?>
				<div class="gh-nav-sep"></div>
				<div class="gh-nav-label"><?php esc_html_e( 'Categories', 'gamehub' ); ?></div>
				<?php foreach ( $gh_cats as $gh_cat ) : ?>
					<?php $gh_link = get_term_link( $gh_cat ); if ( is_wp_error( $gh_link ) ) { continue; } ?>
					<a class="gh-nav-item gh-nav-cat<?php echo is_tax( 'game_category', $gh_cat->term_id ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $gh_link ); ?>" title="<?php echo esc_attr( $gh_cat->name ); ?>">
						<span class="gh-cat-ico"><?php echo esc_html( mb_substr( $gh_cat->name, 0, 1 ) ); ?></span>
						<span class="gh-nav-cat-name"><?php echo esc_html( $gh_cat->name ); ?></span>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php
			$gh_pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 20 ) );
			if ( $gh_pages ) :
				?>
				<div class="gh-nav-sep"></div>
				<div class="gh-nav-foot">
					<?php foreach ( $gh_pages as $gh_page ) : ?>
						<a class="gh-nav-foot-link" href="<?php echo esc_url( get_permalink( $gh_page ) ); ?>"><?php echo esc_html( get_the_title( $gh_page ) ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</nav>
	</aside>

	<div class="gh-sidebar-overlay" data-gh-sidebar-close></div>

	<div class="gh-shell">
		<header class="gh-topbar">
			<button class="gh-menu-btn" type="button" data-gh-sidebar-open aria-label="<?php esc_attr_e( 'Open menu', 'gamehub' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
			</button>
			<a class="gh-topbar-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( has_site_icon() ) : ?>
					<img class="gh-logo-icon" src="<?php echo esc_url( get_site_icon_url( 64 ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="34" height="34">
				<?php else : ?>
					<span class="gh-logo-mark">🎮</span>
				<?php endif; ?>
			</a>

			<div class="gh-search" data-gh-search>
				<svg class="gh-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
				<input type="search" class="gh-search-input" placeholder="<?php esc_attr_e( 'Search games and categories', 'gamehub' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search', 'gamehub' ); ?>">
				<div class="gh-search-panel" hidden></div>
			</div>
		</header>

		<main class="gh-main" id="gh-main">
