<?php
/**
 * Per-game metrics storage: lifetime counters + per-day rollups.
 *
 * Two custom tables (not post meta) keep high-frequency writes off the posts
 * tables and make aggregate/time-series queries cheap:
 *
 *   {prefix}gh_stats  — one row per game, lifetime counters.
 *   {prefix}gh_daily  — one row per game per day, for calendars & date ranges.
 *   {prefix}gh_votes  — one row per (game, voter) so likes/dislikes dedupe.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_Stats {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Remove stats rows when a game is permanently deleted.
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
	}

	public static function stats_table() {
		global $wpdb;
		return $wpdb->prefix . 'gh_stats';
	}

	public static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'gh_daily';
	}

	public static function votes_table() {
		global $wpdb;
		return $wpdb->prefix . 'gh_votes';
	}

	/**
	 * Create/upgrade the custom tables. Safe to run repeatedly (dbDelta).
	 */
	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$stats   = self::stats_table();
		$daily   = self::daily_table();
		$votes   = self::votes_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE $stats (
				post_id BIGINT UNSIGNED NOT NULL,
				plays BIGINT UNSIGNED NOT NULL DEFAULT 0,
				visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				likes INT UNSIGNED NOT NULL DEFAULT 0,
				dislikes INT UNSIGNED NOT NULL DEFAULT 0,
				rating_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
				rating_count INT UNSIGNED NOT NULL DEFAULT 0,
				session_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
				session_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (post_id),
				KEY plays (plays),
				KEY visits (visits),
				KEY likes (likes)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $daily (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT UNSIGNED NOT NULL,
				day DATE NOT NULL,
				plays BIGINT UNSIGNED NOT NULL DEFAULT 0,
				visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				likes INT UNSIGNED NOT NULL DEFAULT 0,
				dislikes INT UNSIGNED NOT NULL DEFAULT 0,
				session_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
				session_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY post_day (post_id, day),
				KEY day (day)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $votes (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT UNSIGNED NOT NULL,
				voter_hash CHAR(64) NOT NULL DEFAULT '',
				vote VARCHAR(8) NOT NULL DEFAULT '',
				rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY post_voter (post_id, voter_hash),
				KEY vote (vote)
			) $charset;"
		);
	}

	/**
	 * Guarantee a stats row exists for a game.
	 *
	 * @param int $post_id Game post ID.
	 */
	public static function ensure_row( $post_id ) {
		global $wpdb;
		$table = self::stats_table();
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $table (post_id) VALUES (%d)",
				(int) $post_id
			)
		);
	}

	/**
	 * Return the full lifetime stats row for a game (zeros if none yet).
	 *
	 * @param int $post_id Game post ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		global $wpdb;
		$table = self::stats_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE post_id = %d", (int) $post_id ),
			ARRAY_A
		);
		$defaults = array(
			'post_id'         => (int) $post_id,
			'plays'           => 0,
			'visits'          => 0,
			'likes'           => 0,
			'dislikes'        => 0,
			'rating_sum'      => 0,
			'rating_count'    => 0,
			'session_seconds' => 0,
			'session_count'   => 0,
		);
		if ( ! $row ) {
			return $defaults;
		}
		return array_map( 'intval', wp_parse_args( $row, $defaults ) );
	}

	/**
	 * Increment a lifetime counter and the matching per-day rollup atomically.
	 *
	 * @param int    $post_id Game post ID.
	 * @param string $column  One of plays|visits (session/likes have dedicated paths).
	 * @param int    $amount  Increment amount (default 1).
	 * @return int|false New lifetime value, or false on failure.
	 */
	public static function bump( $post_id, $column, $amount = 1 ) {
		$allowed = array( 'plays', 'visits' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return false;
		}
		self::ensure_row( $post_id );
		self::bump_daily( $post_id, array( $column => (int) $amount ) );

		global $wpdb;
		$table = self::stats_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET $column = $column + %d, updated_at = %s WHERE post_id = %d",
				(int) $amount,
				current_time( 'mysql' ),
				(int) $post_id
			)
		);
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT $column FROM $table WHERE post_id = %d", (int) $post_id )
		);
	}

	/**
	 * Add a session duration (seconds) to lifetime + daily totals.
	 *
	 * @param int $post_id Game post ID.
	 * @param int $seconds Session duration in seconds.
	 */
	public static function add_session( $post_id, $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds <= 0 ) {
			return;
		}
		self::ensure_row( $post_id );
		self::bump_daily( $post_id, array( 'session_seconds' => $seconds, 'session_count' => 1 ) );

		global $wpdb;
		$table = self::stats_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table
				 SET session_seconds = session_seconds + %d,
				     session_count = session_count + 1,
				     updated_at = %s
				 WHERE post_id = %d",
				$seconds,
				current_time( 'mysql' ),
				(int) $post_id
			)
		);
	}

	/**
	 * Apply signed deltas to like/dislike lifetime counters and daily rollup.
	 * Callers compute the deltas from the votes table transition.
	 *
	 * @param int $post_id       Game post ID.
	 * @param int $like_delta    Signed like delta.
	 * @param int $dislike_delta Signed dislike delta.
	 */
	public static function apply_vote_delta( $post_id, $like_delta, $dislike_delta ) {
		self::ensure_row( $post_id );
		global $wpdb;
		$table = self::stats_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table
				 SET likes = GREATEST(CAST(likes AS SIGNED) + %d, 0),
				     dislikes = GREATEST(CAST(dislikes AS SIGNED) + %d, 0),
				     updated_at = %s
				 WHERE post_id = %d",
				(int) $like_delta,
				(int) $dislike_delta,
				current_time( 'mysql' ),
				(int) $post_id
			)
		);
		$daily = array();
		if ( 0 !== (int) $like_delta ) {
			$daily['likes'] = max( 0, (int) $like_delta );
		}
		if ( 0 !== (int) $dislike_delta ) {
			$daily['dislikes'] = max( 0, (int) $dislike_delta );
		}
		if ( $daily ) {
			self::bump_daily( $post_id, $daily );
		}
	}

	/**
	 * Add a rating (1-5) to the running sum/count.
	 *
	 * @param int $post_id     Game post ID.
	 * @param int $rating      New rating value 1-5.
	 * @param int $prev_rating Previous rating from same voter (0 if none).
	 */
	public static function apply_rating( $post_id, $rating, $prev_rating = 0 ) {
		self::ensure_row( $post_id );
		global $wpdb;
		$table       = self::stats_table();
		$sum_delta   = (int) $rating - (int) $prev_rating;
		$count_delta = $prev_rating > 0 ? 0 : 1;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table
				 SET rating_sum = GREATEST(CAST(rating_sum AS SIGNED) + %d, 0),
				     rating_count = rating_count + %d,
				     updated_at = %s
				 WHERE post_id = %d",
				$sum_delta,
				$count_delta,
				current_time( 'mysql' ),
				(int) $post_id
			)
		);
	}

	/**
	 * Upsert one or more per-day counters for a game.
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $cols    Map of column => positive increment.
	 */
	private static function bump_daily( $post_id, $cols ) {
		if ( empty( $cols ) ) {
			return;
		}
		global $wpdb;
		$table   = self::daily_table();
		$allowed = array( 'plays', 'visits', 'likes', 'dislikes', 'session_seconds', 'session_count' );
		$day     = current_time( 'Y-m-d' );

		$set_cols   = array( 'post_id', 'day' );
		$set_vals   = array( '%d', '%s' );
		$params     = array( (int) $post_id, $day );
		$update_sql = array();

		foreach ( $cols as $col => $inc ) {
			if ( ! in_array( $col, $allowed, true ) ) {
				continue;
			}
			$set_cols[]   = $col;
			$set_vals[]   = '%d';
			$params[]     = (int) $inc;
			$update_sql[] = "$col = $col + VALUES($col)";
		}
		if ( empty( $update_sql ) ) {
			return;
		}

		$sql = "INSERT INTO $table (" . implode( ',', $set_cols ) . ') VALUES ('
			. implode( ',', $set_vals ) . ') ON DUPLICATE KEY UPDATE '
			. implode( ',', $update_sql );

		$wpdb->query( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Directly set lifetime counters (admin override).
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $data    Map of column => value (plays|visits|likes|dislikes|session_seconds|session_count).
	 */
	public static function set_counters( $post_id, $data ) {
		self::ensure_row( $post_id );
		$allowed = array( 'plays', 'visits', 'likes', 'dislikes', 'session_seconds', 'session_count' );
		$set     = array();
		foreach ( $allowed as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$set[ $col ] = max( 0, (int) $data[ $col ] );
			}
		}
		if ( empty( $set ) ) {
			return;
		}
		$set['updated_at'] = current_time( 'mysql' );

		global $wpdb;
		$wpdb->update( self::stats_table(), $set, array( 'post_id' => (int) $post_id ) );
	}

	/**
	 * The recorded vote row for a (game, voter) pair.
	 *
	 * @param int    $post_id    Game post ID.
	 * @param string $voter_hash Voter hash.
	 * @return array|null
	 */
	public static function get_vote( $post_id, $voter_hash ) {
		global $wpdb;
		$table = self::votes_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE post_id = %d AND voter_hash = %s",
				(int) $post_id,
				(string) $voter_hash
			),
			ARRAY_A
		);
	}

	public function on_delete_post( $post_id ) {
		if ( 'game' !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( self::stats_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
		$wpdb->delete( self::daily_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
		$wpdb->delete( self::votes_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}
}
