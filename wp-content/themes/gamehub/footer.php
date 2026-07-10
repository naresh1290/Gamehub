<?php
/**
 * Footer — closes the app shell.
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
				<div class="gh-footer-links">
					<?php
					$gh_fpages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 20 ) );
					foreach ( (array) $gh_fpages as $gh_fp ) {
						echo '<a href="' . esc_url( get_permalink( $gh_fp ) ) . '">' . esc_html( get_the_title( $gh_fp ) ) . '</a>';
					}
					?>
				</div>
				<div class="gh-footer-copy">
					<?php
					$gh_ft = get_theme_mod( 'gamehub_footer_text', '' );
					if ( $gh_ft ) {
						echo wp_kses_post( $gh_ft );
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
			</div>
		</footer>
	</div><!-- .gh-shell -->
</div><!-- .gh-app -->

<?php wp_footer(); ?>
</body>
</html>
