<?php
/**
 * Header.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="gh-header">
	<div class="gh-container gh-header-inner">
		<a class="gh-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="gh-logo-mark">🎮</span>
				<span><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>

		<form class="gh-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search games…', 'gamehub' ); ?>" autocomplete="off">
			<input type="hidden" name="post_type" value="game">
		</form>

		<button type="button" class="gh-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle theme', 'gamehub' ); ?>" data-gh-theme-toggle>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
		</button>
	</div>

	<?php $cats = gamehub_categories(); ?>
	<?php if ( $cats ) : ?>
		<nav class="gh-catbar" aria-label="<?php esc_attr_e( 'Categories', 'gamehub' ); ?>">
			<div class="gh-catbar-inner">
				<a class="gh-chip<?php echo is_post_type_archive( 'game' ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>"><?php esc_html_e( 'All', 'gamehub' ); ?></a>
				<?php foreach ( $cats as $cat ) : ?>
					<a class="gh-chip<?php echo ( is_tax( 'game_category', $cat->term_id ) ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>
</header>

<main id="gh-main">
