<?php
/**
 * "Add Games" bulk importer screen — quickly add all games from a JSON URL,
 * an uploaded file, or pasted JSON. Lives under the Games menu.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Import_Page {

	const SLUG = 'gamehub-import';

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
		// Prominent "Add Games" button above the games list table.
		add_filter( 'views_edit-game', array( $this, 'list_button' ) );
	}

	public function menu() {
		$page = add_submenu_page(
			'edit.php?post_type=game',
			__( 'Add Games', 'gamehub-engine' ),
			__( 'Add Games', 'gamehub-engine' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
		// Highlight the "Add Games" submenu even though it renders via callback.
		unset( $page );
	}

	public function assets( $hook ) {
		if ( false !== strpos( (string) $hook, self::SLUG ) ) {
			wp_enqueue_style( 'ghub-engine-admin', GHUB_ENGINE_URL . 'assets/admin.css', array(), GHUB_ENGINE_VERSION );
		}
	}

	/**
	 * Add an "Add Games" button next to the list-table view links.
	 */
	public function list_button( $views ) {
		$url  = admin_url( 'edit.php?post_type=game&page=' . self::SLUG );
		$html = '<a href="' . esc_url( $url ) . '" class="button button-primary" style="margin:2px 0 6px">➕ ' . esc_html__( 'Add Games (bulk import)', 'gamehub-engine' ) . '</a>';
		// Render the button on its own line above the filter links.
		$views['gamehub_import'] = $html;
		return $views;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'gamehub-engine' ) );
		}

		$notice = '';
		if ( isset( $_POST['gamehub_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gamehub_import_nonce'] ) ), 'gamehub_import' ) ) {
			$notice = $this->handle_submit();
		}

		$s   = GameHub_Settings::get();
		$url = esc_url( $s['json_url'] ?? '' );
		?>
		<div class="wrap ghub-settings">
			<h1><?php esc_html_e( 'Add Games', 'gamehub-engine' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Bulk-import your games catalog from a JSON feed URL, an uploaded file, or pasted JSON.', 'gamehub-engine' ); ?></p>
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'gamehub_import', 'gamehub_import_nonce' ); ?>

				<h2 class="title"><?php esc_html_e( 'From a URL', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gh_url"><?php esc_html_e( 'JSON feed URL', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="url" id="gh_url" name="gamehub_url" class="large-text code" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/games.json">
							<label style="display:block;margin-top:6px"><input type="checkbox" name="gamehub_save_url" value="1" checked> <?php esc_html_e( 'Save this URL as the auto-sync source (Settings)', 'gamehub-engine' ); ?></label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Or upload / paste', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gh_file"><?php esc_html_e( 'Upload JSON file', 'gamehub-engine' ); ?></label></th>
						<td><input type="file" id="gh_file" name="gamehub_file" accept=".json,application/json,.txt"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gh_paste"><?php esc_html_e( 'Paste JSON', 'gamehub-engine' ); ?></label></th>
						<td><textarea id="gh_paste" name="gamehub_paste" rows="8" class="large-text code" placeholder='[{"name":"...","slug":"...","category":"...","gameicon":"/...png","url":"https://..."}]'></textarea></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Options', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'On import', 'gamehub-engine' ); ?></th>
						<td>
							<label><input type="checkbox" name="gamehub_update_existing" value="1" checked> <?php esc_html_e( 'Update games that already exist (match by game_id, then URL, then slug)', 'gamehub-engine' ); ?></label><br>
							<label><input type="checkbox" name="gamehub_deactivate_missing" value="1"> <?php esc_html_e( 'Draft games that are not present in this import', 'gamehub-engine' ); ?></label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Import games', 'gamehub-engine' ); ?></button>
				</p>
			</form>

			<div class="card" style="max-width:820px">
				<h2><?php esc_html_e( 'Expected format', 'gamehub-engine' ); ?></h2>
				<p><?php esc_html_e( 'A JSON array of games (or an object with a "games" array). Category pages are created automatically from each game\'s category. The gameicon path is served through your domain, and url is loaded as the game iframe.', 'gamehub-engine' ); ?></p>
				<pre style="background:#f6f7f7;padding:10px;border:1px solid #dcdcde;overflow:auto">[
  {
    "name": "Trapped in the Dollhouse",
    "slug": "trapped-in-the-dollhouse",
    "category": "Games for Girls",
    "gameicon": "/cdn-cgi/image/.../trapped-in-the-dollhouse-logo.png",
    "url": "https://gamesss.funox.com/gamelib/.../index.html"
  }
]</pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Process the import form. Priority: uploaded file > pasted JSON > URL.
	 *
	 * @return string Admin notice HTML.
	 */
	private function handle_submit() {
		$options = array(
			'update_existing'    => ! empty( $_POST['gamehub_update_existing'] ),
			'deactivate_missing' => ! empty( $_POST['gamehub_deactivate_missing'] ),
		);
		$importer = GameHub_Importer::instance();
		$stats    = null;
		$source   = '';

		if ( ! empty( $_FILES['gamehub_file']['tmp_name'] ) && is_uploaded_file( $_FILES['gamehub_file']['tmp_name'] ) ) {
			$body   = file_get_contents( $_FILES['gamehub_file']['tmp_name'] ); // phpcs:ignore
			$stats  = $importer->import_json( (string) $body, $options );
			$source = __( 'uploaded file', 'gamehub-engine' );
		} elseif ( ! empty( $_POST['gamehub_paste'] ) ) {
			$body   = wp_unslash( $_POST['gamehub_paste'] ); // phpcs:ignore WordPress.Security.ValidationSanitization
			$stats  = $importer->import_json( (string) $body, $options );
			$source = __( 'pasted JSON', 'gamehub-engine' );
		} else {
			$feed_url = esc_url_raw( trim( (string) ( $_POST['gamehub_url'] ?? '' ) ) );
			if ( '' === $feed_url ) {
				return '<div class="notice notice-error"><p>' . esc_html__( 'Provide a URL, upload a file, or paste JSON.', 'gamehub-engine' ) . '</p></div>';
			}
			if ( ! empty( $_POST['gamehub_save_url'] ) ) {
				$settings             = GameHub_Settings::get();
				$settings['json_url'] = $feed_url;
				update_option( GameHub_Settings::OPTION, $settings );
			}
			$stats  = $importer->import_from_url( $feed_url, $options );
			$source = __( 'URL', 'gamehub-engine' );
		}

		if ( is_wp_error( $stats ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $stats->get_error_message() ) . '</p></div>';
		}

		$msg = sprintf(
			/* translators: 1: source, 2-6: counts */
			esc_html__( 'Imported from %1$s: %2$d added, %3$d updated, %4$d skipped, %5$d drafted, %6$d errors.', 'gamehub-engine' ),
			esc_html( $source ),
			(int) $stats['inserted'],
			(int) $stats['updated'],
			(int) $stats['skipped'],
			(int) $stats['deactivated'],
			(int) $stats['errors']
		);
		$out = '<div class="notice notice-success"><p><strong>' . $msg . '</strong> '
			. '<a href="' . esc_url( admin_url( 'edit.php?post_type=game' ) ) . '">' . esc_html__( 'View games', 'gamehub-engine' ) . '</a></p>';
		if ( ! empty( $stats['error_lines'] ) ) {
			$out .= '<pre style="max-height:200px;overflow:auto;background:#f6f7f7;padding:8px;border:1px solid #dcdcde">'
				. esc_html( implode( "\n", array_slice( $stats['error_lines'], 0, 50 ) ) ) . '</pre>';
		}
		return $out . '</div>';
	}
}
