<?php
/**
 * AI content generation for games, categories, and the homepage.
 *
 * For a game/category it searches Google (SerpApi), uses the top results as
 * reference, and asks OpenAI to write SEO HTML (starts at H2, 2-3 headings,
 * natural internal links from the site's own pages, an optional FAQ, and — for
 * categories — a short linked list of games). Generated items are flagged so
 * bulk runs are resumable and skip finished work.
 *
 * @package GameHub\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GameHub_AI {

	const META_GENERATED = '_ghub_ai_generated';

	/* ---- Config ---------------------------------------------------------- */

	private static function cfg( $key ) {
		$s = GameHub_Settings::get();
		return trim( (string) ( $s[ $key ] ?? '' ) );
	}
	public static function api_key() {
		return self::cfg( 'openai_api_key' );
	}
	public static function model() {
		return self::cfg( 'openai_model' ) ?: 'gpt-4o-mini';
	}
	public static function serp_key() {
		return self::cfg( 'serpapi_key' );
	}
	public static function ready() {
		return '' !== self::api_key();
	}

	/* ---- External APIs --------------------------------------------------- */

	/**
	 * Top 5 Google organic results (title, snippet, link) via SerpApi.
	 * Best-effort: returns an empty array on any failure (generation continues).
	 */
	public static function fetch_serp( $keyword ) {
		$key = self::serp_key();
		if ( '' === $key ) {
			return array();
		}
		$url = add_query_arg(
			array( 'engine' => 'google', 'q' => $keyword, 'num' => 10, 'hl' => 'en', 'api_key' => $key ),
			'https://serpapi.com/search.json'
		);
		$res = wp_remote_get( $url, array( 'timeout' => 25 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		foreach ( (array) ( $body['organic_results'] ?? array() ) as $r ) {
			$out[] = array(
				'title'   => (string) ( $r['title'] ?? '' ),
				'snippet' => (string) ( $r['snippet'] ?? '' ),
				'link'    => (string) ( $r['link'] ?? '' ),
			);
			if ( count( $out ) >= 5 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Call OpenAI chat completions. Returns the content string or a WP_Error.
	 */
	public static function openai( $system, $user ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'no_key', __( 'OpenAI API key is not set (Games → Settings).', 'gamehub-engine' ) );
		}
		$res = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => self::model(),
						'messages' => array(
							array( 'role' => 'system', 'content' => $system ),
							array( 'role' => 'user', 'content' => $user ),
						),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$msg = $body['error']['message'] ?? sprintf( /* translators: HTTP code */ __( 'OpenAI returned HTTP %d.', 'gamehub-engine' ), $code );
			return new WP_Error( 'openai', $msg );
		}
		$content = $body['choices'][0]['message']['content'] ?? '';
		$content = self::clean_html( $content );
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'OpenAI returned empty content.', 'gamehub-engine' ) );
		}
		return $content;
	}

	/* ---- Helpers --------------------------------------------------------- */

	/**
	 * Tidy model output: strip code fences / wrappers, convert Markdown to HTML
	 * when the model ignored the HTML instruction, and optionally demote H1→H2.
	 */
	private static function clean_html( $html, $demote_h1 = true ) {
		$html = trim( (string) $html );
		$html = preg_replace( '/^```[a-z]*\s*/i', '', $html );
		$html = preg_replace( '/\s*```$/', '', $html );
		$html = preg_replace( '#</?(html|body|head|!doctype)[^>]*>#i', '', $html );
		$html = trim( $html );

		// If the output has no block HTML, treat it as Markdown and convert.
		if ( ! preg_match( '/<(h1|h2|h3|p|ul|ol|div)\b/i', $html ) ) {
			$html = self::markdown_to_html( $html );
		}
		if ( $demote_h1 ) {
			$html = preg_replace( '#<(/?)h1(\s[^>]*)?>#i', '<$1h2$2>', $html );
		}
		return trim( $html );
	}

	/**
	 * Minimal Markdown → HTML for headings, bold/italic, links, lists, paragraphs.
	 */
	private static function markdown_to_html( $md ) {
		$md = str_replace( array( "\r\n", "\r" ), "\n", (string) $md );
		$md = preg_replace( '/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $md );
		$md = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $md );
		$md = preg_replace( '/__([^_]+)__/', '<strong>$1</strong>', $md );
		$md = preg_replace( '/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $md );

		$out     = '';
		$in_list = false;
		foreach ( explode( "\n", $md ) as $line ) {
			$t = trim( $line );
			if ( '' === $t ) {
				if ( $in_list ) {
					$out    .= '</ul>';
					$in_list = false;
				}
				continue;
			}
			if ( preg_match( '/^###\s+(.*)/', $t, $m ) ) {
				$out .= ( $in_list ? '</ul>' : '' ) . '<h3>' . $m[1] . '</h3>';
				$in_list = false;
			} elseif ( preg_match( '/^##\s+(.*)/', $t, $m ) || preg_match( '/^#\s+(.*)/', $t, $m ) ) {
				$out .= ( $in_list ? '</ul>' : '' ) . '<h2>' . $m[1] . '</h2>';
				$in_list = false;
			} elseif ( preg_match( '/^[-*]\s+(.*)/', $t, $m ) ) {
				if ( ! $in_list ) {
					$out    .= '<ul>';
					$in_list = true;
				}
				$out .= '<li>' . $m[1] . '</li>';
			} else {
				$out    .= ( $in_list ? '</ul>' : '' ) . '<p>' . $t . '</p>';
				$in_list = false;
			}
		}
		if ( $in_list ) {
			$out .= '</ul>';
		}
		return $out;
	}

	/**
	 * Internal links the model may use (anchor => URL): popular categories and
	 * related/popular games.
	 */
	private static function interlinks( $type, $id ) {
		$links = array();
		if ( function_exists( 'ghub_categories_by_popularity' ) ) {
			foreach ( ghub_categories_by_popularity( 12 ) as $t ) {
				$l = get_term_link( $t );
				if ( ! is_wp_error( $l ) ) {
					$links[ $t->name ] = $l;
				}
			}
		}
		$args = array(
			'post_type'      => 'game',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'no_found_rows'  => true,
			'ghub_order'     => 'popular',
		);
		if ( 'game' === $type ) {
			$cats = wp_get_object_terms( $id, 'game_category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $cats ) && $cats ) {
				$args['post__not_in'] = array( (int) $id );
				$args['tax_query']    = array( array( 'taxonomy' => 'game_category', 'field' => 'term_id', 'terms' => $cats ) );
			}
		} elseif ( 'category' === $type ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'game_category', 'field' => 'term_id', 'terms' => array( (int) $id ) ) );
		}
		foreach ( get_posts( $args ) as $p ) {
			$links[ get_the_title( $p ) ] = get_permalink( $p );
		}
		return $links;
	}

	private static function links_block( $links ) {
		$lines = array();
		foreach ( $links as $anchor => $url ) {
			$lines[] = '- ' . $anchor . ' => ' . $url;
		}
		return implode( "\n", array_slice( $lines, 0, 24 ) );
	}

	private static function serp_block( $serp ) {
		if ( empty( $serp ) ) {
			return '(no external references available)';
		}
		$lines = array();
		foreach ( $serp as $r ) {
			$lines[] = '- ' . $r['title'] . ': ' . $r['snippet'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * A per-item angle so content structure varies instead of repeating.
	 */
	private static function variation( $id, $type ) {
		$game = array(
			'Open with the theme/setting and objective, then the moment-to-moment gameplay, then a strategy or two.',
			'Lead with what makes it addictive and fun, then controls and modes, then who will love it.',
			'Paint a quick vivid scene, then explain the core mechanics, then share pro tips.',
			'Start with the main challenge, then the standout features, then replay value and progression.',
			'Hook with a bold one-liner, then how rounds/levels flow, then advanced tactics.',
			'Frame it around a goal players chase, then unique twists, then how to get better fast.',
			'Begin with the vibe and audience, then gameplay loop, then what to try next.',
		);
		$cat = array(
			'Explain what defines this genre, then why it is popular, then how to pick a game to start.',
			'Lead with the appeal and variety, then common mechanics, then standout titles to try.',
			'Open with who enjoys these games, then what to expect, then tips for newcomers.',
			'Start with the fantasy/experience these games deliver, then range of styles, then favorites.',
			'Hook with a strong statement about the genre, then sub-styles within it, then where to begin.',
		);
		$list = ( 'category' === $type ) ? $cat : $game;
		return $list[ (int) $id % count( $list ) ];
	}

	private static function system_prompt( $site ) {
		return "You are an expert SEO copywriter for {$site}, an online games website. "
			. 'Output raw HTML only — never Markdown, never code fences, never <html>/<head>/<body> tags. '
			. 'Use real HTML tags for everything: <h2> and <h3> for headings, <p> for paragraphs, <strong> for bold, <ul>/<li> for lists, <a href> for links. '
			. 'Do NOT use "#", "##", "**", or "- " Markdown syntax. '
			. 'Always start with an <h2> (never <h1>). Keep 2-3 heading sections. '
			. 'Add internal links ONLY where genuinely relevant, using the exact URLs provided — never invent URLs and never force links. '
			. 'Do not fabricate facts, ratings, or download claims. Write in a natural, engaging tone.';
	}

	/* ---- Generators ------------------------------------------------------ */

	public static function generate_game( $post_id, $force = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'game' !== $post->post_type ) {
			return new WP_Error( 'bad', __( 'Not a game.', 'gamehub-engine' ) );
		}
		if ( ! $force && get_post_meta( $post_id, self::META_GENERATED, true ) ) {
			return 'skipped';
		}
		$site = get_bloginfo( 'name' );
		$name = get_the_title( $post );
		$cats = wp_get_object_terms( $post_id, 'game_category', array( 'fields' => 'names' ) );
		$cat  = ( ! is_wp_error( $cats ) && $cats ) ? $cats[0] : '';
		$serp  = self::fetch_serp( $name . ' game' );
		$faq   = ( 0 === ( (int) $post_id % 3 ) );
		$angle = self::variation( $post_id, 'game' );

		$user = "Write unique on-page content for the game \"{$name}\"" . ( $cat ? " ({$cat})" : '' ) . " on {$site}.\n\n"
			. "Top Google results for reference (paraphrase, do NOT copy):\n" . self::serp_block( $serp ) . "\n\n"
			. "Internal links you may use where relevant (anchor => URL):\n" . self::links_block( self::interlinks( 'game', $post_id ) ) . "\n\n"
			. "Requirements:\n"
			. "- Start with an <h2>. Use 2-3 headings total (<h2>/<h3>).\n"
			. "- IMPORTANT: pick heading titles tailored to THIS specific game. Do NOT reuse a fixed template or the same generic headings across games (avoid always using 'How to Play', 'Features', 'Tips'). Vary the structure, section order, angle, and wording so each game reads uniquely.\n"
			. "- Angle to lean into for this one: {$angle}\n"
			. "- Add 2-4 natural internal links from the list above where they fit.\n"
			. ( $faq ? "- Add an FAQ section (choose your own <h2> heading) with 2-3 <h3> questions and <p> answers.\n" : "- Do NOT add an FAQ section.\n" )
			. '- Output HTML only.';

		$content = self::openai( self::system_prompt( $site ), $user );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_kses_post( $content ) ) );
		update_post_meta( $post_id, self::META_GENERATED, 1 );
		return 'generated';
	}

	public static function generate_category( $term_id, $force = false ) {
		$term = get_term( (int) $term_id, 'game_category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'bad', __( 'Not a category.', 'gamehub-engine' ) );
		}
		if ( ! $force && get_term_meta( $term_id, self::META_GENERATED, true ) ) {
			return 'skipped';
		}
		$site  = get_bloginfo( 'name' );
		$name  = $term->name;
		$serp  = self::fetch_serp( $name . ' online games' );
		$links = self::interlinks( 'category', $term_id );
		$faq   = ( 0 === ( (int) $term_id % 3 ) );

		$games = array();
		foreach ( array_slice( $links, 0, 10 ) as $anchor => $url ) {
			$games[] = $anchor . ' => ' . $url;
		}

		$angle = self::variation( $term_id, 'category' );

		$user = "Write unique on-page content for the \"{$name}\" category page on {$site}.\n\n"
			. "Top Google results for reference (paraphrase, do NOT copy):\n" . self::serp_block( $serp ) . "\n\n"
			. "Internal links you may use (anchor => URL):\n" . self::links_block( $links ) . "\n\n"
			. "Requirements:\n"
			. "- Start with an <h2>. Use 2-3 headings total (<h2>/<h3>).\n"
			. "- IMPORTANT: pick heading titles tailored to THIS category. Do NOT reuse the same generic headings across categories. Vary structure, angle, and wording so each category reads uniquely.\n"
			. "- Angle to lean into for this one: {$angle}\n"
			. "- Near the end, include a short heading (your own wording) followed by a <ul> listing a few games as <a href> links using the exact URLs above.\n"
			. "- Add a few natural internal links to related categories where relevant.\n"
			. ( $faq ? "- Add an FAQ section (choose your own <h2> heading) with 2-3 <h3> questions and <p> answers.\n" : "- Do NOT add an FAQ section.\n" )
			. '- Output HTML only.';

		$content = self::openai( self::system_prompt( $site ), $user );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		wp_update_term( $term_id, 'game_category', array( 'description' => wp_kses_post( $content ) ) );
		update_term_meta( $term_id, self::META_GENERATED, 1 );
		return 'generated';
	}

	public static function generate_homepage() {
		$site    = get_bloginfo( 'name' );
		$tagline = get_bloginfo( 'description' );
		$serp    = self::fetch_serp( $site . ' free online games' );
		$links   = self::interlinks( 'home', 0 );

		$user = "Write the homepage content block for {$site}" . ( $tagline ? " ({$tagline})" : '' ) . ".\n\n"
			. "Top Google results for reference (paraphrase, do NOT copy):\n" . self::serp_block( $serp ) . "\n\n"
			. "Internal category links you may use (anchor => URL):\n" . self::links_block( $links ) . "\n\n"
			. "Requirements:\n- Start with an <h1> containing the site name and a benefit (this is the homepage heading).\n- Then 2-3 <h2> sections describing the site, popular categories, and why to play here.\n- Link to a few categories naturally using the URLs above.\n- No FAQ. Output HTML only.";

		// Homepage keeps its H1 (it's the page heading), so bypass the H1->H2 demotion.
		$system  = self::system_prompt( $site ) . ' For THIS homepage task only, begin with a single <h1>, then use <h2>/<h3>.';
		$key     = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'no_key', __( 'OpenAI API key is not set.', 'gamehub-engine' ) );
		}
		$res = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'model'    => self::model(),
						'messages' => array(
							array( 'role' => 'system', 'content' => $system ),
							array( 'role' => 'user', 'content' => $user ),
						),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'openai', $body['error']['message'] ?? ( 'OpenAI HTTP ' . $code ) );
		}
		$content = self::clean_html( (string) ( $body['choices'][0]['message']['content'] ?? '' ), false );
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'OpenAI returned empty content.', 'gamehub-engine' ) );
		}

		$s                     = GameHub_Settings::get();
		$s['homepage_content'] = wp_kses_post( $content );
		update_option( GameHub_Settings::OPTION, $s );
		return $content;
	}

	/* ---- Pending lists (for resumable bulk) ------------------------------ */

	public static function pending_games() {
		return get_posts(
			array(
				'post_type'      => 'game',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array( 'key' => self::META_GENERATED, 'compare' => 'NOT EXISTS' ),
				),
			)
		);
	}

	/**
	 * Re-run formatting (Markdown→HTML) on already-generated content — no API
	 * cost. Returns the number of items changed.
	 */
	public static function reformat_existing( $type ) {
		$fixed = 0;
		if ( 'games' === $type ) {
			$ids = get_posts(
				array(
					'post_type'      => 'game',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array( array( 'key' => self::META_GENERATED, 'compare' => 'EXISTS' ) ),
				)
			);
			foreach ( $ids as $id ) {
				$c = (string) get_post_field( 'post_content', $id );
				$n = self::clean_html( $c );
				if ( $n !== $c && '' !== $n ) {
					wp_update_post( array( 'ID' => $id, 'post_content' => wp_kses_post( $n ) ) );
					$fixed++;
				}
			}
		} elseif ( 'categories' === $type ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'game_category',
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_query' => array( array( 'key' => self::META_GENERATED, 'compare' => 'EXISTS' ) ),
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $id ) {
					$t = get_term( $id, 'game_category' );
					if ( ! $t || is_wp_error( $t ) ) {
						continue;
					}
					$c = (string) $t->description;
					$n = self::clean_html( $c );
					if ( $n !== $c && '' !== $n ) {
						wp_update_term( $id, 'game_category', array( 'description' => wp_kses_post( $n ) ) );
						$fixed++;
					}
				}
			}
		}
		return $fixed;
	}

	public static function pending_categories() {
		$terms = get_terms( array( 'taxonomy' => 'game_category', 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$terms,
				static function ( $id ) {
					return ! get_term_meta( $id, self::META_GENERATED, true );
				}
			)
		);
	}
}
