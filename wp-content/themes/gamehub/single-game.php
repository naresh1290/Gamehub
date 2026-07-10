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
	$cats = ( is_wp_error( $cats ) || ! $cats ) ? array() : array_filter(
		(array) $cats,
		static function ( $t ) {
			return $t instanceof WP_Term;
		}
	);
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
							<?php
							foreach ( $cats as $cat ) :
								$cat_link = get_term_link( $cat );
								if ( is_wp_error( $cat_link ) ) {
									continue;
								}
								?>
								<a href="<?php echo esc_url( $cat_link ); ?>"><?php echo esc_html( $cat->name ); ?></a>
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
						data-title="<?php echo esc_attr( $game['name'] ); ?>"
						data-icon="<?php echo esc_url( $game['icon'] ); ?>">

						<div class="gh-fs-bar">
							<button type="button" class="gh-fs-close" data-gh-fs-exit aria-label="<?php esc_attr_e( 'Close', 'gamehub' ); ?>">✕</button>
							<span class="gh-fs-title"><?php echo esc_html( $game['name'] ); ?></span>
						</div>

						<div class="gh-player-stage">
							<?php // Mobile launch tile (icon + play → immersive). ?>
							<div class="gh-player-launch" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Play', 'gamehub' ); ?>"
								<?php if ( $game['icon'] ) : ?>style="background-image:url('<?php echo esc_url( $game['icon'] ); ?>')"<?php endif; ?>>
								<span class="gh-play-btn">
									<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
								</span>
							</div>
						</div>

						<div class="gh-player-bar">
							<div class="gh-pb-left">
								<?php if ( $game['icon'] ) : ?><img src="<?php echo esc_url( $game['icon'] ); ?>" alt="" width="30" height="30"><?php endif; ?>
								<span class="gh-pb-name"><?php echo esc_html( $game['name'] ); ?></span>
							</div>
							<div class="gh-pb-right gh-actions" data-game-id="<?php echo (int) $game['id']; ?>">
								<button type="button" class="gh-pb-btn gh-like" data-action="like" aria-label="<?php esc_attr_e( 'Like', 'gamehub' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10v11M2 12v7a2 2 0 0 0 2 2h13.6a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3H14l1-4.5A2.5 2.5 0 0 0 12.5 2L7 10z"/></svg>
									<span class="gh-like-count"><?php echo esc_html( gamehub_short_num( $game['likes'] ) ); ?></span>
								</button>
								<button type="button" class="gh-pb-btn gh-dislike" data-action="dislike" aria-label="<?php esc_attr_e( 'Dislike', 'gamehub' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 14V3M22 12V5a2 2 0 0 0-2-2H6.4a2 2 0 0 0-2 1.7l-1.4 9A2 2 0 0 0 5 16h6l-1 4.5A2.5 2.5 0 0 0 12.5 22L17 14z"/></svg>
									<span class="gh-dislike-count"><?php echo esc_html( gamehub_short_num( $game['dislikes'] ) ); ?></span>
								</button>
								<button type="button" class="gh-pb-btn gh-share" data-gh-share aria-label="<?php esc_attr_e( 'Share', 'gamehub' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
								</button>
								<button type="button" class="gh-pb-btn gh-fs" data-gh-fs-enter aria-label="<?php esc_attr_e( 'Fullscreen', 'gamehub' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
								</button>
							</div>
						</div>
					</div>

					<div class="gh-player-meta">
						<span class="gh-rating-static" title="<?php echo esc_attr( sprintf( /* translators: like percentage */ __( '%d%% liked', 'gamehub' ), $game['like_ratio'] ) ); ?>">
							<span class="gh-rating-value"><?php echo $game['rating_count'] > 0 ? '★ ' . esc_html( number_format_i18n( $game['rating'], 1 ) ) : ''; ?></span>
							<span class="gh-rating-note"><?php if ( $game['rating_count'] > 0 ) : ?>(<span class="gh-vote-total"><?php echo esc_html( gamehub_short_num( $game['rating_count'] ) ); ?></span> <?php esc_html_e( 'votes', 'gamehub' ); ?>)<?php endif; ?></span>
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
