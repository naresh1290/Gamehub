<?php
/**
 * Footer.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<footer class="gh-footer">
	<div class="gh-footer-inner">
		<div>
			<?php
			$footer_text = get_theme_mod( 'gamehub_footer_text', '' );
			if ( $footer_text ) {
				echo wp_kses_post( wpautop( $footer_text ) );
			} else {
				printf(
					/* translators: 1: year, 2: site name */
					esc_html__( '© %1$s %2$s. All games are property of their respective owners.', 'gamehub' ),
					esc_html( wp_date( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
			}
			?>
		</div>
		<nav aria-label="<?php esc_attr_e( 'Footer', 'gamehub' ); ?>">
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1, 'fallback_cb' => false ) );
			}
			$sitemap = get_page_by_path( 'sitemap' );
			if ( $sitemap ) {
				echo ' <a href="' . esc_url( get_permalink( $sitemap ) ) . '">' . esc_html__( 'Sitemap', 'gamehub' ) . '</a>';
			}
			?>
		</nav>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
