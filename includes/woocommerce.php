<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Woocommerce {
	public static $product_categories = [];
	public static $product_tags       = [];
	public static $is_active          = false;

	public function __construct() {
		self::$is_active = self::is_woocommerce_active();

		if ( ! self::$is_active ) {
			// Make sure the WooCommerce templates are not loaded, in case CPT "product" is used
			add_filter( 'template_include', [ $this, 'no_woo_template_include' ], 1001 );

			return;
		}

		add_action( 'after_setup_theme', [ $this, 'add_theme_support' ] );

		add_action( 'init', [ $this, 'set_products_terms' ] );

		add_action( 'init', [ $this, 'init_elements' ] );

		add_action( 'init', [ $this, 'init_theme_styles' ], 9 );

		add_action( 'wp', [ $this, 'maybe_set_template_preview_content' ], 9 );

		// Disable default bricks title for WooCommerce pages if template is active (@since 1.8)
		add_filter( 'bricks/default_page_title', [ $this, 'default_page_title' ], 10, 2 );

		add_filter( 'bricks/element/maybe_set_aria_current_page', [ $this, 'maybe_set_aria_current_page' ], 10, 2 );

		add_filter( 'bricks/builder/supported_post_types', [ $this, 'bypass_builder_post_type_check' ], 10, 2 );

		// On the builder hook to set the panel elements first element category
		add_filter( 'bricks/builder/first_element_category', [ $this, 'set_first_element_category' ], 10, 3 );

		// Builder/Database: set the post id used to localize the builder data -> is_shop() page
		add_filter( 'bricks/builder/data_post_id', [ $this, 'maybe_set_post_id' ], 10, 1 );

		// Add Template Types to control options
		add_filter( 'bricks/setup/control_options', [ $this, 'add_template_types' ] );

		// Remove the template conditions for the Cart & Checkout template parts
		add_filter( 'builder/settings/template/controls_data', [ $this, 'remove_template_conditions' ], 9 );

		// During the active_templates search set proper content_type
		add_filter( 'bricks/database/content_type', [ $this, 'set_content_type' ], 10, 2 );

		// Remove default WooCommerce styles
		add_filter( 'woocommerce_enqueue_styles', '__return_false' );

		// Add WooCommerce specific link selectors to allow Theme Styles link styles to apply to WooCommerce elements (@since 1.5.7)
		add_filter( 'bricks/link_css_selectors', [ $this, 'link_css_selectors' ], 10, 1 );

		// Enqueue Bricks WooCommerce custom styles
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 10 );

		// add_action( 'wp_enqueue_scripts', [ $this, 'unload_photoswipe5_lightbox_assets' ] );

		// Product archive hooks
		add_action( 'bricks/archive_product/before', [ $this, 'setup_query' ], 10, 2 );
		add_action( 'bricks/archive_product/after', [ $this, 'reset_query' ], 10, 2 );

		// Mini cart fragments
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'update_mini_cart' ], 10, 1 );

		// Breadcrumb separator
		add_filter( 'woocommerce_breadcrumb_defaults', [ $this, 'breadcrumb_separator' ] );
		add_filter( 'woocommerce_get_breadcrumb', [ $this, 'add_breadcrumbs_from_filters' ], 10, 2 );

		/**
		 * Quantity input field: Add plus/minus buttons
		 *
		 * @since 1.7 - Render button after the input in order to hide it if input[type="hidden"]
		 */
		add_action( 'woocommerce_after_quantity_input_field', [ $this, 'quantity_input_field_add_minus_button' ] );
		add_action( 'woocommerce_after_quantity_input_field', [ $this, 'quantity_input_field_add_plus_button' ] );

		// Product tabs: Remove panel titles
		add_filter( 'woocommerce_product_description_heading', '__return_false' );
		add_filter( 'woocommerce_product_additional_information_heading', '__return_false' );
		add_filter( 'woocommerce_reviews_title', '__return_false' );

		// On Sale HTML
		add_filter( 'woocommerce_sale_flash', [ $this, 'badge_sale' ], 10, 3 );

		// Single product
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'badge_new' ], 9 );
		add_filter( 'woocommerce_product_review_comment_form_args', [ $this, 'product_review_comment_form_args' ] );

		// Query loop: using the query loop builder for products
		add_filter( 'bricks/posts/merge_query', [ $this, 'maybe_merge_query' ], 10, 2 );
		add_filter( 'bricks/posts/query_vars', [ $this, 'set_products_query_vars' ], 10, 3 );

		// Query: Add Woo Cart contents
		add_filter( 'bricks/setup/control_options', [ $this, 'add_control_options' ], 10, 1 );
		add_filter( 'bricks/query/run', [ $this, 'run_cart_query' ], 10, 2 );
		add_filter( 'bricks/query/loop_object', [ $this, 'set_loop_object' ], 10, 3 );

		// TODO: Needed?
		add_filter( 'bricks/query/loop_object_id', [ $this, 'set_loop_object_id' ], 10, 3 );
		add_filter( 'bricks/query/loop_object_type', [ $this, 'set_loop_object_type' ], 10, 3 );

		add_filter( 'post_class', [ $this, 'post_class' ], 10, 3 );

		// Checkout: Make sure the fields removed by the user inside the builder are not required during the checkout process (@since 1.5.7)
		add_filter( 'woocommerce_checkout_fields', [ $this, 'woocommerce_checkout_fields' ], 99, 1 );

		// @since 1.6.1 - AJAX Add to cart
		if ( self::enabled_ajax_add_to_cart() ) {
			add_action( 'wc_ajax_bricks_add_to_cart', [ $this, 'add_to_cart' ] );
			add_action( 'wc_ajax_nopriv_bricks_add_to_cart', [ $this, 'add_to_cart' ] );
		}

		// @since 1.7 - Remove / Restore Woo native hook actions when using {do_action}
		add_action( 'bricks/dynamic_data/before_do_action', [ $this, 'maybe_remove_woo_hook_actions' ], 10, 4 );
		add_action( 'bricks/dynamic_data/after_do_action', [ $this, 'maybe_restore_woo_hook_actions' ], 10, 5 );

		// @since 1.8.1 - Bricks WooCommerce Notice
		self::maybe_remove_native_woocommerce_notices_hooks();

	}

	/**
	 * Checkout: Make sure the removed billing/shipping fields in the WooCommerce checkout customer details element are set to be not required
	 *
	 * @since 1.5.7
	 */
	public function woocommerce_checkout_fields( $fields ) {
		if ( ! is_checkout() ) {
			return $fields;
		}

		$templates = Templates::get_templates_by_type( 'wc_form_checkout' );

		if ( empty( $templates[0] ) ) {
			return $fields;
		}

		$elements = get_post_meta( $templates[0], BRICKS_DB_PAGE_CONTENT, true );

		if ( empty( $elements ) ) {
			return $fields;
		}

		$customer_details_settings = false;

		// Get settings of "Checkout customer details" element
		foreach ( $elements as $element ) {
			if ( $element['name'] === 'woocommerce-checkout-customer-details' && ! empty( $element['settings'] ) ) {
				$customer_details_settings = $element['settings'];
			}
		}

		// Mark removed billing fields to not-required
		if ( ! empty( $customer_details_settings['removeBillingFields'] ) && ! empty( $fields['billing'] ) ) {
			foreach ( $customer_details_settings['removeBillingFields'] as $field_id ) {
				if ( isset( $fields['billing'][ $field_id ] ) ) {
					$fields['billing'][ $field_id ]['required'] = 0;
				}
			}
		}

		// Mark removed shipping fields to not-required
		if ( ! empty( $customer_details_settings['removeShippingFields'] ) && ! empty( $fields['shipping'] ) ) {
			foreach ( $customer_details_settings['removeShippingFields'] as $field_id ) {
				if ( isset( $fields['shipping'][ $field_id ] ) ) {
					$fields['shipping'][ $field_id ]['required'] = 0;
				}
			}
		}

		return $fields;
	}

	/**
	 * Cart or checkout build with Bricks: Remove 'wordpress' post class to avoid auto-containing Bricks content
	 *
	 * @since 1.5.5
	 */
	public function post_class( $classes, $class, $post_id ) {
		$remove_wordpress_class = false;

		// Cart page
		if ( is_cart() ) {
			$count = is_object( WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;

			if (
				( $count && self::get_template_data_by_type( 'wc_cart', false ) ) || // Cart has items & Bricks template
				( ! $count && self::get_template_data_by_type( 'wc_cart_empty', false ) ) // Empty cart & Bricks template
			) {
				$remove_wordpress_class = true;
			}
		}

		// Checkout page
		if ( is_checkout() ) {
			// Order pay
			if ( get_query_var( 'order-pay' ) ) {
				if ( self::get_template_data_by_type( 'wc_form_pay', false ) ) {
					$remove_wordpress_class = true;
				}
			}

			// Order receipt (= thank you page)
			if ( get_query_var( 'order-received' ) ) {
				if ( self::get_template_data_by_type( 'wc_thankyou', false ) ) {
					$remove_wordpress_class = true;
				}
			}

			// Checkout page
			elseif ( self::get_template_data_by_type( 'wc_form_checkout', false ) ) {
				$remove_wordpress_class = true;
			}
		}

		// STEP: Remove 'wordpress' post class to avoid auto-containing Bricks content
		if ( $remove_wordpress_class ) {
			$index = array_search( 'wordpress', $classes );

			if ( isset( $classes[ $index ] ) ) {
				unset( $classes[ $index ] );
			}
		}

		return $classes;
	}

	/**
	 * If WooCommerce is not used, make sure the single and archive Woo templates are not used
	 *
	 * @since 1.5.1
	 *
	 * @param string $template
	 * @return string
	 */
	public function no_woo_template_include( $template ) {
		if ( empty( $template ) ) {
			return $template;
		}

		if ( strpos( $template, '/bricks/archive-product.php' ) ) {
			return get_query_template( 'archive', [ 'archive.php' ] );
		}

		if ( strpos( $template, '/bricks/single-product.php' ) ) {
			return get_query_template( 'single', [ 'single.php' ] );
		}

		return $template;
	}

	/**
	 * Sale badge HTML
	 *
	 * Show text or percentage.
	 */
	public function badge_sale( $html, $post, $product ) {
		$badge_type = Database::get_setting( 'woocommerceBadgeSale', false );

		// Type: ''
		if ( ! $badge_type ) {
			return;
		}

		// Type: text
		elseif ( $badge_type === 'text' ) {
			return '<span class="badge onsale">' . esc_html__( 'Sale', 'bricks' ) . '</span>';
		}

		// Type: percentage
		if ( $product->is_type( 'variable' ) ) {
			$percentages = [];

			// Get all variation prices
			$prices = $product->get_variation_prices();

			foreach ( $prices['price'] as $key => $price ) {
				if ( $prices['regular_price'][ $key ] !== $price ) {
					$percentages[] = round( 100 - ( floatval( $prices['sale_price'][ $key ] ) / floatval( $prices['regular_price'][ $key ] ) * 100 ) );
				}
			}

			// Use highest discountvalue
			$percentage = max( $percentages ) . '%';
		} elseif ( $product->is_type( 'grouped' ) ) {
			$percentages = [];

			$children = $product->get_children();

			foreach ( $children as $child ) {
				$child_product = wc_get_product( $child );

				$regular_price = (float) $child_product->get_regular_price();
				$sale_price    = (float) $child_product->get_sale_price();

				if ( $sale_price != 0 || ! empty( $sale_price ) ) {
					$percentages[] = round( 100 - ( $sale_price / $regular_price * 100 ) );
				}
			}

			// Use highest value
			$percentage = max( $percentages ) . '%';
		} else {
			$regular_price = (float) $product->get_regular_price();
			$sale_price    = (float) $product->get_sale_price();

			if ( $sale_price != 0 || ! empty( $sale_price ) ) {
				$percentage = round( 100 - ( $sale_price / $regular_price * 100 ) ) . '%';
			} else {
				return $html;
			}
		}

		return '<span class="badge onsale">-' . $percentage . '</span>';
	}

	public static function badge_new() {
		global $product;

		$newness_in_days = Database::get_setting( 'woocommerceBadgeNew', false );

		if ( ! $newness_in_days ) {
			return;
		}

		$newness_timestamp = time() - ( 60 * 60 * 24 * $newness_in_days );
		$created           = strtotime( $product->get_date_created() );
		$is_new            = $newness_timestamp < $created; // Created less than {$newness_in_days} days ago

		if ( $is_new ) {
			echo '<span class="badge new">' . esc_html__( 'New', 'bricks' ) . '</span>';
		}
	}

	/**
	 * Product review submit button: Add 'button' class to apply Woo button styles
	 */
	public function product_review_comment_form_args( $comment_form ) {
		$comment_form['class_submit'] = 'button';

		return $comment_form;
	}

	/**
	 * WooCommerce support sets WC_Template_Loader::$theme_support = true
	 */
	public function add_theme_support() {
		add_theme_support( 'woocommerce', [
			'product_grid' => [
				'default_columns' => 4,
				'default_rows'    => 3,
				'min_columns'     => 1,
				'max_columns'     => 6,
				'min_rows'        => 1,
			],
		] );

		add_theme_support( 'wc-product-gallery-slider' );

		// Disable/enable product gallery zoom
		if ( Database::get_setting( 'woocommerceDisableProductGalleryZoom', false ) ) {
			remove_theme_support( 'wc-product-gallery-zoom' );
		} else {
			add_theme_support( 'wc-product-gallery-zoom' );
		}

		// Disable/enable product gallery lightbox (always disabled in builder)
		$disable_product_gallery_lightbox = Database::get_setting( 'woocommerceDisableProductGalleryLightbox', false );

		if ( $disable_product_gallery_lightbox || bricks_is_builder() ) {
			remove_theme_support( 'wc-product-gallery-lightbox' );
		} else {
			add_theme_support( 'wc-product-gallery-lightbox' );
		}
	}

	/**
	 * Get products terms (categories, tags) for in-builder product query controls
	 */
	public function set_products_terms() {
		if ( bricks_is_builder() ) {
			self::$product_categories = self::get_products_terms( 'product_cat' );
			self::$product_tags       = self::get_products_terms( 'product_tag' );
		}
	}

	/**
	 * Get terms for a given product taxonomy
	 */
	public static function get_products_terms( $taxonomy = null ) {
		if ( empty( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);

		$tags = [];

		foreach ( $terms as $term ) {
			$tags[ $term->term_id ] = $term->name;
		}

		return $tags;
	}

	/**
	 * Check if WooCommerce plugin is active
	 *
	 * @return boolean
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'woocommerce' ) && ! Database::get_setting( 'woocommerceDisableBuilder', false );
	}

	/**
	 * Init WooCommerce theme styles
	 */
	public function init_theme_styles() {
		$file = BRICKS_PATH . 'includes/woocommerce/theme-styles.php';

		if ( is_readable( $file ) ) {
			require_once $file;

			new Woocommerce_Theme_Styles();
		}
	}

	/**
	 * Init WooCommerce elements
	 */
	public function init_elements() {
		// Load WooCommerce helpers
		$helpers_file = BRICKS_PATH . 'includes/woocommerce/helpers.php';

		if ( is_readable( $helpers_file ) ) {
			require_once $helpers_file;
		}

		$woo_elements = [
			'woocommerce-breadcrumbs',
			'woocommerce-mini-cart',

			'product-title',
			'product-gallery',
			'product-short-description',
			'product-price',
			'product-stock',
			'product-meta',
			'product-rating',
			'product-content',
			'product-add-to-cart',
			'product-related',
			'product-reviews',
			'product-additional-information',
			'product-tabs',
			'product-upsells',

			'woocommerce-cart-collaterals',
			'woocommerce-cart-coupon',
			'woocommerce-cart-items',

			'woocommerce-checkout-customer-details',
			'woocommerce-checkout-order-review',
			'woocommerce-checkout-thankyou',
			'woocommerce-checkout-order-table',
			'woocommerce-checkout-order-payment',

			'woocommerce-products',
			'woocommerce-products-pagination',
			'woocommerce-products-orderby',
			'woocommerce-products-total-results',
			'woocommerce-products-filter',
			'woocommerce-products-archive-description',

			'woocommerce-notice',
			// 'woocommerce-template-hook', // NOTE: Not in use as action hooks can be added via the 'do_action' DD tag (@since 1.7)
		];

		foreach ( $woo_elements as $element_name ) {
			// @since 1.8.1 - Only register woocommerce-notice if user activated it
			if ( $element_name === 'woocommerce-notice' && ! self::use_bricks_woo_notice_element() ) {
				continue;
			}

			$woo_element_file = BRICKS_PATH . "includes/woocommerce/elements/$element_name.php";

			// Get the class name from the element name
			$class_name = str_replace( '-', '_', $element_name );
			$class_name = ucwords( $class_name, '_' );
			$class_name = "Bricks\\$class_name";

			if ( is_readable( $woo_element_file ) ) {
				Elements::register_element( $woo_element_file, $element_name, $class_name );
			}
		}
	}

	public function quantity_input_field_add_minus_button() {
		$html  = '<span class="action minus">';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="12" x2="18" y2="12"></line></svg>';
		$html .= '</span>';

		echo $html;
	}

	public function quantity_input_field_add_plus_button() {
		$html  = '<span class="action plus">';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="6" x2="12" y2="18"></line><line x1="6" y1="12" x2="18" y2="12"></line></svg>';
		$html .= '</span>';

		echo $html;
	}

	public function breadcrumb_separator( $defaults ) {
		$defaults['delimiter'] = '<span>/</span>';

		return $defaults;
	}

	/**
	 * Add search breadbrumb in the product archive if using Bricks search filter
	 *
	 * @param array         $crumbs
	 * @param WC_Breadcrumb $crumbs_obj
	 * @return array
	 */
	public function add_breadcrumbs_from_filters( $crumbs, $crumbs_obj ) {

		if ( ! empty( $_GET['b_search'] ) && Woocommerce_Helpers::is_archive_product() ) {
			$crumbs[] = [
				sprintf( __( 'Search results for &ldquo;%s&rdquo;', 'woocommerce' ), wp_strip_all_tags( $_GET['b_search'] ) ),
				remove_query_arg( 'paged' )
			];
		}

		return $crumbs;
	}

	/**
	 * Bypass Builder post type check because page set to WooCommerce Shop fails
	 *
	 * @return boolean
	 */
	public function bypass_builder_post_type_check( $supported_post_types, $current_post_type ) {
		if ( in_array( 'page', $supported_post_types ) && ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) ) {
			$supported_post_types[] = 'product';
		}

		return $supported_post_types;
	}

	/**
	 * Builder: Set single product template & populate content (if needed)
	 *
	 * @return void
	 */
	public function maybe_set_template_preview_content() {
		$post_id = get_the_ID();

		$template_type       = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, true );
		$template_preview_id = Helpers::get_template_setting( 'templatePreviewPostId', $post_id );

		if (
			strpos( $template_type, 'wc_' ) !== false ||
			wc_get_page_id( 'shop' ) == $template_preview_id ) {
			// Necessary to add 'woocommerce' to body_class for styling
			add_filter(
				'is_woocommerce',
				function() {
					return true;
				}
			);
		}

		// Remove 'woocommerce body class in builder panel
		if ( bricks_is_builder_main() ) {
			add_filter(
				'is_woocommerce',
				function() {
					return false;
				}
			);
		}

		// Form checkout template
		if (
			$template_type === 'wc_form_checkout' ||
			$template_type === 'wc_form_pay' ||
			$template_type === 'wc_cart'
		) {
			add_filter( 'body_class', [ $this, 'add_body_class' ], 9, 1 );
		}

		// Return: Not in builder nor template
		if ( ! bricks_is_builder() || ! Helpers::is_bricks_template( $post_id ) ) {
			return;
		}

		// Get the last product and save it as preview ID
		if ( $template_type === 'wc_product' ) {
			// Template has already a preview post ID: Leave
			$template_preview_post_id = Helpers::get_template_setting( 'templatePreviewPostId', $post_id );

			if ( $template_preview_post_id ) {
				return;
			}

			$products = wc_get_products(
				[
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
				]
			);

			if ( isset( $products[0] ) ) {
				Helpers::set_template_setting( $post_id, 'templatePreviewPostId', $products[0] );
				Helpers::set_template_setting( $post_id, 'templatePreviewType', 'single' );
				Helpers::set_template_setting( $post_id, 'templatePreviewAutoContent', 1 ); // This setting will be used to trigger a notification
			}
		}

		// TODO: Replace this logic by a generic template preview CPT Archive > CPT = products
		// elseif ( $template_type === 'wc_archive' ) {
		// $template_preview_type = Helpers::get_template_setting( 'templatePreviewType', $post_id );

		// if ( $template_preview_type ) {
		// return;
		// }

		// Helpers::set_template_setting( $post_id, 'templatePreviewType', 'archive-product' );
		// }
	}

	/**
	 * Cart page / Checkout page: Return no title if rendered via Bricks template
	 *
	 * @since 1.8
	 */
	public function default_page_title( $post_title, $post_id ) {
		if ( is_cart() ) {
			if ( WC()->cart->is_empty() ) {
        // Empty cart template
				$cart_template_ids = \Bricks\Templates::get_templates_by_type( 'wc_cart_empty' );
			} else {
				// Normal cart template
				$cart_template_ids = \Bricks\Templates::get_templates_by_type( 'wc_cart' );
			}

			return empty( $cart_template_ids ) ? $post_title : '';
		}

		if ( is_checkout() ) {
			$checkout_template_ids = \Bricks\Templates::get_templates_by_type( 'wc_form_checkout' );

			return empty( $checkout_template_ids ) ? $post_title : '';
		}

		return $post_title;
	}

	/**
	 * Set aria-current="page" for WooCommerce Shop page
	 *
	 * @since 1.8
	 */
	public function maybe_set_aria_current_page( $set, $url ) {
		// Return: Not the WooCommerce shop page
		if ( ! is_shop() ) {
			return $set;
		}

		return $url === get_permalink( wc_get_page_id( 'shop' ) );
	}

	/**
	 * Builder: Add body classes to Woo templates
	 *
	 * @param array $classes
	 * @return void
	 */
	public function add_body_class( $classes ) {
		if ( get_post_type() !== BRICKS_DB_TEMPLATE_SLUG ) {
			return $classes;
		}

		if ( Templates::get_template_type() === 'wc_form_checkout' ) {
			$classes[] = 'woocommerce-checkout';
			$classes[] = 'woocommerce-page';
		} elseif ( Templates::get_template_type() === 'wc_form_pay' ) {
			$classes[] = 'woocommerce-checkout';
		} elseif ( Templates::get_template_type() === 'wc_cart' ) {
			$classes[] = 'woocommerce-cart';
			$classes[] = 'woocommerce-page';
		}

		return $classes;
	}

	/**
	 * On the builder, move up WooCommerce specific elements
	 *
	 * @since 1.2.1
	 *
	 * @param string  $category
	 * @param integer $post_id
	 * @param string  $post_type
	 * @return string
	 */
	public function set_first_element_category( $category, $post_id, $post_type ) {
		if ( BRICKS_DB_TEMPLATE_SLUG === $post_type ) {
			$template_type = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, true );

			if ( $template_type == 'wc_product' ) {
				return 'woocommerce_product';
			} elseif ( strpos( $template_type, 'wc_' ) !== false ) {
				return 'woocommerce';
			}
		} elseif ( is_post_type_archive( 'product' ) || $post_id == wc_get_page_id( 'shop' ) ) {
			return 'woocommerce';
		} elseif ( 'product' == $post_type ) {
			return 'woocommerce_product';
		}

		return $category;
	}

	/**
	 * Page marked as shop - is_shop() - has a global $post_id set to the first product (like is_home)
	 *
	 * In builder or when setting the active templates we need to replace the active post id by the page id
	 *
	 * @param integer $post_id
	 * @return void
	 */
	public function maybe_set_post_id( $post_id ) {
		// If launching bricks builder on page defined as shop
		if ( is_shop() && ! Helpers::is_bricks_template( $post_id ) ) {
			$page_id = wc_get_page_id( 'shop' );

			$post_id = ! empty( $page_id ) ? $page_id : $post_id;
		}

		return $post_id;
	}

	/**
	 * Add WooCommerce element link selectors to allow Theme Styles for the links
	 *
	 * @since 1.5.7
	 */
	public function link_css_selectors( $selectors ) {
		$selectors[] = '.brxe-product-content a';
		$selectors[] = '.brxe-product-short-description a';
		$selectors[] = '.brxe-product-tabs .woocommerce-Tabs-panel a';

		return $selectors;
	}

	/**
	 * NOTE: Not in use as we renamed the 'PhotoSwipe' class to 'Photoswipe5' to avoid conflicts with WooCommerce Photoswipe 4
	 */
	public function unload_photoswipe5_lightbox_assets() {
		// Remove Bricks lightbox (as Photoswipe 5 conflicts with Photoswipe 4, the latter which is used by WooCommerce)
		if ( is_product() && current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
			wp_deregister_script( 'bricks-photoswipe' );
			wp_deregister_script( 'bricks-photoswipe-lightbox' );
			wp_deregister_style( 'bricks-photoswipe' );
		}
	}

	/**
	 * Remove WooCommerce scripts on non-WooCommerce pages
	 *
	 * @since 1.2.1
	 */
	public function wp_enqueue_scripts() {
		if ( bricks_is_builder_iframe() ) {
			// Required for product gallery & tabs
			wp_enqueue_script( 'wc-single-product' );
		}

		if ( ! bricks_is_builder_main() ) {
			wp_enqueue_script( 'bricks-woocommerce', BRICKS_URL_ASSETS . 'js/integrations/woocommerce.min.js', [ 'bricks-scripts' ], filemtime( BRICKS_PATH_ASSETS . 'js/integrations/woocommerce.min.js' ), true );
			wp_enqueue_style( 'bricks-woocommerce', BRICKS_URL_ASSETS . 'css/integrations/woocommerce.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/integrations/woocommerce.min.css' ) );
		}

		if ( is_rtl() ) {
			wp_enqueue_style( 'bricks-woocommerce-rtl', BRICKS_URL_ASSETS . 'css/integrations/woocommerce-rtl.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/integrations/woocommerce-rtl.min.css' ) );
		}

		// @since 1.6.1 - Bricks WooCommerce settings for frontend
		wp_localize_script(
			'bricks-scripts',
			'bricksWooCommerce',
			[
				'ajaxAddToCartEnabled' => self::enabled_ajax_add_to_cart(),
				'ajaxAddingText'       => esc_html__( 'Adding', 'bricks' ),
				'ajaxAddedText'        => esc_html__( 'Added', 'bricks' ),
			]
		);

	}

	/**
	 * Before Bricks searchs for the right template, set the content_type if needed
	 *
	 * @param string  $content_type
	 * @param integer $post_id
	 * @return void
	 */
	public static function set_content_type( $content_type, $post_id ) {
		// These will only kick in if user has defaultTemplatesDisabled = false
		if ( is_product() ) {
			$content_type = 'wc_product';
		} elseif ( is_shop() ) {
			$content_type = 'content';
		} elseif ( Woocommerce_Helpers::is_archive_product() ) {
			$content_type = 'wc_archive';
		}

		return $content_type;
	}

	/**
	 * Add template types to control options
	 *
	 * @param array $control_options
	 * @return array
	 *
	 * @since 1.4
	 */
	public function add_template_types( $control_options ) {
		$template_types = $control_options['templateTypes'];

		// @since 1.3: Product archive & single product templates
		$template_types['wc_archive'] = 'WooCommerce - ' . esc_html__( 'Product archive', 'bricks' );
		$template_types['wc_product'] = 'WooCommerce - ' . esc_html__( 'Single product', 'bricks' );

		// @since 1.4: Cart & checkout templates
		$template_types['wc_cart']          = 'WooCommerce - ' . esc_html__( 'Cart', 'bricks' );
		$template_types['wc_cart_empty']    = 'WooCommerce - ' . esc_html__( 'Empty cart', 'bricks' );
		$template_types['wc_form_checkout'] = 'WooCommerce - ' . esc_html__( 'Checkout', 'bricks' );
		$template_types['wc_form_pay']      = 'WooCommerce - ' . esc_html__( 'Pay', 'bricks' );
		$template_types['wc_thankyou']      = 'WooCommerce - ' . esc_html__( 'Thank you', 'bricks' );
		$template_types['wc_order_receipt'] = 'WooCommerce - ' . esc_html__( 'Order receipt', 'bricks' );

		$control_options['templateTypes'] = $template_types;

		return $control_options;
	}

	/**
	 * Remove "Template Conditions" & "Populate Content" panel controls for WooCommerce Cart & Checkout template parts
	 *
	 * @param array $settings
	 * @return array
	 *
	 * @since 1.4
	 */
	public function remove_template_conditions( $settings ) {
		$excluded_templates = [
			'wc_cart',
			'wc_cart_empty',
			'wc_form_checkout',
			'wc_form_pay',
			'wc_thankyou',
			'wc_order_receipt',
		];

		if ( isset( $settings['controlGroups']['template-preview'] ) ) {
			$settings['controlGroups']['template-preview']['required'] = [ 'templateType', '!=', $excluded_templates, 'templateType' ];
		}

		if ( isset( $settings['controls']['templateConditionsInfo'] ) ) {
			$settings['controls']['templateConditionsInfo']['required'] = [ 'templateType', '!=', $excluded_templates, 'templateType' ];
		}

		if ( isset( $settings['controls']['templateConditions'] ) ) {
			$settings['controls']['templateConditions']['required'] = [ 'templateType', '!=', $excluded_templates, 'templateType' ];
		}

		$settings['controls'][] = [
			'group'    => 'template-conditions',
			'type'     => 'info',
			'content'  => esc_html__( 'This template type is automatically rendered on the correct page.', 'bricks' ),
			'required' => [ 'templateType', '=', $excluded_templates, 'templateType' ],
		];

		return $settings;
	}

	/**
	 * Get template data by template type
	 *
	 * For woocommerce templates inside Bricks theme.
	 *
	 * Return template data rendered via Bricks template shortcode.
	 *
	 * (@since 1.8) Return template id if render is false, this is because we do not trigger any hooks when we are not rendering the template.
	 * Example: do_shortcode will be execute in post_class filter, which will trigger the do_shortcode action, and causing wc_print_notices to be executed in post_class filter before the actual template is rendered. Resulted actual template rendering empty notices. (wc_print_notices() will erase the notices after it is executed)
	 *
	 * @see /includes/woocommerce/cart/cart.php (wc_cart), etc.
	 *
	 * @since 1.4
	 */
	public static function get_template_data_by_type( $type = '', $render = true ) {
		// Do not check for Database::get_setting( 'defaultTemplatesDisabled' )
		$template_ids = Templates::get_templates_by_type( $type );

		// No template found
		if ( empty( $template_ids[0] ) ) {
			return false;
		}

		// Return template id if render is false
		if ( ! $render ) {
			return $template_ids[0];
		}

		return do_shortcode( '[bricks_template id="' . $template_ids[0] . '"]' );
	}

	/**
	 * Add Archive Product content type
	 *
	 * Note: Not in use
	 *
	 * @param array $types
	 * @return void
	 */
	public function add_content_types( $types ) {
		$types['archive-product'] = esc_html__( 'Archive (products)', 'bricks' );

		return $types;
	}

	/**
	 * Setup the products query loop in the products archive, including is_shop page (frontend only)
	 *
	 * @param array  $data Elements list
	 * @param string $post_id
	 * @return void
	 */
	public function setup_query( $data, $post_id ) {

		$query_element = Woocommerce_Helpers::get_products_element( $post_id, $data );

		// No query element to merge, proceed with regular WooCommerce loop
		if ( ! $query_element ) {
			wc_setup_loop();

			return;
		}

		// Force the post type to feed the Bricks Query class
		if ( empty( $query_element['settings']['query'] ) ) {
			$query_element['settings']['query'] = [
				'post_type'           => [ 'product' ],
				'ignore_sticky_posts' => 1
			];
		}

		// Query
		$query_object = new Query( $query_element );

		$query = $query_object->query_result;

		// Remove ordering query arguments which may have been added by 'get_catalog_ordering_args'
		WC()->query->remove_ordering_args();

		$columns = isset( $query_element['settings']['columns'] ) ? $query_element['settings']['columns'] : 4;

		wc_setup_loop(
			[
				'columns'      => $columns,
				'name'         => 'bricks-products',
				'is_shortcode' => true,
				'is_search'    => false,
				'is_paginated' => true,
				'total'        => (int) $query->found_posts,
				'total_pages'  => (int) $query->max_num_pages,
				'per_page'     => (int) $query->get( 'posts_per_page' ),
				'current_page' => (int) max( 1, $query->get( 'paged', 1 ) ),
			]
		);
	}

	public function reset_query( $sections, $post_id ) {
		wc_reset_loop();
	}

	/**
	 * Update the mini-cart fragments
	 *
	 * @param array $fragments
	 * @return void
	 */
	public function update_mini_cart( $fragments ) {
		if ( ! is_object( WC()->cart ) ) {
			return;
		}

		// Cart Count
		$count = WC()->cart->get_cart_contents_count();

		$fragments['span.cart-count'] = '<span class="cart-count ' . ( $count == 0 ? 'hide' : 'show' ) . '">' . $count . '</span>';

		// Cart Subtotal
		$subtotal = WC()->cart->get_cart_subtotal();

		if ( $subtotal ) {
			$fragments['span.cart-subtotal'] = '<span class="cart-subtotal">' . $subtotal . '</span>';
		}

		return $fragments;
	}

	/**
	 * Check if the query loop is on Woo products, and if yes, check if we should merge the main query
	 *
	 * @since 1.5
	 *
	 * @param boolean $merge
	 * @param string  $element_id
	 * @return boolean
	 */
	public function maybe_merge_query( $merge, $element_id ) {
		$query = Query::get_query_for_element_id( $element_id );

		if ( ! isset( $query->query_vars['post_type'] ) ) {
			return $merge;
		}

		if ( is_array( $query->query_vars['post_type'] ) && ! in_array( 'product', $query->query_vars['post_type'] ) ) {
			return $merge;
		}

		return Woocommerce_Helpers::is_archive_product();
	}

	/**
	 * Add products query vars to the query loop
	 *
	 * @since 1.5
	 *
	 * @param array  $query_vars
	 * @param array  $settings
	 * @param string $element_id
	 * @return boolean
	 */
	public function set_products_query_vars( $query_vars, $settings, $element_id ) {
		if ( ! isset( $query_vars['post_type'] ) ) {
			return $query_vars;
		}

		if ( is_array( $query_vars['post_type'] ) && ! in_array( 'product', $query_vars['post_type'] ) ) {
			return $query_vars;
		}

		$new_query_vars = $query_vars;

		$filter_args = Woocommerce_Helpers::filters_query_args( $settings );

		// Override the query settings by the filters (orderby, filters)
		foreach ( $filter_args as $key => $filter_value ) {
			if ( in_array( $key, [ 'meta_query', 'tax_query' ] ) && ! empty( $query_vars[ $key ] ) ) {
				continue;
			}

			$new_query_vars[ $key ] = $filter_value;
		}

		// STEP: Merge meta or/and tax query (if has conflicts)
		foreach ( [ 'meta_query', 'tax_query' ] as $type ) {
			if ( empty( $query_vars[ $type ] ) || empty( $filter_args[ $type ] ) ) {
				continue;
			}

			$relation_query_vars  = isset( $query_vars[ $type ]['relation'] ) ? $query_vars[ $type ]['relation'] : false;
			$relation_filter_args = isset( $filter_args[ $type ]['relation'] ) ? $filter_args[ $type ]['relation'] : false;

			// Both meta query sources have the relation key set and they are different
			if ( $relation_query_vars && $relation_filter_args && $relation_query_vars != $relation_filter_args ) {
				$new_query_vars[ $type ] = [
					'relation' => 'AND',
					0          => $query_vars[ $type ],
					1          => $filter_args[ $type ]
				];
			}

			// Relations are equal or not set
			else {
				$relation = $relation_query_vars ? $relation_query_vars : ( $relation_filter_args ? $relation_filter_args : false );

				unset( $query_vars[ $type ]['relation'] );
				unset( $filter_args[ $type ]['relation'] );

				$new_query_vars[ $type ] = array_merge( $query_vars[ $type ], $filter_args[ $type ] );

				if ( $relation ) {
					$new_query_vars[ $type ]['relation'] = $relation;
				}
			}
		}

		return $new_query_vars;
	}

	/**
	 * Adds the cart contents query to the Query Loop builder
	 *
	 * @param array $control_options
	 * @return array
	 */
	public function add_control_options( $control_options ) {
		$control_options['queryTypes']['wooCart'] = esc_html__( 'Cart contents', 'bricks' );

		return $control_options;
	}

	/**
	 * Returns the cart contents query
	 *
	 * @param array $results
	 * @param Query $query
	 * @return array
	 */
	public function run_cart_query( $results, $query ) {
		if ( $query->object_type !== 'wooCart' ) {
			return $results;
		}

		// Avoid Uncaught Error: Call to a member function get_cart() on null (@since 1.8.1)
		if ( is_null( WC()->cart ) ) {
			return [];
		}

		return WC()->cart->get_cart();
	}

	/**
	 * Sets the loop object (to WP_Post) in each query loop iteration
	 *
	 * @param array  $loop_object
	 * @param string $loop_key
	 * @param Query  $query
	 * @return array
	 */
	public function set_loop_object( $loop_object, $loop_key, $query ) {
		if ( $query->object_type !== 'wooCart' ) {
			return $loop_object;
		}

		// @see woocommerce/templates/cart/cart.php
		$_product   = apply_filters( 'woocommerce_cart_item_product', $loop_object['data'], $loop_object, $loop_key );
		$product_id = apply_filters( 'woocommerce_cart_item_product_id', $loop_object['product_id'], $loop_object, $loop_key );

		global $post;

		$post = get_post( $product_id );

		setup_postdata( $post );

		return $loop_object;
	}

	/**
	 * Returns the loop object id (for the cart query)
	 *
	 * @since 1.5.3
	 */
	public function set_loop_object_id( $object_id, $object, $query_id ) {
		$query_object_type = Query::get_query_object_type( $query_id );

		if ( $query_object_type !== 'wooCart' ) {
			return $object_id;
		}

		return get_the_ID();
	}

	/**
	 * Returns the loop object type (for the cart query)
	 *
	 * @since 1.5.3
	 */
	public function set_loop_object_type( $object_type, $object, $query_id ) {
		$query_object_type = Query::get_query_object_type( $query_id );

		if ( $query_object_type !== 'wooCart' ) {
			return $object_type;
		}

		return 'post';
	}

	/**
	 * Check if user enabled single ajax add to cart
	 *
	 * @return bool
	 * @since 1.6.1
	 */
	public static function enabled_ajax_add_to_cart() {
		return Database::get_setting( 'woocommerceEnableAjaxAddToCart', false );
	}

	/**
	 * AJAX Add to cart
	 * Support product types: simple, variable, grouped
	 *
	 * @since 1.6.1
	 *
	 * @see woocommerce/includes/class-wc-ajax.php add_to_cart()
	 */
	public function add_to_cart() {
		ob_start();

		if ( ! isset( $_POST['product_id'] ) ) {
			return;
		}

		$product_type   = isset( $_POST['product_type'] ) ? sanitize_title( wp_unslash( $_POST['product_type'] ) ) : 'simple';
		$product_id     = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
		$quantity       = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
		$product_status = get_post_status( $product_id );
		$variation_id   = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$variation      = isset( $_POST['variation'] ) ? (array) $_POST['variation'] : [];
		$products       = isset( $_POST['products'] ) ? (array) $_POST['products'] : [];

		switch ( $product_type ) {
			case 'grouped':
				// No products added
				if ( count( $products ) < 1 ) {
					return;
				}

				$passed = [];
				foreach ( $products as $id => $quantity ) {
					if ( $quantity > 0 ) {
						$each_passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $id, $quantity );
						if ( $each_passed_validation && false !== WC()->cart->add_to_cart( $id, $quantity ) && 'publish' === $product_status ) {
							do_action( 'woocommerce_ajax_added_to_cart', $id );
							$passed[ $id ] = $quantity;
						}
					}
				}

				// Overall passed validation for grouped products
				$passed_validation = count( $passed ) === count( $products );

				if ( $passed_validation && 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
					foreach ( $passed as $id => $quantity ) {
						wc_add_to_cart_message( [ $id => $quantity ], true );
					}
				}
				break;

			default:
			case 'variable':
			case 'simple':
				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation );

				if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) && 'publish' === $product_status ) {
					do_action( 'woocommerce_ajax_added_to_cart', $product_id );

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						wc_add_to_cart_message( [ $product_id => $quantity ], true );
					}
				} else {
					$passed_validation = false;
				}
				break;
		}

		// Return error
		if ( ! $passed_validation ) {
			// If there was an error adding to the cart, redirect to the product page to show any errors
			$data = [
				'error'       => true,
				'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
			];

			// Send error json
			wp_send_json( $data );
		}

		// All good, return fragments
		\WC_AJAX::get_refreshed_fragments();

	}

	/**
	 * Check if use bricks woo notice element
	 *
	 * @since 1.8.1
	 * @return bool
	 */
	public static function use_bricks_woo_notice_element() {
		return Database::get_setting( 'woocommerceUseBricksWooNotice', false );
	}

	/**
	 * Remove all native woocommerce notices hooks if use Bricks woo notice element
	 * So user can control the location of notices via the Bricks woo notice element
	 *
	 * @since 1.8.1
	 * @see woocommerce/includes/wc-template-hooks.php Notices
	 */
	public static function maybe_remove_native_woocommerce_notices_hooks() {
		if ( ! self::use_bricks_woo_notice_element() ) {
			return;
		}

		// cart-empty.php
		remove_action( 'woocommerce_cart_is_empty', 'woocommerce_output_all_notices', 5 );

		remove_action( 'woocommerce_shortcode_before_product_cat_loop', 'woocommerce_output_all_notices', 10 );

		// archive-product.php
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10 );
		remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );

		// cart.php
		remove_action( 'woocommerce_before_cart', 'woocommerce_output_all_notices', 10 );

		// This hook is fired when using the [woocommerce_checkout] shortcode
		remove_action( 'woocommerce_before_checkout_form_cart_notices', 'woocommerce_output_all_notices', 10 );

		// form-checkout.php
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );

		// inside shortcode [woocommerce_checkout] order_pay
		remove_action( 'before_woocommerce_pay', 'woocommerce_output_all_notices', 10 );

		// my-account.php
		remove_action( 'woocommerce_account_content', 'woocommerce_output_all_notices', 5 );

		// myaccount/form-login.php
		remove_action( 'woocommerce_before_customer_login_form', 'woocommerce_output_all_notices', 10 );

		// myaccount/form-lost-password.php
		remove_action( 'woocommerce_before_lost_password_form', 'woocommerce_output_all_notices', 10 );

		// myaccount/form-reset-password.php
		remove_action( 'woocommerce_before_reset_password_form', 'woocommerce_output_all_notices', 10 );

	}

	/**
	 * Remove WooCommerce hook actions to avoid duplicate content
	 *
	 * @since 1.7
	 *
	 * @param string   $action
	 * @param array    $filters
	 * @param string   $context
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	public function maybe_remove_woo_hook_actions( $action, $filters, $context, $post ) {
		$template = Woocommerce_Helpers::get_repeated_wc_template_hooks_by_action( $action );

		// STEP: Exit if not supported template
		if ( empty( $template ) ) {
			return;
		}

		$template_name = array_keys( $template )[0];

		// STEP: Remove native woo hook actions
		Woocommerce_Helpers::execute_actions_in_wc_template( $template_name, 'remove', $action );
	}

	/**
	 * Restore WooCommerce hooks
	 *
	 * @since 1.7
	 *
	 * @param string   $action
	 * @param array    $filters
	 * @param string   $context
	 * @param \WP_Post $post
	 * @param mixed    $value
	 *
	 * @return void
	 */
	public function maybe_restore_woo_hook_actions( $action, $filters, $context, $post, $value ) {
		$template = Woocommerce_Helpers::get_repeated_wc_template_hooks_by_action( $action );

		// STEP: Exit if not supported template
		if ( empty( $template ) ) {
			return;
		}

		$template = array_keys( $template )[0];

		// STEP: Restore native woo hook actions
		Woocommerce_Helpers::execute_actions_in_wc_template( $template, 'add', $action );
	}
}
