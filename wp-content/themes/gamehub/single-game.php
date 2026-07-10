<?php
/**
 * Single game: player, actions (like/dislike/rating), related games.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$game = gamehub_engine_active() ? ghub_get_game( get_post() ) : null;

	if ( ! $game || '' === $game['iframe_url'] ) {
		echo '<div class="gh-container"><p class="gh-empty">' . esc_html__( 'This game is unavailable.', 'gamehub' ) . '</p></div>';
		get_footer();
		return;
	}

	$cats = get_the_terms( get_the_ID(), 'game_category' );
	$cats = is_wp_error( $cats ) ? array() : (array) $cats;
	?>
	<div class="gh-player-wrap">
		<div class="gh-container">
			<div class="gh-game-head">
				<?php if ( $game['icon'] ) : ?>
					<img src="<?php echo esc_url( $game['icon'] ); ?>" alt="<?php echo esc_attr( $game['name'] ); ?>" width="68" height="68">
				<?php endif; ?>
				<div>
					<h1><?php echo esc_html( $game['name'] ); ?></h1>
					<?php if ( $cats ) : ?>
						<div class="gh-game-cats">
							<?php foreach ( $cats as $cat ) : ?>
								<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="gh-player-grid no-side">
				<div>
					<div class="gh-player"
						data-game-id="<?php echo (int) $game['id']; ?>"
						data-iframe="<?php echo esc_url( $game['iframe_url'] ); ?>"
						data-title="<?php echo esc_attr( $game['name'] ); ?>">
						<div class="gh-player-cover" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Play', 'gamehub' ); ?>"
							<?php if ( $game['icon'] ) : ?>style="background-image:url('<?php echo esc_url( $game['icon'] ); ?>')"<?php endif; ?>>
							<button type="button" class="gh-play-btn">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
								<?php esc_html_e( 'Play now', 'gamehub' ); ?>
							</button>
						</div>
					</div>

					<div class="gh-actions" data-game-id="<?php echo (int) $game['id']; ?>">
						<button type="button" class="gh-btn gh-like" data-action="like">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10v11M2 12v7a2 2 0 0 0 2 2h13.6a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3H14l1-4.5A2.5 2.5 0 0 0 12.5 2L7 10z"/></svg>
							<span class="gh-like-count"><?php echo esc_html( gamehub_short_num( $game['likes'] ) ); ?></span>
						</button>
						<button type="button" class="gh-btn gh-dislike" data-action="dislike">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 14V3M22 12V5a2 2 0 0 0-2-2H6.4a2 2 0 0 0-2 1.7l-1.4 9A2 2 0 0 0 5 16h6l-1 4.5A2.5 2.5 0 0 0 12.5 22L17 14z"/></svg>
							<span class="gh-dislike-count"><?php echo esc_html( gamehub_short_num( $game['dislikes'] ) ); ?></span>
						</button>

						<span class="gh-rating" data-rating="<?php echo esc_attr( $game['rating'] ); ?>">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<button type="button" data-value="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: star value */ __( 'Rate %d', 'gamehub' ), $i ) ); ?>">★</button>
							<?php endfor; ?>
							<span class="gh-rating-note">
								<?php if ( $game['rating'] > 0 ) : ?>
									<?php echo esc_html( number_format_i18n( $game['rating'], 1 ) ); ?> (<?php echo esc_html( gamehub_short_num( $game['rating_count'] ) ); ?>)
								<?php endif; ?>
							</span>
						</span>

						<?php if ( $game['plays'] > 0 ) : ?>
							<span class="gh-rating-note"><?php echo esc_html( gamehub_short_num( $game['plays'] ) ); ?> <?php esc_html_e( 'plays', 'gamehub' ); ?></span>
						<?php endif; ?>
					</div>

					<?php
					$content = get_the_content();
					if ( trim( wp_strip_all_tags( $content ) ) ) :
						?>
						<div class="gh-game-desc"><?php the_content(); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<?php
			// Related games from the same categories.
			if ( $cats ) :
				$term_ids = wp_list_pluck( $cats, 'term_id' );
				$related  = new WP_Query(
					array(
						'post_type'      => 'game',
						'posts_per_page' => 12,
						'post__not_in'   => array( get_the_ID() ),
						'orderby'        => 'rand',
						'no_found_rows'  => true,
						'tax_query'      => array(
							array( 'taxonomy' => 'game_category', 'field' => 'term_id', 'terms' => $term_ids ),
						),
					)
				);
				if ( $related->have_posts() ) :
					?>
					<section class="gh-section">
						<div class="gh-section-head"><h2><?php esc_html_e( 'More like this', 'gamehub' ); ?></h2></div>
						<?php gamehub_grid( $related ); ?>
					</section>
					<?php
					wp_reset_postdata();
				endif;
			endif;
			?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
