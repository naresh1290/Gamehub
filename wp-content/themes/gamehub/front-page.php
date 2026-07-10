<?php
/**
 * Homepage: hero + per-category game rows.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<?php // Recently played — populated client-side from localStorage. ?>
<section class="gh-section" data-gh-recent hidden>
	<div class="gh-container">
		<div class="gh-section-head"><h2><?php esc_html_e( 'Recently played', 'gamehub' ); ?></h2></div>
		<div class="gh-hscroll" data-gh-recent-grid></div>
	</div>
</section>

<?php if ( ! gamehub_engine_active() ) : ?>
	<div class="gh-container"><p class="gh-empty"><?php esc_html_e( 'Activate the GameHub Engine plugin and import games to get started.', 'gamehub' ); ?></p></div>
<?php else : ?>

	<?php
	// Popular strip: popular-flagged games first, then most-played.
	$popular_q = new WP_Query(
		array(
			'post_type'      => 'game',
			'posts_per_page' => 12,
			'no_found_rows'  => true,
			'ghub_order'     => 'popular',
		)
	);
	if ( $popular_q->have_posts() ) :
		?>
		<section class="gh-section">
			<div class="gh-container">
				<div class="gh-section-head">
					<h2><?php esc_html_e( 'Popular games', 'gamehub' ); ?></h2>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>"><?php esc_html_e( 'View all', 'gamehub' ); ?> →</a>
				</div>
				<?php gamehub_grid( $popular_q ); ?>
			</div>
		</section>
		<?php
		wp_reset_postdata();
	endif;
	?>

	<?php
	// A row per category (top categories first), each with up to 12 games.
	$cats = gamehub_categories();
	foreach ( array_slice( $cats, 0, 12 ) as $cat ) :
		$q = new WP_Query(
			array(
				'post_type'      => 'game',
				'posts_per_page' => 12,
				'no_found_rows'  => true,
				'ghub_order'     => 'popular',
				'tax_query'      => array(
					array( 'taxonomy' => 'game_category', 'field' => 'term_id', 'terms' => $cat->term_id ),
				),
			)
		);
		if ( ! $q->have_posts() ) {
			continue;
		}
		?>
		<section class="gh-section">
			<div class="gh-container">
				<div class="gh-section-head">
					<h2><?php echo esc_html( $cat->name ); ?></h2>
					<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php esc_html_e( 'View all', 'gamehub' ); ?> →</a>
				</div>
				<?php gamehub_grid( $q ); ?>
			</div>
		</section>
		<?php
		wp_reset_postdata();
	endforeach;
	?>

	<?php
	$home_content = class_exists( 'GameHub_Settings' ) ? ( GameHub_Settings::get()['homepage_content'] ?? '' ) : '';
	if ( $home_content ) {
		echo '<div class="gh-container">';
		gamehub_content_section( $home_content );
		echo '</div>';
	}
	?>

<?php endif; ?>

<?php get_footer(); ?>
