<?php
/**
 * Plugin Name:       GameHub Engine
 * Plugin URI:        https://github.com/OWNER/gamehub-engine
 * Description:        Games data engine: registers the Game post type and category taxonomy, imports games from a remote JSON URL, tracks plays/visits/likes/ratings/session-time, and exposes a REST API. Powers the GameHub theme and GameHub Analytics.
 * Version:           1.0.0
 * Author:            GameHub
 * License:           GPL-2.0-or-later
 * Text Domain:       gamehub-engine
 * Requires at least: 6.2
 * Requires PHP:      7.4
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GHUB_ENGINE_VERSION', '1.0.0' );
define( 'GHUB_ENGINE_FILE', __FILE__ );
define( 'GHUB_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GHUB_ENGINE_URL', plugin_dir_url( __FILE__ ) );
define( 'GHUB_ENGINE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Canonical schema version for the custom tables. Bump when tables change so
 * upgrades re-run dbDelta on the next admin request.
 */
define( 'GHUB_ENGINE_DB_VERSION', '1.0.0' );

require_once GHUB_ENGINE_PATH . 'includes/helpers.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-stats.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-cpt.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-settings.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-importer.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-rest.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-sitemap.php';
require_once GHUB_ENGINE_PATH . 'includes/class-gamehub-updater.php';

/**
 * Boot the plugin once all plugins are loaded.
 */
function ghub_engine_boot() {
	GameHub_CPT::instance();
	GameHub_Stats::instance();
	GameHub_Settings::instance();
	GameHub_Importer::instance();
	GameHub_REST::instance();
	GameHub_Sitemap::instance();

	// Self-update from GitHub releases.
	if ( is_admin() ) {
		$settings = GameHub_Settings::get();
		new GameHub_Updater(
			array(
				'type'       => 'plugin',
				'file'       => GHUB_ENGINE_BASENAME,
				'slug'       => 'gamehub-engine',
				'version'    => GHUB_ENGINE_VERSION,
				'repo'       => ! empty( $settings['github_repo'] ) ? $settings['github_repo'] : 'naresh1290/Gamehub',
				'token'      => ! empty( $settings['github_token'] ) ? $settings['github_token'] : '',
				'asset_name' => 'gamehub-engine.zip',
				'tag_prefix' => 'engine-',
			)
		);
	}

	// Run pending table upgrades if the DB version changed after an update.
	if ( get_option( 'ghub_engine_db_version' ) !== GHUB_ENGINE_DB_VERSION ) {
		GameHub_Stats::install();
		update_option( 'ghub_engine_db_version', GHUB_ENGINE_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'ghub_engine_boot' );

/**
 * Activation: create tables, register rewrites, flush.
 */
function ghub_engine_activate() {
	GameHub_Stats::install();
	update_option( 'ghub_engine_db_version', GHUB_ENGINE_DB_VERSION );

	// CPT/taxonomy must be registered before flushing so their rules exist.
	GameHub_CPT::instance()->register();
	flush_rewrite_rules();

	// Schedule the default (disabled-until-configured) sync cron.
	GameHub_Importer::schedule_default();
}
register_activation_hook( __FILE__, 'ghub_engine_activate' );

/**
 * Deactivation: clear cron, flush rewrites.
 */
function ghub_engine_deactivate() {
	GameHub_Importer::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ghub_engine_deactivate' );
