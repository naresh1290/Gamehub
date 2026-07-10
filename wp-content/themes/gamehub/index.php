<?php
/**
 * Generic fallback template.
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
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class(); ?> style="max-width:760px;margin:0 auto 40px">
					<h1 style="letter-spacing:-.02em"><?php the_title(); ?></h1>
					<div class="gh-game-desc"><?php the_content(); ?></div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p class="gh-empty"><?php esc_html_e( 'Nothing here yet.', 'gamehub' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
