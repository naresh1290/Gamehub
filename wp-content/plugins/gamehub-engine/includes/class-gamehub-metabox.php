<?php
/**
 * "Game Details" metabox on the game edit screen: source id, iframe URL, icon,
 * primary + other categories, and manual counter overrides.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Metabox {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'boxes' ) );
		add_action( 'save_post_game', array( $this, 'save' ), 10, 2 );
	}

	public function boxes() {
		add_meta_box( 'ghub_game_details', __( 'Game Details', 'gamehub-engine' ), array( $this, 'render' ), 'game', 'normal', 'high' );
		// Categories are managed via the Primary / Other fields below.
		remove_meta_box( 'game_categorydiv', 'game', 'side' );
	}

	private function all_terms() {
		$terms = get_terms( array( 'taxonomy' => 'game_category', 'hide_empty' => false, 'orderby' => 'name' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	public function render( $post ) {
		wp_nonce_field( 'ghub_game_details', 'ghub_game_details_nonce' );

		$source   = get_post_meta( $post->ID, GHUB_META_SOURCE_ID, true );
		$iframe   = get_post_meta( $post->ID, GHUB_META_IFRAME, true );
		$icon     = get_post_meta( $post->ID, GHUB_META_ICON, true );
		$assigned = wp_get_object_terms( $post->ID, 'game_category', array( 'fields' => 'ids' ) );
		$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );
		$primary  = (int) get_post_meta( $post->ID, GHUB_META_PRIMARY_CAT, true );
		if ( ! $primary && $assigned ) {
			$primary = $assigned[0];
		}
		$others = array_values( array_diff( $assigned, array( $primary ) ) );
		$stats  = GameHub_Stats::get( $post->ID );
		$all    = $this->all_terms();
		?>
		<style>
			.ghub-mb .form-table th { width: 180px; }
			.ghub-mb input[type="url"], .ghub-mb input[type="text"], .ghub-mb select { width: 100%; max-width: 640px; }
			.ghub-mb select[multiple] { min-height: 160px; }
			.ghub-counters label { display: inline-block; margin: 0 14px 8px 0; font-weight: 600; }
			.ghub-counters input { width: 110px; display: block; margin-top: 4px; }
		</style>
		<div class="ghub-mb">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ghub_source_id"><?php esc_html_e( 'Game ID', 'gamehub-engine' ); ?></label></th>
					<td>
						<input type="text" id="ghub_source_id" class="code" name="ghub_source_id" value="<?php echo esc_attr( $source ); ?>">
						<p class="description"><?php esc_html_e( 'Stable tracking ID used to match this game on JSON re-sync.', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ghub_iframe_url"><?php esc_html_e( 'Game URL', 'gamehub-engine' ); ?> <span style="color:#d63638">*</span></label></th>
					<td>
						<input type="url" id="ghub_iframe_url" name="ghub_iframe_url" value="<?php echo esc_attr( $iframe ); ?>" placeholder="https://example.com/play/index.html">
						<p class="description"><?php esc_html_e( 'Loaded inside the player iframe.', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ghub_icon_url"><?php esc_html_e( 'Icon URL', 'gamehub-engine' ); ?></label></th>
					<td>
						<input type="text" id="ghub_icon_url" name="ghub_icon_url" value="<?php echo esc_attr( $icon ); ?>" placeholder="https://… or /cdn-cgi/…">
						<p class="description"><?php esc_html_e( 'Thumbnail shown on cards. A path is served through the icon proxy when enabled.', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ghub_primary_category"><?php esc_html_e( 'Primary Category', 'gamehub-engine' ); ?></label></th>
					<td>
						<select id="ghub_primary_category" name="ghub_primary_category">
							<option value="0"><?php esc_html_e( '— none —', 'gamehub-engine' ); ?></option>
							<?php foreach ( $all as $t ) : ?>
								<option value="<?php echo (int) $t->term_id; ?>" <?php selected( $primary, (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'The main category (used for breadcrumbs and the primary listing).', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ghub_other_categories"><?php esc_html_e( 'Other Categories', 'gamehub-engine' ); ?></label></th>
					<td>
						<select id="ghub_other_categories" name="ghub_other_categories[]" multiple>
							<?php foreach ( $all as $t ) : ?>
								<option value="<?php echo (int) $t->term_id; ?>" <?php echo in_array( (int) $t->term_id, $others, true ) ? 'selected' : ''; ?>><?php echo esc_html( $t->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Hold Ctrl / ⌘ to select several. The game is listed on every selected category in addition to the primary.', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Counters', 'gamehub-engine' ); ?></th>
					<td class="ghub-counters">
						<label><?php esc_html_e( 'Gameplays', 'gamehub-engine' ); ?><input type="number" min="0" name="ghub_plays" value="<?php echo (int) $stats['plays']; ?>"></label>
						<label><?php esc_html_e( 'Likes', 'gamehub-engine' ); ?><input type="number" min="0" name="ghub_likes" value="<?php echo (int) $stats['likes']; ?>"></label>
						<label><?php esc_html_e( 'Dislikes', 'gamehub-engine' ); ?><input type="number" min="0" name="ghub_dislikes" value="<?php echo (int) $stats['dislikes']; ?>"></label>
						<label><?php esc_html_e( 'Playtime (seconds)', 'gamehub-engine' ); ?><input type="number" min="0" name="ghub_session_seconds" value="<?php echo (int) $stats['session_seconds']; ?>"></label>
						<p class="description"><?php esc_html_e( 'Override the live counters if needed.', 'gamehub-engine' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['ghub_game_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghub_game_details_nonce'] ) ), 'ghub_game_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, GHUB_META_SOURCE_ID, sanitize_text_field( wp_unslash( $_POST['ghub_source_id'] ?? '' ) ) );
		update_post_meta( $post_id, GHUB_META_IFRAME, esc_url_raw( wp_unslash( $_POST['ghub_iframe_url'] ?? '' ) ) );
		update_post_meta( $post_id, GHUB_META_ICON, esc_url_raw( wp_unslash( $_POST['ghub_icon_url'] ?? '' ) ) );

		$primary = (int) ( $_POST['ghub_primary_category'] ?? 0 );
		$others  = isset( $_POST['ghub_other_categories'] ) ? array_map( 'intval', (array) $_POST['ghub_other_categories'] ) : array();
		$ids     = array_values( array_unique( array_filter( array_merge( array( $primary ), $others ) ) ) );
		wp_set_object_terms( $post_id, $ids, 'game_category', false );
		update_post_meta( $post_id, GHUB_META_PRIMARY_CAT, $primary );

		GameHub_Stats::set_counters(
			$post_id,
			array(
				'plays'           => (int) ( $_POST['ghub_plays'] ?? 0 ),
				'likes'           => (int) ( $_POST['ghub_likes'] ?? 0 ),
				'dislikes'        => (int) ( $_POST['ghub_dislikes'] ?? 0 ),
				'session_seconds' => (int) ( $_POST['ghub_session_seconds'] ?? 0 ),
			)
		);
	}
}
