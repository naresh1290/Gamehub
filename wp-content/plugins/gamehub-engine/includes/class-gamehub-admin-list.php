<?php
/**
 * Enabled + Popular toggle columns on the All Games list table.
 *
 * Enabled  → post status (publish/draft).
 * Popular  → `_ghub_popular` meta; popular games sort first everywhere.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Admin_List {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'manage_game_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_game_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_head-edit.php', array( $this, 'assets' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'script' ) );
		add_action( 'wp_ajax_ghub_toggle', array( $this, 'ajax_toggle' ) );
	}

	private function is_game_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'edit-game' === $screen->id;
	}

	public function columns( $cols ) {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['ghub_enabled'] = __( 'Enabled', 'gamehub-engine' );
				$out['ghub_popular'] = __( 'Popular', 'gamehub-engine' );
			}
		}
		return $out;
	}

	public function column( $col, $post_id ) {
		if ( 'ghub_enabled' === $col ) {
			$on = ( 'publish' === get_post_status( $post_id ) );
			$this->toggle( $post_id, 'enabled', $on, '' );
		} elseif ( 'ghub_popular' === $col ) {
			$on = ( 1 === (int) get_post_meta( $post_id, GHUB_META_POPULAR, true ) );
			$this->toggle( $post_id, 'popular', $on, ' ghub-pop' );
		}
	}

	private function toggle( $post_id, $field, $on, $extra ) {
		printf(
			'<label class="ghub-switch%s"><input type="checkbox" class="ghub-toggle" data-id="%d" data-field="%s" %s><span></span></label>',
			esc_attr( $extra ),
			(int) $post_id,
			esc_attr( $field ),
			checked( $on, true, false )
		);
	}

	public function assets() {
		if ( ! $this->is_game_screen() ) {
			return;
		}
		?>
		<style>
			.column-ghub_enabled, .column-ghub_popular { width: 90px; }
			.ghub-switch { position: relative; display: inline-block; width: 40px; height: 22px; }
			.ghub-switch input { opacity: 0; width: 0; height: 0; }
			.ghub-switch span { position: absolute; inset: 0; cursor: pointer; background: #c3c4c7; border-radius: 999px; transition: .15s; }
			.ghub-switch span:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .15s; }
			.ghub-switch input:checked + span { background: #2271b1; }
			.ghub-switch.ghub-pop input:checked + span { background: #d63638; }
			.ghub-switch input:checked + span:before { transform: translateX(18px); }
			.ghub-switch input:disabled + span { opacity: .5; }
		</style>
		<?php
	}

	public function script() {
		if ( ! $this->is_game_screen() ) {
			return;
		}
		$nonce = wp_create_nonce( 'ghub_toggle' );
		?>
		<script>
		(function () {
			var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
			document.addEventListener('change', function (e) {
				var t = e.target;
				if (!t.classList || !t.classList.contains('ghub-toggle')) { return; }
				var body = new URLSearchParams();
				body.set('action', 'ghub_toggle');
				body.set('nonce', NONCE);
				body.set('id', t.getAttribute('data-id'));
				body.set('field', t.getAttribute('data-field'));
				body.set('value', t.checked ? '1' : '0');
				t.disabled = true;
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (res) { t.disabled = false; if (!res || !res.success) { t.checked = !t.checked; } })
					.catch(function () { t.disabled = false; t.checked = !t.checked; });
			});
		})();
		</script>
		<?php
	}

	public function ajax_toggle() {
		check_ajax_referer( 'ghub_toggle', 'nonce' );
		$post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$field   = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value   = ! empty( $_POST['value'] ) ? 1 : 0;

		if ( ! $post_id || 'game' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		if ( 'enabled' === $field ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => $value ? 'publish' : 'draft' ) );
		} elseif ( 'popular' === $field ) {
			update_post_meta( $post_id, GHUB_META_POPULAR, $value );
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
			do_action( 'rt_nginx_helper_purge_all' );
		} else {
			wp_send_json_error();
		}

		wp_send_json_success( array( 'value' => $value ) );
	}
}
