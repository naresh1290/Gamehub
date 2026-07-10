<?php
/**
 * All-games archive and search results for games.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_search = is_search();
?>
<section class="gh-section">
	<div class="gh-container">
		<div class="gh-section-head">
			<h1>
				<?php
				if ( $is_search ) {
					printf( /* translators: search term */ esc_html__( 'Results for “%s”', 'gamehub' ), esc_html( get_search_query() ) );
				} elseif ( isset( $_GET['sort'] ) && 'new' === sanitize_key( wp_unslash( $_GET['sort'] ) ) ) {
					esc_html_e( 'New Games', 'gamehub' );
				} else {
					esc_html_e( 'All Games', 'gamehub' );
				}
				?>
			</h1>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="gh-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					gamehub_card( get_post() );
				endwhile;
				?>
			</div>

			<?php
			gamehub_load_more_button(
				array(
					'sort'   => ( isset( $_GET['sort'] ) && 'new' === sanitize_key( wp_unslash( $_GET['sort'] ) ) ) ? 'new' : '',
					'search' => $is_search ? get_search_query() : '',
				)
			);
			?>
		<?php else : ?>
			<p class="gh-empty"><?php esc_html_e( 'No games found.', 'gamehub' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
