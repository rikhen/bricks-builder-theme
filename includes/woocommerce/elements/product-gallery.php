<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Product_Gallery extends Element {
	public $category = 'woocommerce_product';
	public $name     = 'product-gallery';
	public $icon     = 'ti-gallery';
	public $scripts  = [ 'bricksWooProductGallery' ];

	public function enqueue_scripts() {
		wp_enqueue_script( 'wc-single-product' );
		wp_enqueue_script( 'flexslider' );

		if ( bricks_is_builder_iframe() ) {
			wp_enqueue_script( 'zoom' );
		} elseif ( ! Database::get_setting( 'woocommerceDisableProductGalleryZoom', false ) ) {
			wp_enqueue_script( 'zoom' );
		}
	}

	public function get_label() {
		return esc_html__( 'Product gallery', 'bricks' );
	}

	public function set_controls() {
		$this->controls['_width']['rerender']    = true;
		$this->controls['_widthMax']['rerender'] = true;

		$this->controls['columns'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Columns', 'bricks' ),
			'type'        => 'number',
			'css'         => [
				[
					'selector' => '.flex-control-thumbs',
					'property' => 'grid-template-columns',
					'value'    => 'repeat(%s, 1fr)', // NOTE: Undocumented (@since 1.3)
				],
			],
			'placeholder' => 4,
			'rerender'    => true,
		];

		$this->controls['gap'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Gap', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[

					'selector' => '.flex-control-thumbs',
					'property' => 'gap',
				],
				[
					'selector' => '.woocommerce-product-gallery',
					'property' => 'gap',
				],
			],
			'placeholder' => '30px',
		];

		$this->controls['productImageSize'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Product', 'bricks' ) . ': ' . esc_html__( 'Image size', 'bricks' ),
			'type'        => 'select',
			'options'     => Setup::get_image_sizes_options(),
			'placeholder' => 'woocommerce_single',
		];

		$this->controls['thumbnailImageSize'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Thumbnail', 'bricks' ) . ': ' . esc_html__( 'Image size', 'bricks' ),
			'type'        => 'select',
			'options'     => Setup::get_image_sizes_options(),
			'placeholder' => 'woocommerce_gallery_thumbnail',
		];

		$this->controls['lightboxImageSize'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Lightbox', 'bricks' ) . ': ' . esc_html__( 'Image size', 'bricks' ),
			'type'        => 'select',
			'options'     => Setup::get_image_sizes_options(),
			'placeholder' => 'full',
		];
	}

	public function render() {
		$settings = $this->settings;

		global $product;

		$product = wc_get_product( $this->post_id );

		if ( empty( $product ) ) {
			return $this->render_element_placeholder(
				[
					'title'       => esc_html__( 'For better preview select content to show.', 'bricks' ),
					'description' => esc_html__( 'Go to: Settings > Template Settings > Populate Content', 'bricks' ),
				]
			);
		}

		add_filter( 'woocommerce_gallery_thumbnail_size', [ $this, 'set_gallery_thumbnail_size' ] );

		add_filter( 'woocommerce_gallery_image_size', [ $this, 'set_gallery_image_size' ] );

		add_filter( 'woocommerce_gallery_full_size', [ $this, 'set_gallery_full_size' ] );

		add_filter( 'woocommerce_gallery_image_html_attachment_image_params', [ $this, 'add_image_class_prevent_lazy_loading' ], 10, 4 );

		echo "<div {$this->render_attributes( '_root' )}>";

		wc_get_template( 'single-product/product-image.php' );

		echo '</div>';

		remove_filter( 'woocommerce_gallery_thumbnail_size', [ $this, 'set_gallery_thumbnail_size' ] );

		remove_filter( 'woocommerce_gallery_image_size', [ $this, 'set_gallery_image_size' ] );

		remove_filter( 'woocommerce_gallery_full_size', [ $this, 'set_gallery_full_size' ] );

		remove_filter( 'woocommerce_gallery_image_html_attachment_image_params', [ $this, 'add_image_class_prevent_lazy_loading' ], 10, 4 );
	}

	/**
	 * Set gallery image size for the current product gallery
	 *
	 * hook: woocommerce_gallery_image_size
	 * @see woocommerce/includes/wc-template-functions.php
	 *
	 * @since 1.8
	 */
	public function set_gallery_image_size( $size ) {
		if ( ! empty( $this->settings['productImageSize'] ) ) {
			$size = $this->settings['productImageSize'];
		}

		return $size;
	}

	/**
	 * Set gallery thumbnail size for the current product gallery
	 *
	 * hook: woocommerce_gallery_thumbnail_size
	 * @see woocommerce/includes/wc-template-functions.php
	 *
	 * @since 1.8
	 */
	public function set_gallery_thumbnail_size( $size ) {
		if ( ! empty( $this->settings['thumbnailImageSize'] ) ) {
			$size = $this->settings['thumbnailImageSize'];
		}

		return $size;
	}

	/**
	 * Set gallery full size for the current product gallery (Lightbox)
	 *
	 * hook: woocommerce_gallery_full_size
	 * @see woocommerce/includes/wc-template-functions.php
	 *
	 * @since 1.8
	 */
	public function set_gallery_full_size( $size ) {
		if ( ! empty( $this->settings['lightboxImageSize'] ) ) {
			$size = $this->settings['lightboxImageSize'];
		}

		return $size;
	}

	public function add_image_class_prevent_lazy_loading( $attr, $attachment_id, $image_size, $main_image ) {
		// NOTE: Undocumented (used only internally in the Frontend::set_image_attributes)
		if ( $this->lazy_load() ) {
			$attr['_brx_disable_lazy_loading'] = 1;
		}

		// Photoswipe 5 (@since 1.7.2)
		// NOTE: Not in use as Photoswipe 5 is not supported by all major Woo product gallery plugins
		// $attachment               = wp_get_attachment_image_src( $attachment_id, $image_size );
		// $attr['data-pswp-src']    = ! empty( $attachment[0] ) ? $attachment[0] : '';
		// $attr['data-pswp-width']  = ! empty( $attachment[1] ) ? $attachment[1] : '';
		// $attr['data-pswp-height'] = ! empty( $attachment[2] ) ? $attachment[2] : '';
		// $attr['data-pswp-id']     = $this->id;

		return $attr;
	}
}
