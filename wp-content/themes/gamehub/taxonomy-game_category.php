<?php
/**
 * Category archive: lists games in a game_category term.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term = get_queried_object();
?>
<section class="gh-section">
	<div class="gh-container">
		<?php
		gamehub_breadcrumbs(
			array(
				array( 'name' => __( 'Home', 'gamehub' ), 'url' => home_url( '/' ) ),
				array( 'name' => $term->name ),
			)
		);
		?>
		<div class="gh-section-head">
			<h1><?php echo esc_html( $term->name ); ?></h1>
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
			<p class="gh-empty"><?php esc_html_e( 'No games in this category yet.', 'gamehub' ); ?></p>
		<?php endif; ?>

		<?php
		if ( $term && ! empty( $term->description ) ) {
			gamehub_content_section( $term->description );
		}
		?>
	</div>
</section>
<?php get_footer(); ?>
