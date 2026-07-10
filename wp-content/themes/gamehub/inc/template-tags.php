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
 * Render an SEO/content section. Accepts raw HTML (already sanitized).
 *
 * @param string $html    Content HTML.
 * @param string $heading Optional heading.
 */
function gamehub_content_section( $html, $heading = '' ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return;
	}
	echo '<section class="gh-content"><div class="gh-content-inner">';
	if ( $heading ) {
		echo '<h2>' . esc_html( $heading ) . '</h2>';
	}
	echo wpautop( wp_kses_post( $html ) );
	echo '</div></section>';
}

/**
 * The library of category SVG icons (24x24, currentColor).
 *
 * @return array<string,string>
 */
function gamehub_category_icon_set() {
	$o = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
	$f = '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none">';
	return array(
		'gamepad'  => $o . '<line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="15" y1="13" x2="15.01" y2="13"/><line x1="18" y1="11" x2="18.01" y2="11"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>',
		'car'      => $o . '<path d="M19 17h2v-3.3a2 2 0 0 0-.55-1.38L18 10l-1.5-3.5A2 2 0 0 0 14.7 5H9.3a2 2 0 0 0-1.8 1.5L6 10l-2.45 2.32A2 2 0 0 0 3 13.7V17h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
		'bike'     => $o . '<circle cx="6" cy="16" r="3.5"/><circle cx="18" cy="16" r="3.5"/><path d="M6 16l4-6h5l2 6M10 10l1-3h3"/></svg>',
		'target'   => $o . '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/></svg>',
		'trophy'   => $o . '<path d="M6 4h12v5a6 6 0 0 1-12 0V4z"/><path d="M6 5H4a2 2 0 0 0 0 4h1M18 5h2a2 2 0 0 1 0 4h-1"/><path d="M9 15v2a3 3 0 0 1-2 2M15 15v2a3 3 0 0 0 2 2"/><path d="M5 21h14"/></svg>',
		'puzzle'   => $o . '<path d="M9 4.5a2 2 0 0 1 4 0c0 .6.5 1 1 1h2a1 1 0 0 1 1 1v2c0 .5.4 1 1 1a2 2 0 0 1 0 4c-.6 0-1 .4-1 1v3a1 1 0 0 1-1 1h-3c-.5 0-1-.5-1-1a2 2 0 0 0-4 0c0 .5-.5 1-1 1H4a1 1 0 0 1-1-1v-3c0-.6-.4-1-1-1a2 2 0 0 1 0-4c.6 0 1-.5 1-1V6.5a1 1 0 0 1 1-1h2c.5 0 1-.4 1-1z"/></svg>',
		'blocks'   => $o . '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
		'board'    => $o . '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18M3 9h18M3 15h18"/></svg>',
		'card'     => $o . '<rect x="4" y="5" width="11" height="15" rx="2"/><path d="M8.5 4.6 17 3.3a2 2 0 0 1 2.3 1.6l1.4 9"/></svg>',
		'globe'    => $o . '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
		'chef'     => $o . '<path d="M6 14a4 4 0 0 1-1-7.9A5 5 0 0 1 15 5a5 5 0 0 1 3.9 8.1"/><path d="M6 14v5a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-5"/><path d="M6 17h12"/></svg>',
		'heart'    => $f . '<path d="M12 21s-7.5-4.9-10-9.2C.6 9 1.4 5.7 4.2 4.6 6.3 3.8 8.6 4.6 10 6.3l2 2.2 2-2.2c1.4-1.7 3.7-2.5 5.8-1.7 2.8 1.1 3.6 4.4 2.2 7.2C19.5 16.1 12 21 12 21z"/></svg>',
		'star'     => $f . '<path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7z"/></svg>',
		'bulb'     => $o . '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5.9 1 1 1.8v.5h6v-.5c.1-.8.4-1.3 1-1.8A7 7 0 0 0 12 2z"/></svg>',
		'ghost'    => $o . '<path d="M12 2a8 8 0 0 0-8 8v11l3-2 2.5 2 2.5-2 2.5 2 2.5-2 3 2V10a8 8 0 0 0-8-8z"/><path d="M9.5 11h.01M14.5 11h.01"/></svg>',
		'key'      => $o . '<circle cx="7.5" cy="15.5" r="4"/><path d="M10.3 12.7 20 3M16 7l3 3M14 9l2 2"/></svg>',
		'users'    => $o . '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.6a3 3 0 0 1 0 5.6M20.5 20a5 5 0 0 0-4-4.9"/></svg>',
		'cube'     => $o . '<path d="M12 2 3 7v10l9 5 9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/></svg>',
		'hand'     => $o . '<path d="M8 11V5a2 2 0 1 1 4 0v6"/><path d="M12 11V4a2 2 0 1 1 4 0v7"/><path d="M16 12a2 2 0 1 1 4 0v3a6 6 0 0 1-6 6h-1a7 7 0 0 1-5-2l-3.5-3.5a2 2 0 0 1 2.8-2.8L11 14"/></svg>',
		'gear'     => $o . '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
		'compass'  => $o . '<circle cx="12" cy="12" r="9"/><path d="M16 8l-2.5 5.5L8 16l2.5-5.5z"/></svg>',
		'joystick' => $o . '<circle cx="12" cy="7" r="3"/><path d="M12 10v6"/><path d="M7 20a5 5 0 0 1 10 0z"/></svg>',
		'shield'   => $o . '<path d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5z"/></svg>',
		'paw'      => $f . '<circle cx="5.5" cy="12" r="1.8"/><circle cx="18.5" cy="12" r="1.8"/><circle cx="9" cy="7" r="1.8"/><circle cx="15" cy="7" r="1.8"/><path d="M12 12c-2.5 0-4.5 2-4.5 4.2 0 1.6 1.3 2.3 2.5 2.3.9 0 1.3-.4 2-.4s1.1.4 2 .4c1.2 0 2.5-.7 2.5-2.3C16.5 14 14.5 12 12 12z"/></svg>',
		'music'    => $o . '<path d="M9 18V6l10-2v12"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/></svg>',
		'bolt'     => $f . '<path d="M13 2 4 14h6l-1 8 10-12h-6z"/></svg>',
	);
}

/**
 * Pick a relatable SVG icon for a category name (deterministic — same name
 * always yields the same icon; new categories are matched by keyword).
 *
 * @param string $name Category name.
 * @return string Inline SVG markup.
 */
function gamehub_category_icon( $name ) {
	$icons = gamehub_category_icon_set();
	$n     = ' ' . strtolower( wp_strip_all_tags( (string) $name ) ) . ' ';
	$rules = array(
		'car'      => array( 'racing', 'race', 'driv', 'drift', 'traffic', 'speed', 'parking', 'truck', 'car ', 'cars', 'vehicle', 'highway', 'road', 'taxi', 'bus' ),
		'bike'     => array( 'bike', 'motor', 'moto', 'cycle', 'bmx' ),
		'target'   => array( 'shoot', 'gun', 'sniper', ' war', 'battle', 'fps', 'army', 'tank', 'strike', 'combat', 'weapon', 'hunt' ),
		'trophy'   => array( 'sport', 'football', 'soccer', 'basket', 'cricket', 'tennis', 'golf', 'pool', 'baseball', 'box', 'wrestl', 'hockey', 'volleyball', 'rugby', 'goal' ),
		'globe'    => array( '.io', 'io game', ' io ', 'iogame' ),
		'puzzle'   => array( 'puzzle', 'jigsaw', 'hidden', 'mahjong', 'sudoku', 'nonogram' ),
		'blocks'   => array( 'match', 'merge', 'bubble', 'block', '2048', 'candy', 'gem', 'crush', 'tile', 'pop', 'connect' ),
		'board'    => array( 'board', 'chess', 'checker', 'ludo', 'domino', 'backgammon' ),
		'card'     => array( 'card', 'solitaire', 'poker', 'uno', 'blackjack', 'klondike', 'freecell' ),
		'chef'     => array( 'cook', 'food', 'chef', 'restaurant', 'cake', 'pizza', 'burger', 'kitchen', 'bake', 'ice cream' ),
		'heart'    => array( 'girl', 'dress', 'makeup', 'make up', 'fashion', 'beauty', 'salon', 'princess', 'wedding', 'love', 'style' ),
		'star'     => array( 'kid', 'baby', 'color', 'draw', 'paint', 'toddler', 'preschool' ),
		'bulb'     => array( 'brain', 'quiz', 'trivia', 'word', 'mind', 'thinky', 'math', 'memory', 'logic', 'iq', 'riddle', 'crossword', 'educat', 'learn' ),
		'ghost'    => array( 'horror', 'scary', 'zombie', 'ghost', 'monster', 'evil', 'creepy', 'nightmare', 'fear', 'dark' ),
		'key'      => array( 'escape', ' room', 'prison', 'breakout' ),
		'users'    => array( 'multiplayer', '2 player', '2player', 'two player', 'players', 'co-op', 'coop', 'friend', ' vs ' ),
		'cube'     => array( 'roblox', 'minecraft', 'craft', 'sandbox', 'pixel', 'voxel' ),
		'hand'     => array( 'clicker', 'idle', ' tap', 'incremental' ),
		'gear'     => array( 'simulat', ' sim', 'farm', 'city', 'build', 'manage', 'tycoon' ),
		'compass'  => array( 'adventure', 'quest', 'explore', 'story', 'rpg', 'journey' ),
		'joystick' => array( 'arcade', 'classic', 'retro', 'runner', 'platform', 'jump', ' fly', 'endless', 'skill' ),
		'shield'   => array( 'strategy', 'tower', 'defen', 'defence' ),
		'paw'      => array( 'animal', ' pet', 'dino', 'fish', 'horse', ' dog', ' cat', 'zoo' ),
		'music'    => array( 'music', 'piano', 'dance', 'rhythm', 'beat', 'song', 'guitar', 'drum' ),
		'bolt'     => array( 'action', 'fight', 'stickman', 'hero', 'ninja', 'sword', 'rush', 'smash', 'clash' ),
	);
	foreach ( $rules as $key => $keywords ) {
		foreach ( $keywords as $kw ) {
			if ( false !== strpos( $n, $kw ) ) {
				return $icons[ $key ];
			}
		}
	}
	return $icons['gamepad'];
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
	// Most-popular categories first (by total plays), when the engine is active.
	if ( function_exists( 'ghub_categories_by_popularity' ) ) {
		return ghub_categories_by_popularity();
	}
	$terms = get_terms( array( 'taxonomy' => 'game_category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}
