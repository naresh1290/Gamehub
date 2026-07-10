<?php
/**
 * Analytics admin screen: date range, scorecards, time-series chart,
 * ranked lists, per-game drilldown, CSV export.
 *
 * @package GameHub\Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Analytics_Admin {

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
		add_action( 'admin_post_ghub_analytics_export', array( $this, 'export_csv' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'Game Analytics', 'gamehub-analytics' ),
			__( 'Game Analytics', 'gamehub-analytics' ),
			'manage_options',
			'gamehub-analytics',
			array( $this, 'render' ),
			'dashicons-chart-area',
			26
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_gamehub-analytics' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ghub-analytics', GHUB_ANALYTICS_URL . 'assets/analytics.css', array(), GHUB_ANALYTICS_VERSION );
		wp_enqueue_script( 'ghub-analytics', GHUB_ANALYTICS_URL . 'assets/analytics.js', array(), GHUB_ANALYTICS_VERSION, true );
	}

	/**
	 * Resolve the current from/to/report/metric/game from the request.
	 */
	private function context() {
		$today   = current_time( 'Y-m-d' );
		$default_from = gmdate( 'Y-m-d', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS );

		$from   = GameHub_Analytics_Data::valid_date( $_GET['from'] ?? '', $default_from );
		$to     = GameHub_Analytics_Data::valid_date( $_GET['to'] ?? '', $today );
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		$report = sanitize_key( $_GET['report'] ?? 'plays' );
		$metric = sanitize_key( $_GET['metric'] ?? 'plays' );
		$game   = isset( $_GET['game'] ) ? (int) $_GET['game'] : 0;

		$valid_reports = array( 'plays', 'visits', 'likes', 'dislikes', 'rating', 'avg_session', 'trending' );
		if ( ! in_array( $report, $valid_reports, true ) ) {
			$report = 'plays';
		}
		return compact( 'from', 'to', 'report', 'metric', 'game' );
	}

	private static function report_labels() {
		return array(
			'plays'       => __( 'Most Played', 'gamehub-analytics' ),
			'visits'      => __( 'Most Visited', 'gamehub-analytics' ),
			'likes'       => __( 'Most Liked', 'gamehub-analytics' ),
			'dislikes'    => __( 'Most Disliked', 'gamehub-analytics' ),
			'rating'      => __( 'Highest Rated', 'gamehub-analytics' ),
			'avg_session' => __( 'Longest Sessions', 'gamehub-analytics' ),
			'trending'    => __( 'Trending', 'gamehub-analytics' ),
		);
	}

	private static function fmt_duration( $seconds ) {
		$seconds = (int) $seconds;
		if ( $seconds <= 0 ) {
			return '—';
		}
		$m = intdiv( $seconds, 60 );
		$s = $seconds % 60;
		if ( $m >= 60 ) {
			return sprintf( '%dh %dm', intdiv( $m, 60 ), $m % 60 );
		}
		return $m > 0 ? sprintf( '%dm %ds', $m, $s ) : sprintf( '%ds', $s );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'gamehub-analytics' ) );
		}

		$ctx     = $this->context();
		$summary = GameHub_Analytics_Data::summary( $ctx['from'], $ctx['to'] );
		$life    = GameHub_Analytics_Data::lifetime_totals();
		$series  = GameHub_Analytics_Data::timeseries( $ctx['from'], $ctx['to'], in_array( $ctx['metric'], array( 'plays', 'visits', 'likes', 'dislikes', 'session_seconds' ), true ) ? $ctx['metric'] : 'plays', $ctx['game'] ?: null );
		$labels  = self::report_labels();
		$rows    = GameHub_Analytics_Data::top( $ctx['report'], $ctx['from'], $ctx['to'], 50 );

		$base = admin_url( 'admin.php?page=gamehub-analytics' );

		// Pass series to JS.
		wp_add_inline_script(
			'ghub-analytics',
			'window.GHUB_SERIES=' . wp_json_encode(
				array(
					'labels' => array_keys( $series ),
					'values' => array_values( $series ),
					'metric' => $ctx['metric'],
				)
			) . ';',
			'before'
		);
		?>
		<div class="wrap ghub-analytics">
			<h1><?php esc_html_e( 'Game Analytics', 'gamehub-analytics' ); ?></h1>

			<?php $this->render_daterange( $base, $ctx ); ?>

			<div class="ghub-scorecards">
				<?php
				$this->scorecard( __( 'Plays', 'gamehub-analytics' ), number_format_i18n( $summary['plays'] ), sprintf( __( '%s lifetime', 'gamehub-analytics' ), number_format_i18n( $life['plays'] ?? 0 ) ) );
				$this->scorecard( __( 'Visits', 'gamehub-analytics' ), number_format_i18n( $summary['visits'] ), sprintf( __( '%s lifetime', 'gamehub-analytics' ), number_format_i18n( $life['visits'] ?? 0 ) ) );
				$this->scorecard( __( 'Likes', 'gamehub-analytics' ), number_format_i18n( $summary['likes'] ), '👍' );
				$this->scorecard( __( 'Dislikes', 'gamehub-analytics' ), number_format_i18n( $summary['dislikes'] ), '👎' );
				$this->scorecard( __( 'Sessions', 'gamehub-analytics' ), number_format_i18n( $summary['session_count'] ), __( 'play sessions', 'gamehub-analytics' ) );
				$this->scorecard( __( 'Avg session', 'gamehub-analytics' ), self::fmt_duration( $summary['avg_session'] ), __( 'time in game', 'gamehub-analytics' ) );
				?>
			</div>

			<div class="ghub-panel">
				<div class="ghub-panel-head">
					<h2>
						<?php
						if ( $ctx['game'] ) {
							printf( /* translators: game name */ esc_html__( 'Trend — %s', 'gamehub-analytics' ), esc_html( get_the_title( $ctx['game'] ) ) );
						} else {
							esc_html_e( 'Trend over time', 'gamehub-analytics' );
						}
						?>
					</h2>
					<div class="ghub-metric-tabs">
						<?php
						$metrics = array(
							'plays'           => __( 'Plays', 'gamehub-analytics' ),
							'visits'          => __( 'Visits', 'gamehub-analytics' ),
							'likes'           => __( 'Likes', 'gamehub-analytics' ),
							'session_seconds' => __( 'Playtime', 'gamehub-analytics' ),
						);
						foreach ( $metrics as $k => $lab ) {
							$url = add_query_arg( array_merge( $ctx, array( 'metric' => $k ) ), $base );
							printf(
								'<a class="ghub-tab%s" href="%s">%s</a>',
								$ctx['metric'] === $k ? ' is-active' : '',
								esc_url( $url ),
								esc_html( $lab )
							);
						}
						if ( $ctx['game'] ) {
							printf( ' <a class="ghub-tab" href="%s">%s</a>', esc_url( add_query_arg( array_diff_key( $ctx, array( 'game' => 0 ) ), $base ) ), esc_html__( '‹ All games', 'gamehub-analytics' ) );
						}
						?>
					</div>
				</div>
				<canvas id="ghub-chart" height="90"></canvas>
			</div>

			<div class="ghub-panel">
				<div class="ghub-panel-head">
					<div class="ghub-report-tabs">
						<?php
						foreach ( $labels as $key => $label ) {
							$url = add_query_arg( array_merge( $ctx, array( 'report' => $key ) ), $base );
							printf(
								'<a class="ghub-tab%s" href="%s">%s</a>',
								$ctx['report'] === $key ? ' is-active' : '',
								esc_url( $url ),
								esc_html( $label )
							);
						}
						?>
					</div>
					<?php
					$export_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'ghub_analytics_export',
								'report' => $ctx['report'],
								'from'   => $ctx['from'],
								'to'     => $ctx['to'],
							),
							admin_url( 'admin-post.php' )
						),
						'ghub_export'
					);
					?>
					<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'gamehub-analytics' ); ?></a>
				</div>

				<?php $this->render_table( $ctx, $rows, $base ); ?>
			</div>
		</div>
		<?php
	}

	private function render_daterange( $base, $ctx ) {
		$presets = array(
			'7'   => __( 'Last 7 days', 'gamehub-analytics' ),
			'30'  => __( 'Last 30 days', 'gamehub-analytics' ),
			'90'  => __( 'Last 90 days', 'gamehub-analytics' ),
			'365' => __( 'Last year', 'gamehub-analytics' ),
		);
		?>
		<div class="ghub-daterange">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="gamehub-analytics">
				<input type="hidden" name="report" value="<?php echo esc_attr( $ctx['report'] ); ?>">
				<input type="hidden" name="metric" value="<?php echo esc_attr( $ctx['metric'] ); ?>">
				<?php if ( $ctx['game'] ) : ?><input type="hidden" name="game" value="<?php echo (int) $ctx['game']; ?>"><?php endif; ?>
				<label><?php esc_html_e( 'From', 'gamehub-analytics' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $ctx['from'] ); ?>"></label>
				<label><?php esc_html_e( 'To', 'gamehub-analytics' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $ctx['to'] ); ?>"></label>
				<button class="button button-primary"><?php esc_html_e( 'Apply', 'gamehub-analytics' ); ?></button>
			</form>
			<div class="ghub-presets">
				<?php
				foreach ( $presets as $days => $label ) {
					$from = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( (int) $days - 1 ) * DAY_IN_SECONDS );
					$to   = current_time( 'Y-m-d' );
					$url  = add_query_arg( array_merge( $ctx, array( 'from' => $from, 'to' => $to ) ), $base );
					printf( '<a class="ghub-preset" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	private function scorecard( $label, $value, $sub ) {
		echo '<div class="ghub-scorecard"><div class="ghub-sc-label">' . esc_html( $label ) . '</div>';
		echo '<div class="ghub-sc-value">' . esc_html( $value ) . '</div>';
		echo '<div class="ghub-sc-sub">' . esc_html( $sub ) . '</div></div>';
	}

	private function render_table( $ctx, $rows, $base ) {
		if ( empty( $rows ) ) {
			echo '<p class="ghub-empty">' . esc_html__( 'No data for this report and range yet.', 'gamehub-analytics' ) . '</p>';
			return;
		}
		$is_duration = ( 'avg_session' === $ctx['report'] );
		$is_rating   = ( 'rating' === $ctx['report'] );
		?>
		<table class="widefat striped ghub-table">
			<thead>
				<tr>
					<th class="ghub-rank">#</th>
					<th><?php esc_html_e( 'Game', 'gamehub-analytics' ); ?></th>
					<th><?php echo esc_html( self::report_labels()[ $ctx['report'] ] ); ?></th>
					<th><?php esc_html_e( 'Plays', 'gamehub-analytics' ); ?></th>
					<th><?php esc_html_e( 'Visits', 'gamehub-analytics' ); ?></th>
					<th><?php esc_html_e( 'Likes', 'gamehub-analytics' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'gamehub-analytics' ); ?></th>
					<th><?php esc_html_e( 'Avg session', 'gamehub-analytics' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $i => $r ) : ?>
					<?php $drill = add_query_arg( array_merge( $ctx, array( 'game' => $r['post_id'], 'metric' => 'plays' ) ), $base ); ?>
					<tr>
						<td class="ghub-rank"><?php echo (int) ( $i + 1 ); ?></td>
						<td class="ghub-gamecell">
							<a href="<?php echo esc_url( $drill ); ?>"><strong><?php echo esc_html( $r['name'] ?: ( '#' . $r['post_id'] ) ); ?></strong></a>
							<span class="ghub-rowlinks">
								<?php if ( $r['view_link'] ) : ?><a href="<?php echo esc_url( $r['view_link'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'gamehub-analytics' ); ?></a><?php endif; ?>
								<?php if ( $r['edit_link'] ) : ?> · <a href="<?php echo esc_url( $r['edit_link'] ); ?>"><?php esc_html_e( 'Edit', 'gamehub-analytics' ); ?></a><?php endif; ?>
							</span>
						</td>
						<td class="ghub-metric">
							<?php
							if ( $is_duration ) {
								echo esc_html( self::fmt_duration( $r['metric'] ) );
							} elseif ( $is_rating ) {
								echo '★ ' . esc_html( number_format_i18n( $r['metric'], 2 ) );
							} else {
								echo esc_html( number_format_i18n( $r['metric'] ) );
							}
							?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $r['plays'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $r['visits'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $r['likes'] ) ); ?></td>
						<td><?php echo $r['rating'] > 0 ? '★ ' . esc_html( number_format_i18n( $r['rating'], 2 ) ) : '—'; ?></td>
						<td><?php echo esc_html( self::fmt_duration( $r['avg_session'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Stream the current report as CSV.
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ghub_export' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'gamehub-analytics' ) );
		}
		$today  = current_time( 'Y-m-d' );
		$from   = GameHub_Analytics_Data::valid_date( $_GET['from'] ?? '', gmdate( 'Y-m-d', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS ) );
		$to     = GameHub_Analytics_Data::valid_date( $_GET['to'] ?? '', $today );
		$report = sanitize_key( $_GET['report'] ?? 'plays' );
		$rows   = GameHub_Analytics_Data::top( $report, $from, $to, 500 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gamehub-' . $report . '-' . $from . '_to_' . $to . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Rank', 'Game', 'Post ID', ucfirst( $report ), 'Plays', 'Visits', 'Likes', 'Dislikes', 'Rating', 'Avg session (s)', 'URL' ) );
		foreach ( $rows as $i => $r ) {
			fputcsv(
				$out,
				array(
					$i + 1,
					$r['name'],
					$r['post_id'],
					$r['metric'],
					$r['plays'],
					$r['visits'],
					$r['likes'],
					$r['dislikes'],
					$r['rating'],
					$r['avg_session'],
					$r['view_link'],
				)
			);
		}
		fclose( $out );
		exit;
	}
}
