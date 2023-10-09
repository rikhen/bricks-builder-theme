<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Instagram_Feed extends \Bricks\Element {
	public $category = 'media';
	public $name     = 'instagram-feed';
	public $icon     = 'ion-logo-instagram';

	public function get_label() {
		return esc_html__( 'Instagram feed', 'bricks' );
	}

	public function get_keywords() {
		return [ 'instagram', 'feed', 'social' ];
	}

	public function set_controls() {
		$this->controls['instagramAccessToken'] = [
			'type'     => 'info',
			'content'  => sprintf(
				esc_html__( 'Instagram access token required! Add in WordPress dashboard under %s', 'bricks' ),
				'<a href="' . Helpers::settings_url( '#tab-api-keys' ) . '" target="_blank">' . esc_html__( 'Bricks > Settings > API Keys', 'bricks' ) . '</a>'
			),
			'required' => [ 'instagramAccessToken', '=', '', 'globalSettings' ],
		];

		// LAYOUT
		$this->controls['layoutSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Layout', 'bricks' ),
		];

		$this->controls['columns'] = [
			'label'       => esc_html__( 'Columns', 'bricks' ),
			'type'        => 'number',
			'css'         => [
				[
					'property' => 'grid-template-columns',
					'selector' => 'ul',
					'value'    => 'repeat(%s, 1fr)',
				],
			],
			'placeholder' => 3,
		];

		$this->controls['numberOfPosts'] = [
			'label'       => esc_html__( 'Posts', 'bricks' ),
			'type'        => 'number',
			'min'         => 1,
			'max'         => 100,
			'placeholder' => 9,
		];

		// IMAGE
		$this->controls['imageSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Image', 'bricks' ),
		];

		$this->controls['imageAspectRatio'] = [
			'label'  => esc_html__( 'Aspect ratio', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
			'small'  => true,
			'css'    => [
				[
					'property' => 'aspect-ratio',
					'selector' => 'img',
				],
			],
		];

		$this->controls['imageGap'] = [
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => 'ul',
				],
			],
		];

		$this->controls['imageBorder'] = [
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => 'img',
				],
			],
		];

		$this->controls['imageBorder'] = [
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => 'img',
				],
			],
		];

		$this->controls['imageLink'] = [
			'type'  => 'checkbox',
			'label' => esc_html__( 'Link', 'bricks' ),
		];

		// CAPTION
		$this->controls['captionSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Caption', 'bricks' ),
		];

		$this->controls['caption'] = [
			'type'  => 'checkbox',
			'label' => esc_html__( 'Enable', 'bricks' ),
		];

		$this->controls['captionBackground'] = [
			'label'    => esc_html__( 'Background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '.caption',
				],
			],
			'required' => [ 'caption', '=', true ],
		];

		$this->controls['captionBorder'] = [
			'label'    => esc_html__( 'Border', 'bricks' ),
			'type'     => 'border',
			'css'      => [
				[
					'property' => 'border',
					'selector' => '.caption',
				],
			],
			'required' => [ 'caption', '=', true ],
		];

		$this->controls['captionTypography'] = [
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.caption',
				],
			],
			'required' => [ 'caption', '=', true ],
		];

		// FOLLOW
		$this->controls['followSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Follow', 'bricks' ),
		];

		$this->controls['followText'] = [
			'label'   => esc_html__( 'Text', 'bricks' ),
			'type'    => 'text',
			'default' => 'Follow us @yourhandle',
		];

		$this->controls['followPosition'] = [
			'label'       => esc_html__( 'Position', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'top'    => esc_html__( 'Top', 'bricks' ),
				'bottom' => esc_html__( 'Bottom', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Bottom', 'bricks' ),
		];

		$this->controls['followIcon'] = [
			'label'   => esc_html__( 'Icon', 'bricks' ),
			'type'    => 'icon',
			'default' => [
				'library' => 'ionicons',
				'icon'    => 'ion-logo-instagram',
			],
			'css'     => [
				[
					'selector' => '.follow-icon', // Target SVG file
				],
			],
		];

		$this->controls['followTypography'] = [
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.follow',
				],
			],
		];

		// CACHE
		$this->controls['cacheSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Cache', 'bricks' ),
		];

		$this->controls['cacheDuration'] = [
			'label'       => esc_html__( 'Duration', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'1800'   => esc_html__( '30 minutes', 'bricks' ),
				'3600'   => esc_html__( '1 hour', 'bricks' ),
				'86400'  => esc_html__( '1 day', 'bricks' ),
				'604800' => esc_html__( '1 week', 'bricks' ),
			],
			'inline'      => true,
			'default'     => 3600,
			'placeholder' => esc_html__( '1 hour', 'bricks' ),
		];
	}

	public function render() {
		// STEP: Get the access token
		$instagram_access_token = \Bricks\Database::get_setting( 'instagramAccessToken' );

		// Return: No access token set
		if ( ! $instagram_access_token ) {
			return $this->render_element_placeholder(
				[
					'icon-class' => 'ion-md-warning',
					'title'      => esc_html__( 'Please connect your Instagram account.', 'bricks' ),
				]
			);
		}

		// STEP: Get the settings
		$settings = $this->settings;

		// Number of columns
		$columns = is_numeric( $settings['columns'] ?? null ) && $settings['columns'] >= 1 && $settings['columns'] <= 6
			? intval( $settings['columns'] )
			: 3;

		// Number of posts to fetch
		$number_of_posts = is_numeric( $settings['numberOfPosts'] ?? null ) && $settings['numberOfPosts'] >= 0
			? intval( $settings['numberOfPosts'] )
			: 9;

		// Cache duration (default: 1 hour)
		$cache_duration = $settings['cacheDuration'] ?? 3600;

		// Follow link position
		$follow_position = $settings['followPosition'] ?? 'bottom';

		// STEP: Cache the data in transient

		// Create a unique key for the transient to ensure each feed is unique and cached independently
		$transient_key = 'instagram_feed_' . md5( $instagram_access_token . $number_of_posts . $cache_duration );

		// Attempt to retrieve cached data
		$data = get_transient( $transient_key );

		// STEP: If no cache exists, we fetch fresh data

		if ( $data === false ) {
			// Construct the API URL for fetching Instagram posts
			$media_api_url = "https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&access_token=$instagram_access_token&limit=$number_of_posts";

			// Construct the API URL for fetching the Instagram account details
			$account_api_url = "https://graph.instagram.com/me?fields=username&access_token=$instagram_access_token";

			// Fetch and decode Instagram posts
			$mediaResponse = wp_remote_get( $media_api_url );

			if ( is_wp_error( $mediaResponse ) || wp_remote_retrieve_response_code( $mediaResponse ) != 200 ) {
				return $this->render_element_placeholder(
					[
						'icon-class' => 'ion-md-warning',
						'title'      => esc_html__( 'Failed to fetch Instagram posts.', 'bricks' ),
					]
				);
			}

			$media_body = json_decode( wp_remote_retrieve_body( $mediaResponse ), true );

			if ( ! isset( $media_body['data'] ) ) {
				return $this->render_element_placeholder(
					[
						'icon-class' => 'ion-md-warning',
						'title'      => esc_html__( 'No Instagram posts found.', 'bricks' ),
					]
				);
			}

			// Fetch and decode Instagram account details
			$response = wp_remote_get( $account_api_url );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				return $this->render_element_placeholder(
					[
						'icon-class' => 'ion-md-warning',
						'title'      => esc_html__( 'Failed to fetch Instagram account details.', 'bricks' ),
					]
				);
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! isset( $response_body['username'] ) ) {
				return $this->render_element_placeholder(
					[
						'icon-class' => 'ion-md-warning',
						'title'      => esc_html__( 'No Instagram account found.', 'bricks' ),
					]
				);
			}

			$data = [
				'username' => $response_body['username'],
				'media'    => $media_body['data'],
			];

			// Save the data to cache for the specified duration in transient
			set_transient( $transient_key, $data, intval( $cache_duration ) );
		}

		// STEP: Extract the posts and username
		$follow_icon = $settings['followIcon']['icon'] ?? false;
		$follow_text = $settings['followText'] ?? '';
		$follow_html = '';

		if ( $follow_icon || $follow_text ) {
			$username    = $data['username'] ?? '';
			$follow_html = '<a class="follow" href="https://instagram.com/' . $username . '" target="_blank">';

			if ( $follow_icon ) {
				$follow_html .= $this->render_icon( $settings['followIcon'] );
			}

			if ( $follow_text ) {
				$follow_html .= $follow_text;
			}

			$follow_html .= '</a>';
		}

		$output = "<div {$this->render_attributes( '_root' )}>";

		// Render follow section at the top
		if ( $follow_position == 'top' ) {
			$output .= $follow_html;
		}

		$output .= '<ul>';

		$posts = $data['media'] ?? [];

		foreach ( $posts as $post ) {
			$caption = $post['caption'] ?? '';

			$output .= '<li>';

			if ( isset( $settings['imageLink'] ) ) {
				$output .= '<a href="' . esc_url( $post['permalink'] ) . '" target="_blank">';
			}

			$output .= '<img src="' . esc_url( $post['media_url'] ) . '" alt="' . esc_attr( $caption ) . '">';

			if ( $caption && isset( $settings['caption'] ) ) {
				$output .= '<p class="caption">' . esc_html( $caption ) . '</p>';
			}

			if ( isset( $settings['imageLink'] ) ) {
				$output .= '</a>';
			}
			$output .= '</li>';
		}

		$output .= '</ul>';

		// Render follow section at the bottom
		if ( $follow_position == 'bottom' ) {
			$output .= $follow_html;
		}

		$output .= '</div>';

		echo $output;
	}
}
