<?php
/**
 * Self-updater backed by GitHub Releases — shared by the engine plugin, the
 * analytics plugin, and the theme.
 *
 * Each package bundles its own copy of this file; the class is guarded so the
 * first one loaded defines it and the rest reuse it. Every instance is
 * configured independently, so plugins and the theme update on separate tracks.
 *
 * A release must attach a built zip asset (e.g. `gamehub-engine.zip`) whose
 * top-level folder matches the plugin/theme slug. The release tag is the
 * version (e.g. `1.2.0` or `v1.2.0`).
 *
 * @package GameHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GameHub_Updater' ) ) {

	class GameHub_Updater {

		/** @var array */
		private $config;

		/**
		 * @param array $config {
		 *   @type string type       'plugin' | 'theme'
		 *   @type string file       Plugin basename (plugin) or stylesheet dir name (theme).
		 *   @type string slug       Folder slug.
		 *   @type string version    Installed version.
		 *   @type string repo       owner/repo.
		 *   @type string token      Optional GitHub token (private repos).
		 *   @type string asset_name Release asset filename to install.
		 * }
		 */
		public function __construct( array $config ) {
			$this->config = wp_parse_args(
				$config,
				array(
					'type'       => 'plugin',
					'file'       => '',
					'slug'       => '',
					'version'    => '0.0.0',
					'repo'       => '',
					'token'      => '',
					'asset_name' => '',
					// For a monorepo shared by several packages: only releases whose
					// tag starts with this prefix belong to this package (e.g.
					// "engine-"). Empty = use the repo's single latest release.
					'tag_prefix' => '',
				)
			);

			if ( 'theme' === $this->config['type'] ) {
				add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme' ) );
			} else {
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin' ) );
				add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
			}

			// Authenticated asset downloads for private repos.
			add_filter( 'http_request_args', array( $this, 'auth_download' ), 10, 2 );

			// Handle the "Check for updates" trigger.
			add_action( 'admin_init', array( $this, 'maybe_force_check' ) );
		}

		/* ---------------------------------------------------------------- */

		private function transient_key() {
			return 'ghub_upd_' . md5( $this->config['repo'] . '|' . $this->config['slug'] );
		}

		/**
		 * Fetch (and cache) the latest release from GitHub.
		 *
		 * @return array|null { version, package, url, changelog, published } or null.
		 */
		private function get_release() {
			if ( empty( $this->config['repo'] ) || false === strpos( $this->config['repo'], '/' ) ) {
				return null;
			}

			$cached = get_transient( $this->transient_key() );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$headers = array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'GameHub-Updater' );
			if ( ! empty( $this->config['token'] ) ) {
				$headers['Authorization'] = 'Bearer ' . $this->config['token'];
			}

			$prefix = (string) $this->config['tag_prefix'];
			$api    = 'https://api.github.com/repos/' . $this->config['repo'] . '/releases';
			$api   .= ( '' !== $prefix ) ? '?per_page=100' : '/latest';

			$response = wp_remote_get( $api, array( 'timeout' => 15, 'headers' => $headers ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				set_transient( $this->transient_key(), array(), 2 * HOUR_IN_SECONDS );
				return null;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( '' !== $prefix ) {
				// Monorepo: pick the highest-versioned release tagged with our prefix.
				$best     = null;
				$best_ver = '0.0.0';
				foreach ( (array) $body as $rel ) {
					$tag = (string) ( $rel['tag_name'] ?? '' );
					if ( 0 !== strpos( $tag, $prefix ) || ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
						continue;
					}
					$ver = ltrim( substr( $tag, strlen( $prefix ) ), 'vV' );
					if ( version_compare( $ver, $best_ver, '>' ) ) {
						$best_ver = $ver;
						$best     = $rel;
					}
				}
				if ( ! $best ) {
					set_transient( $this->transient_key(), array(), 2 * HOUR_IN_SECONDS );
					return null;
				}
				$body    = $best;
				$version = $best_ver;
			} else {
				if ( empty( $body['tag_name'] ) ) {
					return null;
				}
				$version = ltrim( (string) $body['tag_name'], 'vV' );
			}
			$package = '';
			if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
				foreach ( $body['assets'] as $asset ) {
					if ( ! empty( $this->config['asset_name'] ) && ( $asset['name'] ?? '' ) === $this->config['asset_name'] ) {
						// Private repos: use the API asset URL (auth added on download).
						$package = ! empty( $this->config['token'] ) ? ( $asset['url'] ?? '' ) : ( $asset['browser_download_url'] ?? '' );
						break;
					}
				}
			}
			if ( '' === $package ) {
				$package = $body['zipball_url'] ?? '';
			}

			$release = array(
				'version'   => $version,
				'package'   => $package,
				'url'       => $body['html_url'] ?? ( 'https://github.com/' . $this->config['repo'] ),
				'changelog' => (string) ( $body['body'] ?? '' ),
				'published' => $body['published_at'] ?? '',
			);
			set_transient( $this->transient_key(), $release, 6 * HOUR_IN_SECONDS );
			return $release;
		}

		/* ---------------------------------------------------------------- */

		public function check_plugin( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}
			$release = $this->get_release();
			if ( ! $release || version_compare( $release['version'], $this->config['version'], '<=' ) ) {
				return $transient;
			}

			$item = array(
				'slug'        => $this->config['slug'],
				'plugin'      => $this->config['file'],
				'new_version' => $release['version'],
				'url'         => $release['url'],
				'package'     => $release['package'],
			);
			$transient->response[ $this->config['file'] ] = (object) $item;
			return $transient;
		}

		public function plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->config['slug'] ) {
				return $result;
			}
			$release = $this->get_release();
			if ( ! $release ) {
				return $result;
			}
			return (object) array(
				'name'          => $this->config['slug'],
				'slug'          => $this->config['slug'],
				'version'       => $release['version'],
				'download_link' => $release['package'],
				'sections'      => array(
					'changelog' => wpautop( wp_kses_post( $release['changelog'] ) ),
				),
			);
		}

		public function check_theme( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}
			$release = $this->get_release();
			if ( ! $release || version_compare( $release['version'], $this->config['version'], '<=' ) ) {
				return $transient;
			}
			$transient->response[ $this->config['file'] ] = array(
				'theme'       => $this->config['file'],
				'new_version' => $release['version'],
				'url'         => $release['url'],
				'package'     => $release['package'],
			);
			return $transient;
		}

		/**
		 * Add auth + octet-stream accept when downloading a private release asset.
		 */
		public function auth_download( $args, $url ) {
			if (
				! empty( $this->config['token'] ) &&
				false !== strpos( (string) $url, 'api.github.com/repos/' . $this->config['repo'] . '/releases/assets/' )
			) {
				$args['headers']               = isset( $args['headers'] ) ? (array) $args['headers'] : array();
				$args['headers']['Authorization'] = 'Bearer ' . $this->config['token'];
				$args['headers']['Accept']        = 'application/octet-stream';
			}
			return $args;
		}

		/**
		 * Clear the cache when the user clicks a "Check for updates" link
		 * (?ghub_check_updates=slug). Then let WP re-run its update checks.
		 */
		public function maybe_force_check() {
			if (
				empty( $_GET['ghub_check_updates'] ) ||
				sanitize_key( wp_unslash( $_GET['ghub_check_updates'] ) ) !== $this->config['slug'] ||
				! current_user_can( 'update_plugins' )
			) {
				return;
			}
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ghub_check_' . $this->config['slug'] ) ) {
				return;
			}
			delete_transient( $this->transient_key() );
			if ( 'theme' === $this->config['type'] ) {
				delete_site_transient( 'update_themes' );
			} else {
				delete_site_transient( 'update_plugins' );
			}
			wp_safe_redirect( remove_query_arg( array( 'ghub_check_updates', '_wpnonce' ) ) );
			exit;
		}

		/**
		 * Build a nonce'd "Check for updates" URL for this package.
		 */
		public static function check_url( $slug, $base = '' ) {
			$base = $base ?: admin_url( 'plugins.php' );
			return wp_nonce_url( add_query_arg( 'ghub_check_updates', $slug, $base ), 'ghub_check_' . $slug );
		}
	}
}
