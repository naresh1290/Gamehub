<?php
/**
 * Engine settings: the single source of white-label configuration.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Settings {

	const OPTION = 'ghub_engine_settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Default settings.
	 */
	public static function defaults() {
		return array(
			// Import.
			'json_url'        => '',
			'sync_interval'   => 'disabled', // disabled|hourly|twicedaily|daily.
			'update_existing' => 1,
			'deactivate_missing' => 0,
			// URLs / slugs.
			'slug_game'       => 'g',
			'slug_archive'    => 'games',
			'slug_category'   => 'c',
			'per_page'        => 60,
			// Playable proxy (blank = embed URLs directly, no proxying).
			'proxy_origin'    => '',
			// Icon image reverse proxy (serve CDN icons via this domain).
			'icon_proxy'      => 0,
			'icon_cdn_host'   => 'img.poki-cdn.com',
			'icon_proxy_path' => 'img',
			// Branding.
			'site_tagline'    => '',
			'homepage_content' => '', // SEO/content block shown at the bottom of the homepage.
			'meta_suffix'     => '', // Appended to game + category meta titles site-wide.
			// Updates (monorepo shared by all three, distinguished by tag prefix).
			'github_repo'          => 'naresh1290/Gamehub',
			'github_repo_theme'    => 'naresh1290/Gamehub',
			'github_repo_analytics' => 'naresh1290/Gamehub',
			'github_token'         => '', // optional, for private repos.
		);
	}

	/**
	 * Merged settings (stored over defaults).
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=game',
			__( 'GameHub Settings', 'gamehub-engine' ),
			__( 'Settings', 'gamehub-engine' ),
			'manage_options',
			'gamehub-settings',
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting(
			'ghub_engine',
			self::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'gamehub-settings' ) ) {
			return;
		}
		wp_enqueue_style( 'ghub-engine-admin', GHUB_ENGINE_URL . 'assets/admin.css', array(), GHUB_ENGINE_VERSION );
	}

	/**
	 * Sanitize the full settings array on save and reschedule cron if the
	 * interval changed.
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['json_url']         = esc_url_raw( trim( (string) ( $input['json_url'] ?? '' ) ) );
		$intervals               = array( 'disabled', 'hourly', 'twicedaily', 'daily' );
		$out['sync_interval']    = in_array( ( $input['sync_interval'] ?? '' ), $intervals, true ) ? $input['sync_interval'] : 'disabled';
		$out['update_existing']  = empty( $input['update_existing'] ) ? 0 : 1;
		$out['deactivate_missing'] = empty( $input['deactivate_missing'] ) ? 0 : 1;

		$out['slug_game']     = sanitize_title( $input['slug_game'] ?? 'g' ) ?: 'g';
		$out['slug_archive']  = sanitize_title( $input['slug_archive'] ?? 'games' ) ?: 'games';
		$out['slug_category'] = sanitize_title( $input['slug_category'] ?? 'c' ) ?: 'c';
		$out['per_page']      = max( 1, min( 500, (int) ( $input['per_page'] ?? 60 ) ) );

		$out['proxy_origin']     = esc_url_raw( trim( (string) ( $input['proxy_origin'] ?? '' ) ) );
		$out['site_tagline']     = sanitize_text_field( $input['site_tagline'] ?? '' );
		$out['homepage_content'] = wp_kses_post( $input['homepage_content'] ?? '' );
		$out['meta_suffix']      = sanitize_text_field( $input['meta_suffix'] ?? '' );

		$out['icon_proxy']      = empty( $input['icon_proxy'] ) ? 0 : 1;
		// Store bare host only (strip scheme/path if pasted).
		$host                   = strtolower( trim( (string) ( $input['icon_cdn_host'] ?? '' ) ) );
		$host                   = preg_replace( '#^https?://#', '', $host );
		$host                   = trim( (string) preg_replace( '#/.*$#', '', $host ) );
		$out['icon_cdn_host']   = preg_match( '/^[a-z0-9.-]+$/', $host ) ? $host : 'img.poki-cdn.com';
		$out['icon_proxy_path'] = trim( sanitize_title( $input['icon_proxy_path'] ?? 'img' ) ) ?: 'img';

		$out['github_repo']           = sanitize_text_field( trim( (string) ( $input['github_repo'] ?? '' ) ) );
		$out['github_repo_theme']     = sanitize_text_field( trim( (string) ( $input['github_repo_theme'] ?? '' ) ) );
		$out['github_repo_analytics'] = sanitize_text_field( trim( (string) ( $input['github_repo_analytics'] ?? '' ) ) );
		$out['github_token']          = sanitize_text_field( trim( (string) ( $input['github_token'] ?? '' ) ) );

		// Slugs changed → rewrite rules need a flush on next load.
		$old = self::get();
		if (
			$old['slug_game'] !== $out['slug_game'] ||
			$old['slug_archive'] !== $out['slug_archive'] ||
			$old['slug_category'] !== $out['slug_category']
		) {
			update_option( 'ghub_flush_needed', 1 );
		}

		// Reschedule sync cron to match the chosen interval.
		GameHub_Importer::reschedule( $out['sync_interval'] );

		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'gamehub-engine' ) );
		}
		$s          = self::get();
		$last_sync  = get_option( 'ghub_last_sync' );
		$sync_notice = '';

		// Manual "Fetch now" action.
		if ( isset( $_POST['ghub_fetch_now'] ) && check_admin_referer( 'ghub_fetch_now' ) ) {
			$stats = GameHub_Importer::instance()->run_from_settings();
			if ( is_wp_error( $stats ) ) {
				$sync_notice = '<div class="notice notice-error"><p>' . esc_html( $stats->get_error_message() ) . '</p></div>';
			} else {
				$sync_notice = '<div class="notice notice-success"><p>' . sprintf(
					/* translators: import counts */
					esc_html__( 'Sync complete: %1$d added, %2$d updated, %3$d skipped, %4$d deactivated, %5$d errors.', 'gamehub-engine' ),
					(int) $stats['inserted'],
					(int) $stats['updated'],
					(int) $stats['skipped'],
					(int) $stats['deactivated'],
					(int) $stats['errors']
				) . '</p></div>';
				$last_sync = get_option( 'ghub_last_sync' );
			}
		}
		?>
		<div class="wrap ghub-settings">
			<h1><?php esc_html_e( 'GameHub Settings', 'gamehub-engine' ); ?></h1>
			<?php echo $sync_notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'ghub_engine' ); ?>

				<h2 class="title"><?php esc_html_e( 'Games source (remote JSON)', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ghub_json_url"><?php esc_html_e( 'JSON URL', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="url" id="ghub_json_url" class="large-text code" name="<?php echo esc_attr( self::OPTION ); ?>[json_url]" value="<?php echo esc_attr( $s['json_url'] ); ?>" placeholder="https://example.com/games.json">
							<p class="description"><?php esc_html_e( 'A URL returning a JSON array of games (or an object with a "games" array). Fields: name, iconurl, gameurl/iframeurl, category, game_id.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-sync', 'gamehub-engine' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[sync_interval]">
								<?php
								$opts = array(
									'disabled'   => __( 'Disabled', 'gamehub-engine' ),
									'hourly'     => __( 'Hourly', 'gamehub-engine' ),
									'twicedaily' => __( 'Twice daily', 'gamehub-engine' ),
									'daily'      => __( 'Daily', 'gamehub-engine' ),
								);
								foreach ( $opts as $k => $label ) {
									printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $s['sync_interval'], $k, false ), esc_html( $label ) );
								}
								?>
							</select>
							<label style="margin-left:12px"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[update_existing]" value="1" <?php checked( $s['update_existing'], 1 ); ?>> <?php esc_html_e( 'Update existing games on sync', 'gamehub-engine' ); ?></label>
							<label style="margin-left:12px"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[deactivate_missing]" value="1" <?php checked( $s['deactivate_missing'], 1 ); ?>> <?php esc_html_e( 'Draft games missing from the feed', 'gamehub-engine' ); ?></label>
							<p class="description">
								<?php
								if ( $last_sync ) {
									printf(
										/* translators: last sync time */
										esc_html__( 'Last sync: %s', 'gamehub-engine' ),
										esc_html( wp_date( 'Y-m-d H:i', (int) $last_sync ) )
									);
								} else {
									esc_html_e( 'Never synced yet.', 'gamehub-engine' );
								}
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Permalinks', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Game URL base', 'gamehub-engine' ); ?></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[slug_game]" value="<?php echo esc_attr( $s['slug_game'] ); ?>" style="width:120px">
							<code>/game-name/</code>
							<p class="description"><?php esc_html_e( 'Default: g → /g/game-name/', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'All-games base', 'gamehub-engine' ); ?></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[slug_archive]" value="<?php echo esc_attr( $s['slug_archive'] ); ?>" style="width:120px">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Category base', 'gamehub-engine' ); ?></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[slug_category]" value="<?php echo esc_attr( $s['slug_category'] ); ?>" style="width:120px">
							<code>/category-name/</code>
							<p class="description"><?php esc_html_e( 'Default: c → /c/category-name/. Changing any base re-flushes permalinks automatically.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Games per page', 'gamehub-engine' ); ?></th>
						<td><input type="number" min="1" max="500" name="<?php echo esc_attr( self::OPTION ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>" style="width:90px"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Playable proxy (optional)', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ghub_proxy_origin"><?php esc_html_e( 'Proxy origin', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="url" id="ghub_proxy_origin" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[proxy_origin]" value="<?php echo esc_attr( $s['proxy_origin'] ); ?>" placeholder="https://games.example.com">
							<p class="description"><?php esc_html_e( 'Leave blank to embed game URLs directly. Set this only if you route Google Playables through an nginx proxy on your own domain.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_tagline"><?php esc_html_e( 'Homepage tagline', 'gamehub-engine' ); ?></label></th>
						<td><input type="text" id="ghub_tagline" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[site_tagline]" value="<?php echo esc_attr( $s['site_tagline'] ); ?>" placeholder="<?php esc_attr_e( 'Play free online games', 'gamehub-engine' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_home_content"><?php esc_html_e( 'Homepage content', 'gamehub-engine' ); ?></label></th>
						<td>
							<textarea id="ghub_home_content" name="<?php echo esc_attr( self::OPTION ); ?>[homepage_content]" rows="6" class="large-text"><?php echo esc_textarea( $s['homepage_content'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'SEO / intro text shown in the content section at the bottom of the homepage. Basic HTML allowed.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_meta_suffix"><?php esc_html_e( 'Meta title suffix', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="text" id="ghub_meta_suffix" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[meta_suffix]" value="<?php echo esc_attr( $s['meta_suffix'] ); ?>" placeholder="<?php esc_attr_e( ' - Play Free Online', 'gamehub-engine' ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: example title */
									esc_html__( 'Appended to every game and category page title. e.g. a game becomes %s. Include your own separator.', 'gamehub-engine' ),
									'<code>' . esc_html__( 'Subway Surfers - Play Free Online', 'gamehub-engine' ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Icon image proxy', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable proxy', 'gamehub-engine' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[icon_proxy]" value="1" <?php checked( $s['icon_proxy'], 1 ); ?>> <?php esc_html_e( 'Serve game icons through this domain instead of the CDN', 'gamehub-engine' ); ?></label>
							<p class="description"><?php esc_html_e( 'Requires a matching nginx reverse-proxy location on the server (see the deploy/nginx snippet).', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_icon_host"><?php esc_html_e( 'CDN host', 'gamehub-engine' ); ?></label></th>
						<td><input type="text" id="ghub_icon_host" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[icon_cdn_host]" value="<?php echo esc_attr( $s['icon_cdn_host'] ); ?>" placeholder="img.poki-cdn.com"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_icon_path"><?php esc_html_e( 'Local path', 'gamehub-engine' ); ?></label></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" id="ghub_icon_path" name="<?php echo esc_attr( self::OPTION ); ?>[icon_proxy_path]" value="<?php echo esc_attr( $s['icon_proxy_path'] ); ?>" style="width:100px">
							<code>/…/icon.png</code>
							<p class="description">
								<?php
								printf(
									/* translators: 1: example source URL, 2: example proxied URL */
									esc_html__( 'e.g. %1$s → %2$s', 'gamehub-engine' ),
									'<code>https://' . esc_html( $s['icon_cdn_host'] ) . '/a/b.png</code>',
									'<code>' . esc_html( home_url( '/' . $s['icon_proxy_path'] . '/a/b.png' ) ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Updates (GitHub releases)', 'gamehub-engine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ghub_repo"><?php esc_html_e( 'Engine repo', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="text" id="ghub_repo" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[github_repo]" value="<?php echo esc_attr( $s['github_repo'] ); ?>" placeholder="owner/gamehub-engine">
							<p class="description"><?php esc_html_e( 'GitHub owner/repo that hosts releases for this plugin.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_repo_theme"><?php esc_html_e( 'Theme repo', 'gamehub-engine' ); ?></label></th>
						<td><input type="text" id="ghub_repo_theme" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[github_repo_theme]" value="<?php echo esc_attr( $s['github_repo_theme'] ); ?>" placeholder="owner/gamehub"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_repo_analytics"><?php esc_html_e( 'Analytics repo', 'gamehub-engine' ); ?></label></th>
						<td><input type="text" id="ghub_repo_analytics" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[github_repo_analytics]" value="<?php echo esc_attr( $s['github_repo_analytics'] ); ?>" placeholder="owner/gamehub-analytics"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ghub_token"><?php esc_html_e( 'Access token', 'gamehub-engine' ); ?></label></th>
						<td>
							<input type="password" id="ghub_token" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[github_token]" value="<?php echo esc_attr( $s['github_token'] ); ?>" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Only needed for private repositories. Leave blank for public repos.', 'gamehub-engine' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'gamehub-engine' ) ); ?>
			</form>

			<hr>
			<form method="post">
				<?php wp_nonce_field( 'ghub_fetch_now' ); ?>
				<p>
					<button type="submit" name="ghub_fetch_now" value="1" class="button button-secondary"><?php esc_html_e( 'Fetch &amp; sync games now', 'gamehub-engine' ); ?></button>
					<span class="description"><?php esc_html_e( 'Pulls the JSON URL immediately using the options saved above.', 'gamehub-engine' ); ?></span>
				</p>
			</form>
		</div>
		<?php
	}
}

/**
 * Flush rewrite rules once, on the request after a slug change.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'ghub_flush_needed' ) ) {
			flush_rewrite_rules();
			delete_option( 'ghub_flush_needed' );
		}
	},
	99
);
