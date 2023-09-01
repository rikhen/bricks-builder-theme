<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Compatibility {
	public function __construct() {}

	public static function register() {
		$instance = new self();

		// Learndash
		add_filter( 'learndash_course_grid_post_extra_course_grids', [ $instance, 'learndash_course_grid_load_assets' ], 10, 2 );

		// Litespeed
		add_action( 'litespeed_init', [ $instance, 'litespeed_no_cache' ] );

		// Weglot
		if ( function_exists( 'weglot_get_current_language' ) ) {
			add_action( 'init', [ $instance, 'weglot_disable_translation' ] );
    }

		// Polylang
		if ( function_exists( 'pll_the_languages' ) ) {
			add_filter( 'bricks/helpers/get_posts_args', [ $instance, 'polylang_get_posts_args' ] );
			add_filter( 'bricks/ajax/get_pages_args', [ $instance, 'polylang_get_posts_args' ] );
		}

		// Paid Memberships Pro: Restrict Bricks content (@since 1.5.4)
		if ( function_exists( 'pmpro_has_membership_access' ) ) {
			add_filter( 'bricks/render_with_bricks', [ $instance, 'pmpro_has_membership_access' ], 10, 1 );
		}

		// TranslatePress (@since 1.6)
		if ( bricks_is_builder() ) {
			// Not working as it runs too early (on plugins_loaded)
			// add_filter( 'trp_enable_translatepress', '__return_false' );

			add_filter( 'trp_allow_tp_to_run', '__return_false' );
			add_filter( 'trp_stop_translating_page', '__return_true' );

			// TranslatePress: Remove language switcher HTML in builder
			add_filter(
				'trp_floating_ls_html',
				function( $html ) {
					return '';
				}
			);
		}

		// Yith WooCommerce Product Add-Ons: dequeue script at priority 11 to make sure it's enqueued
		add_action( 'wp_enqueue_scripts', [ $instance, 'yith_wapo_dequeue_script' ], 11 );

		// WPML (@since 1.7)
		if ( function_exists( 'icl_object_id' ) ) {
			add_filter( 'bricks/database/bricks_get_all_templates_by_type_args', [ $instance, 'wpml_get_posts_args' ] );
		}
	}

	/**
	 * Learndash Course Grid Add One: Load assets if shortcode found
	 *
	 * wp_enqueue_scripts for learndash_course_grid_load_resources() only loads pre 2.0 legacy assets from [ld_course_list]
	 *
	 * @see class-compatibility.php integration for Elementor
	 *
	 * @since 1.7
	 */
	public function learndash_course_grid_load_assets( $course_grids, $post ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return $course_grids;
		}

		$bricks_data = Helpers::get_bricks_data( $post->ID, 'content' );

		if ( $bricks_data && is_array( $bricks_data ) ) {
			$bricks_data = json_encode( $bricks_data );
		}

		if ( function_exists( '\LearnDash\course_grid' ) ) {
			$tags = \LearnDash\course_grid()->skins->parse_content_shortcodes( $bricks_data, [] );
		}

		$course_grids[] = $tags;

		return $course_grids;
	}

	/**
	 * LiteSpeed Cache plugin: Ignore Bricks builder
	 *
	 * Tested with version 3.6.4
	 *
	 * @return void
	 */
	public function litespeed_no_cache() {
		if ( isset( $_GET['bricks'] ) && $_GET['bricks'] === 'run' ) {
			do_action( 'litespeed_disable_all', 'bricks editor' );
		}
	}

	/**
	 * Weglot: Disable Weglot translations inside the builder
	 *
	 * @since 1.8.6
	 *
	 * @return void
	 */
	public function weglot_disable_translation() {
		if ( isset( $_GET['bricks'] ) && $_GET['bricks'] == 'run' ) {
			add_filter( 'weglot_active_translation', '__return_false' );
		}
	}

	/**
	 * Polylang - set the query arg to get all the posts/pages languages
	 *
	 * @param array $query_args
	 * @return array
	 */
	public function polylang_get_posts_args( $query_args ) {

		if ( ! isset( $query_args['lang'] ) ) {
			$query_args['lang'] = 'all';
		}

		return $query_args;
	}

	/**
	 * Check if user has membership access to Bricks content in Helpers::render_with_bricks
	 *
	 * @since 1.5.4
	 */
	public function pmpro_has_membership_access( $render ) {
		return pmpro_has_membership_access();
	}

	/**
	 * Yith WooCommerce Product Add-Ons: Dequeue script on builder as it conflicts with Bricks drag & drop
	 *
	 * @since 1.6.2
	 */
	public function yith_wapo_dequeue_script() {
		if ( bricks_is_builder() && wp_script_is( 'yith_wapo_front', 'enqueued' ) ) {
			wp_dequeue_script( 'yith_wapo_front' );
		}
	}

	/**
	 * WPML: Add 'suppress_filters' => false query arg to get correct templates of currently viewed language.
	 *
	 * @param array $query_args
	 * @return array
	 *
	 * @since 1.7
	 */
	public function wpml_get_posts_args( $query_args ) {
		if ( ! isset( $query_args['suppress_filters'] ) ) {
			$query_args['suppress_filters'] = false;
		}

		return $query_args;
	}
}
