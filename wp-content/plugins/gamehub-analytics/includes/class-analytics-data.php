<?php
/**
 * Read-only analytics queries over the engine's stats + daily tables.
 *
 * @package GameHub\Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Analytics_Data {

	private static function stats_table() {
		global $wpdb;
		return $wpdb->prefix . 'gh_stats';
	}

	private static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'gh_daily';
	}

	/**
	 * Clamp a Y-m-d string; fall back to a default.
	 */
	public static function valid_date( $s, $fallback ) {
		$s = (string) $s;
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
			$d = DateTime::createFromFormat( 'Y-m-d', $s );
			if ( $d && $d->format( 'Y-m-d' ) === $s ) {
				return $s;
			}
		}
		return $fallback;
	}

	/**
	 * Summed metrics across all games for a day range.
	 *
	 * @return array
	 */
	public static function summary( $from, $to ) {
		global $wpdb;
		$daily = self::daily_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(plays),0) AS plays,
					COALESCE(SUM(visits),0) AS visits,
					COALESCE(SUM(likes),0) AS likes,
					COALESCE(SUM(dislikes),0) AS dislikes,
					COALESCE(SUM(session_seconds),0) AS session_seconds,
					COALESCE(SUM(session_count),0) AS session_count
				FROM $daily WHERE day BETWEEN %s AND %s",
				$from,
				$to
			),
			ARRAY_A
		);
		$row              = $row ?: array();
		$row              = array_map( 'intval', wp_parse_args( $row, array( 'plays' => 0, 'visits' => 0, 'likes' => 0, 'dislikes' => 0, 'session_seconds' => 0, 'session_count' => 0 ) ) );
		$row['avg_session'] = $row['session_count'] > 0 ? (int) round( $row['session_seconds'] / $row['session_count'] ) : 0;
		return $row;
	}

	/**
	 * Per-day totals for a metric across all games (or one game).
	 *
	 * @param string   $from    Y-m-d.
	 * @param string   $to      Y-m-d.
	 * @param string   $metric  plays|visits|likes|dislikes|session_seconds.
	 * @param int|null $post_id Optional single game.
	 * @return array<string,int> day => value, zero-filled across the range.
	 */
	public static function timeseries( $from, $to, $metric, $post_id = null ) {
		global $wpdb;
		$allowed = array( 'plays', 'visits', 'likes', 'dislikes', 'session_seconds' );
		if ( ! in_array( $metric, $allowed, true ) ) {
			$metric = 'plays';
		}
		$daily = self::daily_table();

		$sql    = "SELECT day, COALESCE(SUM($metric),0) AS v FROM $daily WHERE day BETWEEN %s AND %s";
		$params = array( $from, $to );
		if ( $post_id ) {
			$sql     .= ' AND post_id = %d';
			$params[] = (int) $post_id;
		}
		$sql .= ' GROUP BY day';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$map  = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r['day'] ] = (int) $r['v'];
		}

		// Zero-fill every day in range for a continuous chart.
		$out    = array();
		$cursor = new DateTime( $from );
		$end    = new DateTime( $to );
		while ( $cursor <= $end ) {
			$d         = $cursor->format( 'Y-m-d' );
			$out[ $d ] = $map[ $d ] ?? 0;
			$cursor->modify( '+1 day' );
		}
		return $out;
	}

	/**
	 * Ranked games for a report.
	 *
	 * @param string $report plays|visits|likes|dislikes|rating|avg_session|trending.
	 * @param string $from   Y-m-d.
	 * @param string $to     Y-m-d.
	 * @param int    $limit  Rows.
	 * @return array<int,array>
	 */
	public static function top( $report, $from, $to, $limit = 25 ) {
		global $wpdb;
		$stats = self::stats_table();
		$daily = self::daily_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		$posts = $wpdb->posts;

		// Plays/visits are cumulative events → summed over the date range.
		// Likes/dislikes/rating are stateful (net, one per visitor) → read the
		// current unique totals from the stats table so they match each game.
		if ( in_array( $report, array( 'plays', 'visits' ), true ) || 'trending' === $report ) {
			$metric = 'trending' === $report ? 'plays' : $report;
			$rows   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.post_id, COALESCE(SUM(d.$metric),0) AS metric
					 FROM $daily d
					 INNER JOIN $posts p ON p.ID = d.post_id AND p.post_type = 'game'
					 WHERE d.day BETWEEN %s AND %s
					 GROUP BY d.post_id
					 HAVING metric > 0
					 ORDER BY metric DESC
					 LIMIT %d",
					$from,
					$to,
					$limit
				),
				ARRAY_A
			);
		} elseif ( 'likes' === $report || 'dislikes' === $report ) {
			$col  = $report;
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.post_id, s.$col AS metric
					 FROM $stats s
					 INNER JOIN $posts p ON p.ID = s.post_id AND p.post_type = 'game'
					 WHERE s.$col > 0
					 ORDER BY metric DESC
					 LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		} elseif ( 'rating' === $report ) {
			// Derived: like share of total votes, on a 0-5 scale.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.post_id, ROUND(s.likes / (s.likes + s.dislikes) * 5, 2) AS metric
					 FROM $stats s
					 INNER JOIN $posts p ON p.ID = s.post_id AND p.post_type = 'game'
					 WHERE (s.likes + s.dislikes) > 0
					 ORDER BY metric DESC, (s.likes + s.dislikes) DESC
					 LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		} elseif ( 'avg_session' === $report ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.post_id, ROUND(s.session_seconds / s.session_count) AS metric
					 FROM $stats s
					 INNER JOIN $posts p ON p.ID = s.post_id AND p.post_type = 'game'
					 WHERE s.session_count > 0
					 ORDER BY metric DESC
					 LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		} else {
			return array();
		}

		$out = array();
		foreach ( (array) $rows as $r ) {
			$pid    = (int) $r['post_id'];
			$life   = GameHub_Stats::get( $pid );
			$votes  = (int) $life['likes'] + (int) $life['dislikes'];
			$rating = $votes > 0 ? round( $life['likes'] / $votes * 5, 2 ) : 0;
			$out[]  = array(
				'post_id'     => $pid,
				'name'        => get_the_title( $pid ),
				'edit_link'   => get_edit_post_link( $pid, '' ),
				'view_link'   => get_permalink( $pid ),
				'metric'      => (float) $r['metric'],
				'plays'       => (int) $life['plays'],
				'visits'      => (int) $life['visits'],
				'likes'       => (int) $life['likes'],
				'dislikes'    => (int) $life['dislikes'],
				'rating'      => (float) $rating,
				'avg_session' => $life['session_count'] > 0 ? (int) round( $life['session_seconds'] / $life['session_count'] ) : 0,
			);
		}
		return $out;
	}

	/**
	 * The overall min day present in the data (for the calendar lower bound).
	 */
	public static function earliest_day() {
		global $wpdb;
		$daily = self::daily_table();
		$d     = $wpdb->get_var( "SELECT MIN(day) FROM $daily" );
		return $d ?: gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
	}

	/**
	 * Lifetime totals across the whole catalog (independent of range).
	 */
	public static function lifetime_totals() {
		global $wpdb;
		$stats = self::stats_table();
		$row   = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(plays),0) AS plays,
				COALESCE(SUM(visits),0) AS visits,
				COALESCE(SUM(likes),0) AS likes,
				COALESCE(SUM(dislikes),0) AS dislikes,
				COALESCE(SUM(session_seconds),0) AS session_seconds,
				COALESCE(SUM(session_count),0) AS session_count,
				COUNT(*) AS games
			FROM $stats",
			ARRAY_A
		);
		return array_map( 'intval', (array) $row );
	}
}
