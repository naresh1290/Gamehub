<?php
/**
 * Static page (About, Contact, Privacy Policy, Terms, etc.). Edited with the
 * Classic Editor, just like a normal WordPress page.
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
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'gh-page' ); ?>>
				<div class="gh-section-head"><h1><?php the_title(); ?></h1></div>
				<div class="gh-content-inner"><?php the_content(); ?></div>
			</article>
			<?php
		endwhile;
		?>
	</div>
</section>
<?php get_footer(); ?>
