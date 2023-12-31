<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Search {
	public function __construct() {
		if ( Database::get_setting( 'searchResultsQueryBricksData', false ) ) {
			// Checking if query contains "s" var @since 1.5.7 (CU #3pxbtcp)
			add_filter( 'posts_join', [ $this, 'search_postmeta_table' ], 10, 2 );
			add_filter( 'posts_where', [ $this, 'modify_search_for_postmeta' ], 10, 2 );
			add_filter( 'posts_distinct', [ $this, 'search_distinct' ], 10, 2 );
		}

		// Exclude Bricks templates if not explicitly enabled via "Public templates" Bricks setting (@since 1.5.6)
		if ( ! Database::get_setting( 'publicTemplates', false ) ) {
			add_action( 'pre_get_posts', [ $this, 'exclude_templates' ] );

			// We shouldn't add this anymore as search query should marked as is_main_archive_query since 1.8.6
			// Set priority to 9 to run before the WooCommerce query_vars hook (CU #3pxbtcp)
			// add_filter( 'bricks/posts/query_vars', [ $this, 'exclude_templates_bricks_query' ], 9, 3 );
		}
	}

	/**
	 * Exclude Bricks templates from the search results (render with WordPress)
	 *
	 * @since 1.5.6
	 */
	public function exclude_templates( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$search_post_types = get_post_types( [ 'exclude_from_search' => false ] );

		if ( is_array( $search_post_types ) && in_array( BRICKS_DB_TEMPLATE_SLUG, $search_post_types ) ) {
			unset( $search_post_types[ BRICKS_DB_TEMPLATE_SLUG ] );

			$query->set( 'post_type', array_keys( $search_post_types ) );
		}
	}

	/**
	 * Exclude Bricks templates from the search results (when using Bricks query in a search template)
	 *
	 * @since 1.5.6
	 */
	public function exclude_templates_bricks_query( $query_vars, $settings, $element_id ) {
		if ( ! is_search() ) {
			return $query_vars;
		}

		if ( empty( $query_vars['post_type'] ) || $query_vars['post_type'] === 'any' ) {
			$search_post_types = get_post_types( [ 'exclude_from_search' => false ] );

			unset( $search_post_types[ BRICKS_DB_TEMPLATE_SLUG ] );

			$query_vars['post_type'] = array_keys( $search_post_types );
		} elseif ( is_array( $query_vars['post_type'] ) ) {
			$index = array_search( BRICKS_DB_TEMPLATE_SLUG, $query_vars['post_type'] );

			if ( $index !== false ) {
				unset( $query_vars['post_type'][ $index ] );
			}
		}

		return $query_vars;
	}

	/**
	 * Helper: Check if is_search() OR Bricks infinite scroll REST API search results
	 *
	 * @since 1.5.7
	 */
	public function is_search( $query ) {
		// WordPress search results
		if ( is_search() ) {
			return true;
		}

		// Bricks: Infinite scroll search results
		if ( bricks_is_rest_call() && isset( $query->query_vars['s'] ) && ! empty( $query->query_vars['s'] ) ) {
			return true;
		}
	}

	/**
	 * Search 'posts' and 'postmeta' tables
	 *
	 * https://adambalee.com/search-wordpress-by-custom-fields-without-a-plugin/
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
	 *
	 * @since 1.3.7
	 */
	public function search_postmeta_table( $join, $query ) {
		global $wpdb;

		if ( $this->is_search( $query ) ) {
			$join .= ' LEFT JOIN ' . $wpdb->postmeta . ' bricksdata ON ' . $wpdb->posts . '.ID = bricksdata.post_id ';
		}

		return $join;
	}

	/**
	 * Modify search query
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
	 *
	 * @since 1.3.7
	 */
	public function modify_search_for_postmeta( $where, $query ) {
		global $pagenow, $wpdb;

		if ( $this->is_search( $query ) ) {
			$where = preg_replace(
				'/\(\s*' . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
				'(' . $wpdb->posts . '.post_title LIKE $1) OR (bricksdata.meta_value LIKE $1)',
				$where
			);
		}

		return $where;
	}

	/**
	 * Prevent duplicates
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_distinct
	 *
	 * @since 1.3.7
	 */
	public function search_distinct( $where, $query ) {
		global $wpdb;

		if ( $this->is_search( $query ) ) {
			return 'DISTINCT';
		}

		return $where;
	}
}
