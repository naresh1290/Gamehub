<?php
/**
 * Plugin Name:       GameHub Analytics
 * Plugin URI:        https://github.com/OWNER/gamehub-analytics
 * Description:        Analytics dashboards for GameHub: calendars, date ranges, time-series charts, and ranked lists (Most Played, Most Visited, Most Liked, Highest Rated, Longest Sessions, Trending) with CSV export. Requires GameHub Engine.
 * Version:           1.0.0
 * Author:            GameHub
 * License:           GPL-2.0-or-later
 * Text Domain:       gamehub-analytics
 * Requires at least: 6.2
 * Requires PHP:      7.4
 *
 * @package GameHub\Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GHUB_ANALYTICS_VERSION', '1.0.0' );
define( 'GHUB_ANALYTICS_FILE', __FILE__ );
define( 'GHUB_ANALYTICS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GHUB_ANALYTICS_URL', plugin_dir_url( __FILE__ ) );
define( 'GHUB_ANALYTICS_BASENAME', plugin_basename( __FILE__ ) );

require_once GHUB_ANALYTICS_PATH . 'includes/class-analytics-data.php';
require_once GHUB_ANALYTICS_PATH . 'includes/class-analytics-admin.php';
require_once GHUB_ANALYTICS_PATH . 'includes/class-gamehub-updater.php';

add_action(
	'plugins_loaded',
	function () {
		// Hard dependency on the engine (which owns the stats tables).
		if ( ! class_exists( 'GameHub_Stats' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'GameHub Analytics', 'gamehub-analytics' ) . ':</strong> ' .
						esc_html__( 'requires the GameHub Engine plugin to be active.', 'gamehub-analytics' ) . '</p></div>';
				}
			);
			return;
		}

		GameHub_Analytics_Admin::instance();

		if ( is_admin() && class_exists( 'GameHub_Updater' ) ) {
			$repo  = 'naresh1290/Gamehub';
			$token = '';
			if ( class_exists( 'GameHub_Settings' ) ) {
				$s     = GameHub_Settings::get();
				$repo  = ! empty( $s['github_repo_analytics'] ) ? $s['github_repo_analytics'] : $repo;
				$token = ! empty( $s['github_token'] ) ? $s['github_token'] : '';
			}
			new GameHub_Updater(
				array(
					'type'       => 'plugin',
					'file'       => GHUB_ANALYTICS_BASENAME,
					'slug'       => 'gamehub-analytics',
					'version'    => GHUB_ANALYTICS_VERSION,
					'repo'       => $repo,
					'token'      => $token,
					'asset_name' => 'gamehub-analytics.zip',
					'tag_prefix' => 'analytics-',
				)
			);
		}
	}
);
