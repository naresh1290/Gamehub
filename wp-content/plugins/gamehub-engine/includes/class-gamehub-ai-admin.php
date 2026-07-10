<?php
/**
 * Admin UI for AI content generation: a "Generate Content" page with resumable
 * bulk generation (progress, skip-generated, error handling), the homepage
 * Generate button, and AJAX endpoints.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_AI_Admin {

	const SLUG = 'gamehub-ai';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_ghub_ai_pending', array( $this, 'ajax_pending' ) );
		add_action( 'wp_ajax_ghub_ai_generate_one', array( $this, 'ajax_generate_one' ) );
		add_action( 'wp_ajax_ghub_ai_generate_home', array( $this, 'ajax_generate_home' ) );
		add_action( 'wp_ajax_ghub_ai_reset', array( $this, 'ajax_reset' ) );
		// Quick links from the list screens.
		add_filter( 'views_edit-game', array( $this, 'list_link' ) );
		add_filter( 'views_edit-game_category', array( $this, 'list_link' ) );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=game',
			__( 'AI Content', 'gamehub-engine' ),
			__( 'AI Content', 'gamehub-engine' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function list_link( $views ) {
		$views['ghub_ai'] = '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=game&page=' . self::SLUG ) ) . '">✨ ' . esc_html__( 'Generate AI content', 'gamehub-engine' ) . '</a>';
		return $views;
	}

	private function on_our_screens( $hook ) {
		if ( false !== strpos( (string) $hook, self::SLUG ) ) {
			return true;
		}
		// Settings page (for the homepage Generate button).
		return false !== strpos( (string) $hook, 'gamehub-settings' );
	}

	public function assets( $hook ) {
		if ( ! $this->on_our_screens( $hook ) ) {
			return;
		}
		wp_register_script( 'ghub-ai', false, array(), GHUB_ENGINE_VERSION, true );
		wp_enqueue_script( 'ghub-ai' );
		wp_localize_script( 'ghub-ai', 'GHUB_AI', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ghub_ai' ) ) );
		wp_add_inline_script( 'ghub-ai', $this->js() );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'gamehub-engine' ) );
		}
		$ready       = GameHub_AI::ready();
		$games_total = (int) wp_count_posts( 'game' )->publish;
		$games_pend  = count( GameHub_AI::pending_games() );
		$cats_total  = (int) wp_count_terms( array( 'taxonomy' => 'game_category', 'hide_empty' => false ) );
		$cats_pend   = count( GameHub_AI::pending_categories() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Content Generator', 'gamehub-engine' ); ?></h1>

			<?php if ( ! $ready ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: settings link */
						esc_html__( 'Set your OpenAI API key first in %s.', 'gamehub-engine' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=game&page=gamehub-settings' ) ) . '">' . esc_html__( 'Games → Settings', 'gamehub-engine' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<style>
				.ghub-ai-card { background:#fff; border:1px solid #dcdcde; border-radius:10px; padding:16px 18px; margin:0 0 18px; max-width:820px; }
				.ghub-ai-card h2 { margin-top:0; }
				.ghub-bar { height:10px; background:#f0f0f1; border-radius:6px; overflow:hidden; margin:12px 0; }
				.ghub-bar-fill { height:100%; width:0; background:#2271b1; transition:width .2s; }
				.ghub-gen-status { font-weight:600; }
				.ghub-gen-log { white-space:pre-wrap; max-height:160px; overflow:auto; background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:8px; margin-top:8px; font-family:monospace; font-size:12px; color:#b32d2e; }
				.ghub-gen-log:empty { display:none; }
			</style>

			<div class="ghub-ai-card">
				<h2><?php esc_html_e( 'Homepage', 'gamehub-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Generates the homepage content block and saves it to Settings.', 'gamehub-engine' ); ?></p>
				<p>
					<button type="button" class="button button-primary" data-ghub-generate-home <?php disabled( ! $ready ); ?>>✨ <?php esc_html_e( 'Generate homepage content', 'gamehub-engine' ); ?></button>
					<span class="ghub-gen-status" id="ghub-home-status" style="margin-left:8px;color:#646970"></span>
				</p>
			</div>

			<div class="ghub-ai-card" id="ghub-bulk-games">
				<h2><?php esc_html_e( 'Games', 'gamehub-engine' ); ?></h2>
				<p class="description"><?php printf( esc_html__( '%1$d of %2$d games still need content. Already-generated games are skipped; you can stop and resume anytime.', 'gamehub-engine' ), (int) $games_pend, (int) $games_total ); ?></p>
				<p>
					<button type="button" class="button button-primary" data-ghub-bulk="games" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Generate all pending', 'gamehub-engine' ); ?></button>
					<button type="button" class="button" data-ghub-stop="games" style="display:none"><?php esc_html_e( 'Stop', 'gamehub-engine' ); ?></button>
					<a href="#" class="button-link" data-ghub-reset="games" style="margin-left:10px;color:#b32d2e"><?php esc_html_e( 'Reset (allow regenerate)', 'gamehub-engine' ); ?></a>
				</p>
				<div class="ghub-bar"><div class="ghub-bar-fill"></div></div>
				<div class="ghub-gen-status"></div>
				<div class="ghub-gen-log"></div>
			</div>

			<div class="ghub-ai-card" id="ghub-bulk-categories">
				<h2><?php esc_html_e( 'Categories', 'gamehub-engine' ); ?></h2>
				<p class="description"><?php printf( esc_html__( '%1$d of %2$d categories still need content.', 'gamehub-engine' ), (int) $cats_pend, (int) $cats_total ); ?></p>
				<p>
					<button type="button" class="button button-primary" data-ghub-bulk="categories" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Generate all pending', 'gamehub-engine' ); ?></button>
					<button type="button" class="button" data-ghub-stop="categories" style="display:none"><?php esc_html_e( 'Stop', 'gamehub-engine' ); ?></button>
					<a href="#" class="button-link" data-ghub-reset="categories" style="margin-left:10px;color:#b32d2e"><?php esc_html_e( 'Reset (allow regenerate)', 'gamehub-engine' ); ?></a>
				</p>
				<div class="ghub-bar"><div class="ghub-bar-fill"></div></div>
				<div class="ghub-gen-status"></div>
				<div class="ghub-gen-log"></div>
			</div>
		</div>
		<?php
	}

	private function js() {
		return <<<'JS'
(function () {
	var AI = window.GHUB_AI || {};
	function post(data) {
		var b = new URLSearchParams();
		b.set('nonce', AI.nonce);
		for (var k in data) { b.set(k, data[k]); }
		return fetch(AI.ajax, { method: 'POST', credentials: 'same-origin', body: b }).then(function (r) { return r.json(); });
	}
	function setEditor(id, html) {
		if (window.tinymce && tinymce.get(id) && !tinymce.get(id).isHidden()) { tinymce.get(id).setContent(html); }
		var ta = document.getElementById(id);
		if (ta) { ta.value = html; }
	}
	var STOP = {};
	document.addEventListener('click', function (e) {
		var hb = e.target.closest('[data-ghub-generate-home]');
		if (hb) { e.preventDefault(); genHome(hb); return; }
		var gb = e.target.closest('[data-ghub-bulk]');
		if (gb) { e.preventDefault(); startBulk(gb.getAttribute('data-ghub-bulk')); return; }
		var sb = e.target.closest('[data-ghub-stop]');
		if (sb) { e.preventDefault(); STOP[sb.getAttribute('data-ghub-stop')] = true; return; }
		var rb = e.target.closest('[data-ghub-reset]');
		if (rb) { e.preventDefault(); resetType(rb.getAttribute('data-ghub-reset')); return; }
	});
	function genHome(btn) {
		var st = document.getElementById('ghub-home-status');
		btn.disabled = true;
		if (st) { st.textContent = 'Generating…'; }
		post({ action: 'ghub_ai_generate_home' }).then(function (res) {
			btn.disabled = false;
			if (res.success) { if (st) { st.textContent = 'Done — inserted below. Review & Save.'; } setEditor('ghub_home_content', res.data.content); }
			else { if (st) { st.textContent = 'Error: ' + (res.data && res.data.error || 'failed'); } }
		}).catch(function () { btn.disabled = false; if (st) { st.textContent = 'Request failed.'; } });
	}
	function resetType(type) {
		if (!confirm('Clear the generated flags for all ' + type + '? This lets you regenerate them.')) { return; }
		post({ action: 'ghub_ai_reset', type: type }).then(function () { location.reload(); });
	}
	function startBulk(type) {
		var box = document.getElementById('ghub-bulk-' + type);
		if (!box) { return; }
		var status = box.querySelector('.ghub-gen-status');
		var bar = box.querySelector('.ghub-bar-fill');
		var log = box.querySelector('.ghub-gen-log');
		var startBtn = box.querySelector('[data-ghub-bulk]');
		var stopBtn = box.querySelector('[data-ghub-stop]');
		STOP[type] = false; startBtn.disabled = true; if (stopBtn) { stopBtn.style.display = ''; }
		if (log) { log.textContent = ''; }
		status.textContent = 'Fetching list…';
		post({ action: 'ghub_ai_pending', type: type }).then(function (res) {
			if (!res.success) { status.textContent = 'Error: ' + (res.data && res.data.error || 'failed'); startBtn.disabled = false; return; }
			var ids = res.data.ids || [], total = ids.length, i = 0, ok = 0, fail = 0, skip = 0;
			if (!total) { status.textContent = 'Nothing pending — all generated.'; startBtn.disabled = false; if (stopBtn) { stopBtn.style.display = 'none'; } return; }
			function done() {
				status.textContent = (STOP[type] ? 'Stopped. ' : 'Done. ') + ok + ' generated, ' + skip + ' skipped, ' + fail + ' failed of ' + total + '.';
				if (bar) { bar.style.width = '100%'; }
				startBtn.disabled = false; if (stopBtn) { stopBtn.style.display = 'none'; }
			}
			function step() {
				if (STOP[type] || i >= total) { done(); return; }
				status.textContent = 'completed (' + i + '/' + total + ')';
				if (bar) { bar.style.width = (i / total * 100) + '%'; }
				post({ action: 'ghub_ai_generate_one', type: type, id: ids[i] }).then(function (r) {
					if (r.success) { if (r.data.status === 'generated') { ok++; } else { skip++; } }
					else { fail++; if (log) { log.textContent += 'ID ' + ids[i] + ': ' + (r.data && r.data.error || 'failed') + '\n'; } }
					i++; step();
				}).catch(function () { fail++; if (log) { log.textContent += 'ID ' + ids[i] + ': request failed\n'; } i++; step(); });
			}
			step();
		});
	}
})();
JS;
	}

	/* ---- AJAX ------------------------------------------------------------ */

	private function guard() {
		check_ajax_referer( 'ghub_ai', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'permission denied' ) );
		}
	}

	public function ajax_pending() {
		$this->guard();
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$ids  = ( 'categories' === $type ) ? GameHub_AI::pending_categories() : ( ( 'games' === $type ) ? GameHub_AI::pending_games() : array() );
		wp_send_json_success( array( 'ids' => array_map( 'intval', $ids ) ) );
	}

	public function ajax_generate_one() {
		$this->guard();
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$id   = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'error' => 'bad id' ) );
		}
		$r = ( 'categories' === $type ) ? GameHub_AI::generate_category( $id ) : GameHub_AI::generate_game( $id );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'error' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'status' => $r ) );
	}

	public function ajax_generate_home() {
		$this->guard();
		$r = GameHub_AI::generate_homepage();
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'error' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'content' => $r ) );
	}

	public function ajax_reset() {
		$this->guard();
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		global $wpdb;
		if ( 'games' === $type ) {
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => GameHub_AI::META_GENERATED ) );
		} elseif ( 'categories' === $type ) {
			$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => GameHub_AI::META_GENERATED ) );
		}
		wp_send_json_success();
	}
}
