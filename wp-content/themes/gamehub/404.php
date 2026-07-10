<?php
/**
 * 404.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<section class="gh-section">
	<div class="gh-container gh-empty">
		<h1 style="font-size:64px;margin:0">404</h1>
		<p><?php esc_html_e( 'That game wandered off. Try another one.', 'gamehub' ); ?></p>
		<p><a class="gh-btn" href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'Browse all games', 'gamehub' ); ?></a></p>
	</div>
</section>
<?php get_footer(); ?>
