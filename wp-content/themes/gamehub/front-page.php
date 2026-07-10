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

$tagline = '';
if ( class_exists( 'GameHub_Settings' ) ) {
	$tagline = GameHub_Settings::get()['site_tagline'] ?? '';
}
if ( '' === $tagline ) {
	$tagline = get_bloginfo( 'description' );
}
?>

<section class="gh-hero">
	<div class="gh-container">
		<h1><?php bloginfo( 'name' ); ?></h1>
		<?php if ( $tagline ) : ?>
			<p><?php echo esc_html( $tagline ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php // Recently played — populated client-side from localStorage. ?>
<section class="gh-section" data-gh-recent hidden>
	<div class="gh-container">
		<div class="gh-section-head"><h2><?php esc_html_e( 'Recently played', 'gamehub' ); ?></h2></div>
		<div class="gh-grid" data-gh-recent-grid></div>
	</div>
</section>

<?php if ( ! gamehub_engine_active() ) : ?>
	<div class="gh-container"><p class="gh-empty"><?php esc_html_e( 'Activate the GameHub Engine plugin and import games to get started.', 'gamehub' ); ?></p></div>
<?php else : ?>

	<?php
	// Popular strip: most-played across the whole catalog, via the stats table.
	global $wpdb;
	$stats_table = $wpdb->prefix . 'gh_stats';
	$popular_ids = $wpdb->get_col( "SELECT s.post_id FROM $stats_table s INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id AND p.post_status = 'publish' AND p.post_type = 'game' ORDER BY s.plays DESC LIMIT 12" );
	if ( $popular_ids ) :
		?>
		<section class="gh-section">
			<div class="gh-container">
				<div class="gh-section-head">
					<h2><?php esc_html_e( 'Popular games', 'gamehub' ); ?></h2>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>"><?php esc_html_e( 'View all', 'gamehub' ); ?> →</a>
				</div>
				<?php gamehub_grid( array_map( 'get_post', $popular_ids ) ); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// A row per category (top categories first), each with up to 12 games.
	$cats = gamehub_categories();
	foreach ( array_slice( $cats, 0, 12 ) as $cat ) :
		$q = new WP_Query(
			array(
				'post_type'      => 'game',
				'posts_per_page' => 12,
				'no_found_rows'  => true,
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
