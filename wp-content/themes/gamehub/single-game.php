<?php
/**
 * Single game: auto-running player surrounded by one continuous list of 30
 * related games (same category prioritized, then filled), with a content box
 * below. Tiles flow densely around and beneath the frame — no separate
 * sections, no blank space.
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

	// One list of 30 related games — same category prioritized, then filled.
	$exclude = array( get_the_ID() );
	$around  = array();
	if ( $cats ) {
		$around  = get_posts(
			array(
				'post_type'      => 'game',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'post__not_in'   => $exclude,
				'ghub_order'     => 'popular',
				'no_found_rows'  => true,
				'tax_query'      => array(
					array( 'taxonomy' => 'game_category', 'field' => 'term_id', 'terms' => wp_list_pluck( $cats, 'term_id' ) ),
				),
			)
		);
		$exclude = array_merge( $exclude, wp_list_pluck( $around, 'ID' ) );
	}
	if ( count( $around ) < 30 ) {
		$around = array_merge(
			$around,
			get_posts(
				array(
					'post_type'      => 'game',
					'post_status'    => 'publish',
					'posts_per_page' => 30 - count( $around ),
					'post__not_in'   => $exclude,
					'ghub_order'     => 'popular',
					'no_found_rows'  => true,
				)
			)
		);
	}
	$around = array_slice( $around, 0, 30 );
	?>
	<div class="gh-player-wrap">
		<div class="gh-container">

			<div class="gh-play-top">
				<div class="gh-play-center">
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
							<div class="gh-player-launch" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Play', 'gamehub' ); ?>"
								<?php if ( $game['icon'] ) : ?>style="background-image:url('<?php echo esc_url( $game['icon'] ); ?>')"<?php endif; ?>>
								<?php if ( $game['icon'] ) : ?>
									<img class="gh-launch-icon" src="<?php echo esc_url( $game['icon'] ); ?>" alt="<?php echo esc_attr( $game['name'] ); ?>">
								<?php endif; ?>
								<span class="gh-play-btn">
									<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
								</span>
							</div>
						</div>

						<div class="gh-player-bar">
							<div class="gh-pb-left">
								<?php if ( $game['icon'] ) : ?><img src="<?php echo esc_url( $game['icon'] ); ?>" alt="" width="30" height="30"><?php endif; ?>
								<h1 class="gh-pb-name"><?php echo esc_html( $game['name'] ); ?></h1>
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
				</div>

				<?php // Mobile-only actions (phones auto-play fullscreen, so no fullscreen button here). ?>
				<div class="gh-mobile-actions gh-actions" data-game-id="<?php echo (int) $game['id']; ?>">
					<button type="button" class="gh-mb-btn gh-like" data-action="like">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10v11M2 12v7a2 2 0 0 0 2 2h13.6a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3H14l1-4.5A2.5 2.5 0 0 0 12.5 2L7 10z"/></svg>
						<span class="gh-like-count"><?php echo esc_html( gamehub_short_num( $game['likes'] ) ); ?></span>
					</button>
					<button type="button" class="gh-mb-btn gh-dislike" data-action="dislike">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 14V3M22 12V5a2 2 0 0 0-2-2H6.4a2 2 0 0 0-2 1.7l-1.4 9A2 2 0 0 0 5 16h6l-1 4.5A2.5 2.5 0 0 0 12.5 22L17 14z"/></svg>
						<span class="gh-dislike-count"><?php echo esc_html( gamehub_short_num( $game['dislikes'] ) ); ?></span>
					</button>
					<button type="button" class="gh-mb-btn gh-share" data-gh-share>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
						<span><?php esc_html_e( 'Share', 'gamehub' ); ?></span>
					</button>
				</div>

				<?php foreach ( $around as $rg ) { gamehub_card( $rg ); } ?>
			</div>

			<?php
			// Content box below the game grid.
			$content = get_the_content();
			if ( trim( wp_strip_all_tags( $content ) ) ) :
				?>
				<section class="gh-content"><div class="gh-content-inner gh-game-desc"><?php the_content(); ?></div></section>
			<?php endif; ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
