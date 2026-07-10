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

			<div class="gh-pagination">
				<?php
				echo paginate_links(
					array(
						'mid_size'  => 1,
						'prev_text' => __( '← Prev', 'gamehub' ),
						'next_text' => __( 'Next →', 'gamehub' ),
					)
				);
				?>
			</div>
		<?php else : ?>
			<p class="gh-empty"><?php esc_html_e( 'No games found.', 'gamehub' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
