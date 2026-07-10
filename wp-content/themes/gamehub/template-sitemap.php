<?php
/**
 * Template Name: GameHub Sitemap
 *
 * A human-readable sitemap of every category and its games. Create a Page,
 * set its slug to "sitemap", and assign this template.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<section class="gh-section gh-sitemap">
	<div class="gh-container">
		<div class="gh-section-head"><h2><?php the_title(); ?></h2></div>

		<?php if ( ! class_exists( 'GameHub_Sitemap' ) ) : ?>
			<p class="gh-empty"><?php esc_html_e( 'Activate the GameHub Engine plugin to generate the sitemap.', 'gamehub' ); ?></p>
		<?php else : ?>
			<?php
			$data = GameHub_Sitemap::html_data();
			if ( empty( $data['categories'] ) ) :
				?>
				<p class="gh-empty"><?php esc_html_e( 'No games yet.', 'gamehub' ); ?></p>
			<?php else : ?>
				<p>
					<a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>"><?php esc_html_e( 'XML sitemap for search engines →', 'gamehub' ); ?></a>
				</p>
				<?php foreach ( $data['categories'] as $cat ) : ?>
					<h2><a href="<?php echo esc_url( $cat['url'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></a> <span style="color:var(--gh-muted);font-weight:400;font-size:15px">(<?php echo count( $cat['games'] ); ?>)</span></h2>
					<ul>
						<?php foreach ( $cat['games'] as $g ) : ?>
							<li><a href="<?php echo esc_url( $g['url'] ); ?>"><?php echo esc_html( $g['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
