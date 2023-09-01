<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Product_Add_To_Cart extends Element {
	public $category = 'woocommerce_product';
	public $name     = 'product-add-to-cart';
	public $icon     = 'ti-shopping-cart';

	public function get_label() {
		return esc_html__( 'Add to cart', 'bricks' );
	}

	public function set_control_groups() {
		$this->control_groups['variations'] = [
			'title' => esc_html__( 'Variations', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['stock'] = [
			'title' => esc_html__( 'Stock', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['quantity'] = [
			'title' => esc_html__( 'Quantity', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['button'] = [
			'title' => esc_html__( 'Button', 'bricks' ),
			'tab'   => 'content',
		];

		// @since 1.6.1
		if ( Woocommerce::enabled_ajax_add_to_cart() ) {
			$this->control_groups['ajax'] = [
				'title' => 'AJAX',
				'tab'   => 'content',
			];
		}
	}

	public function set_controls() {
		// VARIATIONS

		// NOTE: Variation settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['variationsTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => 'table.variations label',
				],
			],
		];

		$this->controls['variationsBackgroundColor'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => 'table.variations tr',
				],
			],
		];

		$this->controls['variationsBorder'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.cart .variations tr',
				]
			],
		];

		$this->controls['variationsMargin'] = [
			'tab'         => 'content',
			'group'       => 'variations',
			'label'       => esc_html__( 'Margin', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'selector' => '.cart table.variations',
					'property' => 'margin',
				],
			],
			'placeholder' => [
				'bottom' => 30,
			],
		];

		$this->controls['variationsPadding'] = [
			'tab'         => 'content',
			'group'       => 'variations',
			'label'       => esc_html__( 'Padding', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'selector' => '.cart table.variations td',
					'property' => 'padding',
				],
			],
			'placeholder' => [
				'top'    => 15,
				'bottom' => 15,
			],
		];

		$this->controls['variationsDescriptionTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Description typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-variation-description',
				],
			],
		];

		$this->controls['variationsPriceTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Price typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-variation-price',
				],
			],
		];

		// STOCK

		// NOTE: Stock settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['hideStock'] = [
			'tab'   => 'content',
			'group' => 'stock',
			'label' => esc_html__( 'Hide stock', 'bricks' ),
			'type'  => 'checkbox',
			'css'   => [
				[
					'selector' => '.stock',
					'property' => 'display',
					'value'    => 'none',
				],
			],
		];

		$this->controls['stockTypography'] = [
			'tab'      => 'content',
			'group'    => 'stock',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.stock',
				],
			],
			'required' => [ 'hideStock', '=', '' ]
		];

		// QUANTITY

		// NOTE: Variation settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['quantityWidth'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'number',
			'units' => true,
			'label' => esc_html__( 'Width', 'bricks' ),
			'css'   => [
				[
					'selector' => '.cart .quantity',
					'property' => 'width',
				],
			],
		];

		$this->controls['quantityBackground'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'color',
			'label' => esc_html__( 'Background', 'bricks' ),
			'css'   => [
				[
					'selector' => '.cart .quantity',
					'property' => 'background-color',
				],
			],
		];

		$this->controls['quantityBorder'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'selector' => '.qty',
					'property' => 'border',
				],
				[
					'selector' => '.minus',
					'property' => 'border',
				],
				[
					'selector' => '.plus',
					'property' => 'border',
				],
			],
		];

		// BUTTON

		$this->controls['buttonText'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'tooltip'     => [
				'content'  => esc_html__( 'Text', 'bricks' ),
				'position' => 'top-left',
			],
			'type'        => 'text',
			'placeholder' => esc_html__( 'Add to cart', 'bricks' ),
		];

		$this->controls['buttonPadding'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'padding',
				],
			],
		];

		$this->controls['buttonWidth'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'min-width',
				],
			],
		];

		$this->controls['buttonBackgroundColor'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'background-color',
				],
			],
		];

		$this->controls['buttonBorder'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
				],
			],
		];

		$this->controls['buttonTypography'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'font',
				],
			],
		];

		// Button icon

		$this->controls['icon'] = [
			'tab'      => 'content',
			'group'    => 'button',
			'label'    => esc_html__( 'Icon', 'bricks' ),
			'type'     => 'icon',
			'rerender' => true,
		];

		$this->controls['iconTypography'] = [
			'tab'     => 'content',
			'group'   => 'button',
			'label'   => esc_html__( 'Icon typography', 'bricks' ),
			'type'    => 'typography',
			'css'     => [
				[
					'property' => 'font',
					'selector' => '.icon',
				],
			],
		];

		$this->controls['iconPosition'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'label'       => esc_html__( 'Icon position', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['iconPosition'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Left', 'bricks' ),
			'required'    => [ 'icon', '!=', '' ],
		];

		// Controls for the AJAX add to cart feature (@since 1.6.1)
		if ( Woocommerce::enabled_ajax_add_to_cart() ) {
			$this->controls['addingSeparator'] = [
				'tab'   => 'content',
				'group' => 'ajax',
				'label' => esc_html__( 'Adding', 'bricks' ),
				'type'  => 'separator',
			];

			$this->controls['addingButtonText'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'type'        => 'text',
				'label'       => esc_html__( 'Button text', 'bricks' ),
				'inline'      => true,
				'placeholder' => esc_html__( 'Adding', 'bricks' ),
			];

			$this->controls['addingButtonIcon'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon', 'bricks' ),
				'type'     => 'icon',
				'rerender' => true,
			];

			$this->controls['addingButtonIconPosition'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon position', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['iconPosition'],
				'inline'      => true,
				'placeholder' => esc_html__( 'Left', 'bricks' ),
				'required'    => [ 'addingButtonIcon', '!=', '' ],
			];

			$this->controls['addingButtonIconSpinning'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon spinning', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [ 'addingButtonIcon', '!=', '' ],
			];

			// Added

			$this->controls['addedSeparator'] = [
				'tab'   => 'content',
				'group' => 'ajax',
				'label' => esc_html__( 'Added', 'bricks' ),
				'type'  => 'separator',
			];

			$this->controls['addedButtonText'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'type'        => 'text',
				'label'       => esc_html__( 'Button text', 'bricks' ),
				'inline'      => true,
				'placeholder' => esc_html__( 'Added', 'bricks' ),
			];

			$this->controls['addedButtonIcon'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon', 'bricks' ),
				'type'     => 'icon',
				'rerender' => true,
			];

			$this->controls['addedButtonIconPosition'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon position', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['iconPosition'],
				'inline'      => true,
				'placeholder' => esc_html__( 'Left', 'bricks' ),
				'required'    => [ 'addedButtonIcon', '!=', '' ],
			];
		}
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

		$this->maybe_set_ajax_add_to_cart_data_attribute();

		add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		add_filter( 'esc_html', [ $this, 'avoid_esc_html' ], 10, 2 );

		echo "<div {$this->render_attributes( '_root' )}>";

		if ( Query::is_looping() ) {
			woocommerce_template_loop_add_to_cart();
		} else {
			woocommerce_template_single_add_to_cart();
		}

		echo '</div>';

		remove_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		remove_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		remove_filter( 'esc_html', [ $this, 'avoid_esc_html' ], 10, 2 );
	}

	/**
	 * Add custom text and/or icon to the button
	 *
	 * @param string     $text
	 * @param WC_Product $product
	 * @return void
	 *
	 * @since 1.6
	 */
	public function add_to_cart_text( $text, $product ) {
		$settings = $this->settings;

		$text = ! empty( $settings['buttonText'] ) ? $settings['buttonText'] : $text;

		$icon          = ! empty( $settings['icon'] ) ? self::render_icon( $settings['icon'], [ 'icon' ] ) : false;
		$icon_position = isset( $settings['iconPosition'] ) ? $settings['iconPosition'] : 'left';

		$output = '';

		if ( $icon && $icon_position === 'left' ) {
			$output .= $icon;
		}

		$output .= "<span>$text</span>";

		if ( $icon && $icon_position === 'right' ) {
			$output .= $icon;
		}

		return $output;
	}

	/**
	 * TODO: Needs description
	 *
	 * @since 1.6
	 */
	public function avoid_esc_html( $safe_text, $text ) {
		return $text;
	}

	/**
	 * Set AJAX add to cart data attribute: data-bricks-ajax-add-to-cart
	 *
	 * @since 1.6.1
	 */
	public function maybe_set_ajax_add_to_cart_data_attribute() {
		// Only set data attribute if ajax add to cart is enabled and we are not in a loop. (Single product add to cart button)
		if ( ! Woocommerce::enabled_ajax_add_to_cart() || Query::is_looping() ) {
			return;
		}

		$settings = $this->settings;

		$default_icon          = isset( $settings['icon'] ) ? self::render_icon( $settings['icon'], [ 'icon' ] ) : false;
		$default_icon_position = isset( $settings['iconPosition'] ) ? $settings['iconPosition'] : 'left';

		$states = [ 'adding', 'added' ];

		$ajax_add_to_cart_data = [];

		foreach ( $states as $state ) {
			$default_add_to_cart_text = $state === 'adding' ? esc_html__( 'Adding', 'bricks' ) : esc_html__( 'Added', 'bricks' );
			$state_text               = isset( $settings[ $state . 'ButtonText' ] ) ? $settings[ $state . 'ButtonText' ] : $default_add_to_cart_text;
			$icon_classes             = isset( $settings[ $state . 'ButtonIconSpinning' ] ) ? [ 'icon', 'spinning' ] : [ 'icon' ];
			$icon                     = isset( $settings[ $state . 'ButtonIcon' ] ) ? self::render_icon( $settings[ $state . 'ButtonIcon' ], $icon_classes ) : $default_icon;
			$icon_position            = isset( $settings[ $state . 'ButtonIconPosition' ] ) ? $settings[ $state . 'ButtonIconPosition' ] : $default_icon_position;

			$output = '';

			if ( $icon && $icon_position === 'left' ) {
				$output .= $icon;
			}

			$output .= "<span>$state_text</span>";

			if ( $icon && $icon_position === 'right' ) {
				$output .= $icon;
			}

			$ajax_add_to_cart_data[ $state . 'HTML' ] = $output;
		}

		$this->set_attribute( '_root', 'data-bricks-ajax-add-to-cart', wp_json_encode( $ajax_add_to_cart_data ) );
	}
}
