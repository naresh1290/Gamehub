<?php
/**
 * Presentation helpers.
 *
 * @package GameHub\Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a single game card. Accepts a WP_Post/ID.
 *
 * @param int|WP_Post $post Game.
 */
function gamehub_card( $post ) {
	$game = gamehub_engine_active() ? ghub_get_game( $post ) : null;
	if ( ! $game ) {
		return;
	}
	$rating = $game['rating'] > 0 ? number_format_i18n( $game['rating'], 1 ) : null;
	?>
	<a class="gh-card" href="<?php echo esc_url( $game['permalink'] ); ?>" data-game-id="<?php echo (int) $game['id']; ?>">
		<div class="gh-card-thumb">
			<?php if ( $game['icon'] ) : ?>
				<img src="<?php echo esc_url( $game['icon'] ); ?>" alt="<?php echo esc_attr( $game['name'] ); ?>" loading="lazy" width="300" height="300">
			<?php endif; ?>
		</div>
		<div class="gh-card-body">
			<p class="gh-card-title"><?php echo esc_html( $game['name'] ); ?></p>
			<div class="gh-card-meta">
				<?php if ( $rating ) : ?>
					<span class="gh-star">★ <?php echo esc_html( $rating ); ?></span>
				<?php endif; ?>
				<?php if ( $game['plays'] > 0 ) : ?>
					<span><?php echo esc_html( gamehub_short_num( $game['plays'] ) ); ?> <?php esc_html_e( 'plays', 'gamehub' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</a>
	<?php
}

/**
 * Render a grid of games from a WP_Query or an array of posts/IDs.
 *
 * @param WP_Query|array $items Games.
 */
function gamehub_grid( $items ) {
	$posts = $items instanceof WP_Query ? $items->posts : (array) $items;
	if ( empty( $posts ) ) {
		echo '<p class="gh-empty">' . esc_html__( 'No games here yet.', 'gamehub' ) . '</p>';
		return;
	}
	echo '<div class="gh-grid">';
	foreach ( $posts as $p ) {
		gamehub_card( $p );
	}
	echo '</div>';
}

/**
 * Compact number formatting (1.2K, 3.4M).
 *
 * @param int $n Number.
 * @return string
 */
function gamehub_short_num( $n ) {
	$n = (int) $n;
	if ( $n >= 1000000 ) {
		return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
	}
	if ( $n >= 1000 ) {
		return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
	}
	return (string) $n;
}

/**
 * The list of game categories for the chip bar / nav.
 *
 * @return WP_Term[]
 */
function gamehub_categories() {
	if ( ! taxonomy_exists( 'game_category' ) ) {
		return array();
	}
	$terms = get_terms( array( 'taxonomy' => 'game_category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}
