<?php
/**
 * Search results (game grid).
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<section class="gh-section">
	<div class="gh-container">
		<div class="gh-section-head">
			<h2><?php printf( /* translators: search term */ esc_html__( 'Results for “%s”', 'gamehub' ), esc_html( get_search_query() ) ); ?></h2>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="gh-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					if ( 'game' === get_post_type() && gamehub_engine_active() ) {
						gamehub_card( get_post() );
					} else {
						echo '<a class="gh-card" href="' . esc_url( get_permalink() ) . '"><div class="gh-card-body"><p class="gh-card-title">' . esc_html( get_the_title() ) . '</p></div></a>';
					}
				endwhile;
				?>
			</div>
			<div class="gh-pagination">
				<?php echo paginate_links( array( 'mid_size' => 1, 'prev_text' => __( '← Prev', 'gamehub' ), 'next_text' => __( 'Next →', 'gamehub' ) ) ); ?>
			</div>
		<?php else : ?>
			<p class="gh-empty"><?php esc_html_e( 'No games matched your search.', 'gamehub' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
