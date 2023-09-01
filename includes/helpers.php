<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Helpers {
	/**
	 * Get template data from post meta
	 *
	 * @since 1.0
	 */
	public static function get_template_settings( $post_id ) {
		$template_settings = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, true );

		return $template_settings;
	}

	/**
	 * Store template settings
	 *
	 * @since 1.0
	 */
	public static function set_template_settings( $post_id, $settings ) {
		update_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, $settings );
	}

	/**
	 * Remove template settings from store
	 *
	 * @since 1.0
	 */
	public static function delete_template_settings( $post_id ) {
		delete_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS );
	}

	/**
	 * Get individual template setting by key
	 *
	 * @since 1.0
	 */
	public static function get_template_setting( $key, $post_id ) {
		$template_settings = self::get_template_settings( $post_id );

		return isset( $template_settings[ $key ] ) ? $template_settings[ $key ] : '';
	}

	/**
	 * Store a specific template setting
	 *
	 * @since 1.0
	 */
	public static function set_template_setting( $post_id, $key, $setting_value ) {
		$template_settings = self::get_template_settings( $post_id );

		if ( ! is_array( $template_settings ) ) {
			$template_settings = [];
		}

		$template_settings[ $key ] = $setting_value;

		self::set_template_settings( $post_id, $template_settings );
	}

	/**
	 * Get terms
	 *
	 * @param string $taxonomy
	 * @param string $post_type
	 * @param string $include_all Includes meta terms like "All terms (taxonomy name)"
	 *
	 * @since 1.0
	 */
	public static function get_terms_options( $taxonomy = null, $post_type = null, $include_all = false ) {
		$term_args = [ 'hide_empty' => false ];

		if ( isset( $taxonomy ) ) {
			$term_args['taxonomy'] = $taxonomy;
		}

		$cache_key = 'get_terms_options' . md5( 'taxonomy' . json_encode( $taxonomy ) . 'post_type' . json_encode( $post_type ) . 'include' . $include_all );

		$response = wp_cache_get( $cache_key, 'bricks' );

		if ( $response !== false ) {
			return $response;
		}

		$terms = get_terms( $term_args );

		$response = [];

		$all_terms = [];

		foreach ( $terms as $term ) {
			if (
				$term->taxonomy === 'nav_menu' ||
				$term->taxonomy === 'link_category' ||
				$term->taxonomy === 'post_format'
				// $term->taxonomy === BRICKS_DB_TEMPLATE_TAX_TAG
			) {
				continue;
			}

			// Skip term if term taxonomy is not a taxonomy of requested post type
			if ( isset( $post_type ) ) {
				$post_type_taxonomies = get_object_taxonomies( $post_type );

				if ( ! in_array( $term->taxonomy, $post_type_taxonomies ) ) {
					continue;
				}
			}

			// Store taxonomy name and term ID as WP_Query tax_query needs both (name and term ID)
			$taxonomy_object = get_taxonomy( $term->taxonomy );
			$taxonomy_label  = '';

			if ( gettype( $taxonomy_object ) === 'object' ) {
				$taxonomy_label = ' (' . $taxonomy_object->labels->name . ')';
			} else {
				if ( $term->taxonomy === BRICKS_DB_TEMPLATE_TAX_TAG ) {
					$taxonomy_label = ' (' . esc_html__( 'Template tag', 'bricks' ) . ')';
				}

				if ( $term->taxonomy === BRICKS_DB_TEMPLATE_TAX_BUNDLE ) {
					$taxonomy_label = ' (' . esc_html__( 'Template bundle', 'bricks' ) . ')';
				}
			}

			$all_terms[ $term->taxonomy . '::all' ] = esc_html__( 'All terms', 'bricks' ) . $taxonomy_label;

			$response[ $term->taxonomy . '::' . $term->term_id ] = $term->name . $taxonomy_label;
		}

		if ( $include_all ) {
			$response = array_merge( $all_terms, $response );
		}

		wp_cache_set( $cache_key, $response, 'bricks', 5 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Get users (for templatePreview)
	 *
	 * @param bool $show_role Show user role.
	 * @uses templatePreviewAuthor
	 *
	 * @since 1.0
	 */
	public static function get_users_options( $args, $show_role = false ) {
		$users = [];

		foreach ( get_users( $args ) as $user ) {
			$user_id = $user->ID;

			$user_roles = array_values( $user->roles );

			$value = get_the_author_meta( 'display_name', $user_id );

			if ( $show_role && ! empty( $user_roles[0] ) ) {
				global $wp_roles;

				$value .= ' (' . $wp_roles->roles[ $user_roles[0] ]['name'] . ')';
			}

			$users[ $user_id ] = $value;
		}

		return $users;
	}

	/**
	 * Get post edit link with appended query string to trigger builder
	 *
	 * @since 1.0
	 */
	public static function get_builder_edit_link( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return add_query_arg( BRICKS_BUILDER_PARAM, 'run', get_permalink( $post_id ) );
	}

	/**
	 * Get supported post types
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		$supported_post_types = Database::get_setting( 'postTypes', [] );
		$post_types_options   = [];

		foreach ( $supported_post_types as $post_type_slug ) {
			if ( $post_type_slug === 'attachment' ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type_slug );

			$post_types_options[ $post_type_slug ] = is_object( $post_type_object ) ? $post_type_object->labels->name : ucwords( str_replace( '_', ' ', $post_type_slug ) );
		}

		return $post_types_options;
	}

	/**
	 * Get registered post types
	 *
	 * Key: Post type name
	 * Value: Post type label
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_registered_post_types() {
		/**
		 * Hook to customise post type arguments
		 *
		 * Example: Return all registered post types, instead of only 'public' post types.
		 *
		 * https://academy.bricksbuilder.io/article/filter-bricks-registered_post_types_args/
		 *
		 * @since 1.6
		 */
		$registered_post_types_args = apply_filters(
			'bricks/registered_post_types_args',
			[
				'public' => true,
			]
		);

		$registered_post_types = get_post_types( $registered_post_types_args, 'objects' );

		// Remove post type: Bricks template (always has builder support)
		unset( $registered_post_types[ BRICKS_DB_TEMPLATE_SLUG ] );

		$post_types = [];

		foreach ( $registered_post_types as $key => $object ) {
			$post_types[ $key ] = $object->label;
		}

		return $post_types;
	}

	/**
	 * Is current post type supported by builder
	 *
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public static function is_post_type_supported( $post_id = 0 ) {
		$post_id = ! empty( $post_id ) ? $post_id : get_the_ID();

		// NOTE: Set post ID to posts page.
		if ( empty( $post_id ) && is_home() ) {
			$post_id = get_option( 'page_for_posts' );
		}

		$current_post_type = get_post_type( $post_id );

		// Bricks templates always have builder support
		if ( $current_post_type === BRICKS_DB_TEMPLATE_SLUG ) {
			return true;
		}

		$supported_post_types = Database::get_setting( 'postTypes', [] );

		return in_array( $current_post_type, $supported_post_types );
	}

	/**
	 * Return page-specific title
	 *
	 * @param int  $post_id
	 * @param bool $context
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_the_archive_title/
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_the_title( $post_id = 0, $context = false ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$preview_type = '';

		// Check if loading a Bricks template
		if ( self::is_bricks_template( $post_id ) ) {
			$preview_type = self::get_template_setting( 'templatePreviewType', $post_id );

			if ( $preview_type === 'archive-term' ) {
				$preview_term = self::get_template_setting( 'templatePreviewTerm', $post_id );
				if ( ! empty( $preview_term ) ) {
					$preview_term      = explode( '::', $preview_term );
					$preview_taxonomy  = isset( $preview_term[0] ) ? $preview_term[0] : '';
					$preview_term_id   = isset( $preview_term[1] ) ? intval( $preview_term[1] ) : '';
					$preview_term      = get_term_by( 'id', $preview_term_id, $preview_taxonomy );
					$preview_term_name = $preview_term ? $preview_term->name : '';
				}
			} elseif ( $preview_type == 'archive-cpt' ) {
				$preview_post_type = self::get_template_setting( 'templatePreviewPostType', $post_id );
			}
		}

		if ( Query::is_looping() && Query::get_loop_object_type() === 'post' ) {
			$title = get_the_title( $post_id );
		} elseif ( is_home() ) {
			$post_id = get_option( 'page_for_posts' );
			$title   = get_the_title( $post_id );
		} elseif ( is_404() ) {
			$title = isset( Database::$active_templates['error'] ) ? get_the_title( Database::$active_templates['error'] ) : esc_html__( 'Page not found', 'bricks' );
		} elseif ( is_category() || ( isset( $preview_taxonomy ) && $preview_taxonomy === 'category' ) ) {
			$category = isset( $preview_term_name ) ? $preview_term_name : single_cat_title( '', false );
			$category = apply_filters( 'single_cat_title', $category );
			$title    = $context ? sprintf( esc_html__( 'Category: %s', 'bricks' ), $category ) : $category;
		} elseif ( is_tag() || ( isset( $preview_taxonomy ) && $preview_taxonomy === 'post_tag' ) ) {
			$tag   = isset( $preview_term_name ) ? $preview_term_name : single_tag_title( '', false );
			$tag   = apply_filters( 'single_tag_title', $tag );
			$title = $context ? sprintf( esc_html__( 'Tag: %s', 'bricks' ), $tag ) : $tag;
		} elseif ( is_author() || $preview_type === 'archive-author' ) {
			if ( $preview_type === 'archive-author' ) {
				// Get author ID from template preview (as no $authordata exists)
				$template_preview_author = self::get_template_setting( 'templatePreviewAuthor', $post_id );
				$author                  = get_the_author_meta( 'display_name', $template_preview_author );
			} else {
				// @since 1.7.1 - get_the_author() might be wrong if some other query is running on the author archive page
				$author = get_the_author_meta( 'display_name', $post_id );
			}
			$author = ! empty( $author ) ? $author : '';
			$title  = $context ? sprintf( esc_html__( 'Author: %s', 'bricks' ), $author ) : $author;
		} elseif ( is_year() || $preview_type === 'archive-date' ) {
			$date  = $preview_type === 'archive-date' ? date( 'Y' ) : get_the_date( _x( 'Y', 'yearly archives date format' ) );
			$title = $context ? sprintf( esc_html__( 'Year: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_month() ) {
			$date  = get_the_date( _x( 'F Y', 'monthly archives date format' ) );
			$title = $context ? sprintf( esc_html__( 'Month: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_day() ) {
			$date  = get_the_date( _x( 'F j, Y', 'daily archives date format' ) );
			$title = $context ? sprintf( esc_html__( 'Day: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_tax( 'post_format' ) ) {
			if ( is_tax( 'post_format', 'post-format-aside' ) ) {
				$title = esc_html__( 'Asides', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
				$title = esc_html__( 'Galleries', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-image' ) ) {
				$title = esc_html__( 'Images', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
				$title = esc_html__( 'Videos', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-quote' ) ) {
				$title = esc_html__( 'Quotes', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-link' ) ) {
				$title = esc_html__( 'Links', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-status' ) ) {
				$title = esc_html__( 'Statuses', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-audio' ) ) {
				$title = esc_html__( 'Audio', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-chat' ) ) {
				$title = esc_html__( 'Chats', 'bricks' );
			}
		} elseif ( is_tax() || isset( $preview_taxonomy ) ) {
			$tax = isset( $preview_taxonomy ) ? $preview_taxonomy : get_queried_object()->taxonomy;
			$tax = get_taxonomy( $tax );

			$term  = isset( $preview_term_name ) ? $preview_term_name : single_term_title( '', false );
			$term  = apply_filters( 'single_term_title', $term );
			$title = $context ? $tax->labels->singular_name . ': ' . $term : $term;
		} elseif ( is_post_type_archive() || ! empty( $preview_post_type ) ) {
			if ( ! empty( $preview_post_type ) ) {
				$post_type_obj           = get_post_type_object( $preview_post_type );
				$post_type_archive_title = apply_filters( 'post_type_archive_title', $post_type_obj->labels->name, $preview_post_type );
			} else {
				$post_type_archive_title = post_type_archive_title( '', false );
			}

			$title = $context ? sprintf( esc_html__( 'Archives: %s', 'bricks' ), $post_type_archive_title ) : $post_type_archive_title;
		} elseif ( is_search() || $preview_type === 'search' ) {
			$search_query = $preview_type === 'search' ? self::get_template_setting( 'templatePreviewSearchTerm', $post_id ) : get_search_query();

			$title = $context ? sprintf( esc_html__( 'Results for: %s', 'bricks' ), $search_query ) : $search_query;

			if ( get_query_var( 'paged' ) ) {
				$title .= ' - ' . sprintf( esc_html__( 'Page %s', 'bricks' ), get_query_var( 'paged' ) );
			}
		} else {
			$preview_id = self::get_template_setting( 'templatePreviewPostId', $post_id );
			$preview_id = ! empty( $preview_id ) ? $preview_id : $post_id;
			$title      = get_the_title( $preview_id );
		}

		// NOTE: Undocumented
		return apply_filters( 'bricks/get_the_title', $title, $post_id );
	}


	/**
	 * Get the queried object which could also be set if previewing a template
	 *
	 * @see: https://developer.wordpress.org/reference/functions/get_queried_object/
	 *
	 * @param integer $post_id
	 * @return WP_Term|WP_User|WP_Post|WP_Post_Type
	 */
	public static function get_queried_object( $post_id ) {
		$queried_object = '';

		// Check if loading a Bricks template
		if ( self::is_bricks_template( $post_id ) ) {
			$preview_type = self::get_template_setting( 'templatePreviewType', $post_id );

			if ( $preview_type == 'single' ) {
				$preview_id     = self::get_template_setting( 'templatePreviewPostId', $post_id );
				$queried_object = get_post( $preview_id );
			} elseif ( $preview_type === 'archive-term' ) {
				$preview_term = self::get_template_setting( 'templatePreviewTerm', $post_id );

				if ( ! empty( $preview_term ) ) {
					$preview_term     = explode( '::', $preview_term );
					$preview_taxonomy = isset( $preview_term[0] ) ? $preview_term[0] : '';
					$preview_term_id  = isset( $preview_term[1] ) ? intval( $preview_term[1] ) : '';
					$queried_object   = get_term_by( 'id', $preview_term_id, $preview_taxonomy );
				}
			} elseif ( $preview_type == 'archive-cpt' ) {
				$preview_post_type = self::get_template_setting( 'templatePreviewPostType', $post_id );

				$queried_object = get_post_type_object( $preview_post_type );
			} elseif ( $preview_type == 'archive-author' ) {
				$template_preview_author = self::get_template_setting( 'templatePreviewAuthor', $post_id );

				$queried_object = get_user_by( 'id', $template_preview_author );
			}
		}

		// It is an ajax call but it is not inside a template
		elseif ( bricks_is_ajax_call() && isset( $_POST['action'] ) && strpos( $_POST['action'], 'bricks_' ) === 0 ) {
			$queried_object = get_post( $post_id );
		}

		// In a query loop
		elseif ( ( $looping_query_id = Query::is_any_looping() ) !== false ) {
			$queried_object = Query::get_loop_object( $looping_query_id );
		}

		if ( empty( $queried_object ) ) {
			$queried_object = get_queried_object();
		}

		return $queried_object;
	}

	/**
	 * Calculate the excerpt of a post (product, or any other cpt)
	 *
	 * @param WP_Post $post
	 * @param integer $excerpt_length
	 * @param string  $excerpt_more
	 * @param boolean $keep_html @since 1.6
	 * @return void
	 */
	public static function get_the_excerpt( $post, $excerpt_length, $excerpt_more = null, $keep_html = false ) {
		$post = get_post( $post );

		if ( empty( $post ) ) {
			return '';
		}

		if ( post_password_required( $post ) ) {
			return esc_html__( 'There is no excerpt because this is a protected post.', 'bricks' );
		}

		$text = $post->post_excerpt;

		// No excerpt, generate one
		if ( $text == '' ) {
			$post = get_post( $post );

			$text = get_the_content( '', false, $post );
			$text = strip_shortcodes( $text );
			$text = excerpt_remove_blocks( $text );
			$text = str_replace( ']]>', ']]&gt;', $text );
		}

		/**
		 * Apply excerpt length filter, if default $excerpt_length of 55 words is used
		 *
		 * To apply correct excerpt limit length in-loop in the builder: {post_excerpt:10}
		 *
		 * @since 1.8.6
		 */
		if ( $excerpt_length === 55 ) {
			$excerpt_length = apply_filters( 'excerpt_length', $excerpt_length );
		}

		$excerpt_more = isset( $excerpt_more ) ? $excerpt_more : '&hellip;';

		$excerpt_more = apply_filters( 'excerpt_more', $excerpt_more );

		$text = self::trim_words( $text, $excerpt_length, $excerpt_more, $keep_html );

		/**
		 * Filters the trimmed excerpt string.
		 *
		 * @param string $text The trimmed text.
		 * @param string $raw_excerpt The text prior to trimming.
		 *
		 * @since 2.8.0
		 */
		return apply_filters( 'wp_trim_excerpt', $text, $post->post_excerpt );
	}

	/**
	 * Trim a text string to a certain number of words.
	 *
	 * @since 1.6.2
	 *
	 * @param string  $text
	 * @param integer $length
	 * @param string  $more
	 * @param boolean $keep_html
	 */
	public static function trim_words( $text, $length, $more = null, $keep_html = false ) {
		if ( empty( $text ) ) {
			return '';
		}

		$more = isset( $more ) ? $more : '&hellip;';

		/**
		 * wp_trim_words() strips all HTML tags. We also need the ability to keep them. Example: {woo_product_excerpt}
		 *
		 * Refers to: https://stackoverflow.com/questions/36078264/i-want-to-allow-html-tag-when-use-the-wp-trim-words
		 *
		 * @since 1.6 - Added
		 */
		if ( $keep_html ) {
			$text = force_balance_tags( html_entity_decode( wp_trim_words( htmlentities( wpautop( $text ) ), $length, $more ) ) );
		} else {
			$text = wp_trim_words( $text, $length, $more );
		}

		return $text;
	}

	/**
	 * Posts navigation
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	public static function posts_navigation( $current_page, $total_pages ) {
		if ( $total_pages < 2 ) {
			return;
		}

		$args = [
			'type'      => 'list',
			'current'   => $current_page,
			'total'     => $total_pages,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		];

		// NOTE: Undocumented
		$args = apply_filters( 'bricks/paginate_links_args', $args );

		$posts_navigation_html = '<div class="bricks-pagination" role="navigation" aria-label="' . esc_attr__( 'Pagination', 'bricks' ) . '">';

		$posts_navigation_html .= paginate_links( $args );

		$posts_navigation_html .= '</div>';

		return $posts_navigation_html;
	}

	/**
	 * Pagination within post
	 *
	 * To add ul > li structure as 'link_before' & 'link_after' are not working.
	 *
	 * @since 1.8
	 */
	public static function page_break_navigation() {
		$pagination_html = wp_link_pages(
			[
				'before' => '<div class="bricks-pagination"><ul><span class="title">' . esc_html__( 'Pages:', 'bricks' ) . '</span>',
				'after'  => '</ul></div>',
				'echo'   => false,
			]
		);

		// Wrap each <a> in a <li>
    $pagination_html = str_replace( '<a', '<li><a', $pagination_html );
    $pagination_html = str_replace( '</a>', '</a></li>', $pagination_html );

		// Wrap each <span> (current page) in a <li>
		$pagination_html = str_replace( '<span', '<li><span', $pagination_html );
    $pagination_html = str_replace( '</span>', '</span></li>', $pagination_html );

		return $pagination_html;
	}

	/**
	 * Element placeholder HTML
	 *
	 * @since 1.0
	 */
	public static function get_element_placeholder( $data ) {
		// For custom context menu
		$element_id = ! empty( $data['id'] ) ? $data['id'] : '';

		// Placeholder style for Shortcode element 'showPlaceholder' (@since 1.7.2)
		$styles = ! empty( $data['style'] ) ? $data['style'] : '';
		$style  = '';

		if ( is_array( $styles ) ) {
			foreach ( $styles as $css_property => $css_value ) {
				if ( $css_value !== '' ) {
					// Value is number: Add defaultUnit 'px'  to the end
					if ( is_numeric( $css_value ) ) {
						$css_value .= 'px';
					}

					$style .= "$css_property: $css_value;";
				}
			}
		}

		if ( $style ) {
			$output = '<div class="bricks-element-placeholder" data-id="' . $element_id . '" style="' . $style . '">';
		} else {
			$output = '<div class="bricks-element-placeholder" data-id="' . $element_id . '">';
		}

		if ( ! empty( $data['icon-class'] ) ) {
			$output .= '<i class="' . sanitize_html_class( $data['icon-class'] ) . '"></i>';
		}

		$output .= '<div class="placeholder-inner">';

		if ( ! empty( $data['title'] ) ) {
			$output .= '<div class="placeholder-title">' . $data['title'] . '</div>';
		}

		if ( ! empty( $data['description'] ) ) {
			$output .= '<div class="placeholder-description">' . $data['description'] . '</div>';
		}

		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Retrieves the element, the complete set of elements and the template/page ID where element belongs to
	 *
	 * NOTE: This function does not check for global element settings
	 *
	 * @since 1.5
	 *
	 * @return void
	 */
	public static function get_element_data( $post_id, $element_id ) {
		// @since 1.7 - $post_id can be zero if home page is set to latest posts
		if ( empty( $element_id ) ) {
			return false;
		}

		$output = [
			'element'   => [], // The element we want to find
			'elements'  => [], // The complete set of elements where the element is included
			'source_id' => 0   // The post_id of the page or template where the element was set
		];

		// Get page_data via passed post_id
		if ( bricks_is_ajax_call() || bricks_is_rest_call() ) {
			Database::set_active_templates( $post_id );
		}

		$templates = [];

		$areas = [ 'content', 'header', 'footer' ];

		foreach ( $areas as $area ) {
			$elements = Database::get_data( Database::$active_templates[ $area ], $area );

			if ( ! empty( $elements ) && is_array( $elements ) ) {

				foreach ( $elements as $element ) {
					if ( $element['id'] == $element_id ) {
						$output = [
							'element'   => $element,
							'elements'  => $elements,
							'source_id' => Database::$active_templates[ $area ]
						];

						break ( 2 );
					}

					if ( $element['name'] === 'template' && ! empty( $element['settings']['template'] ) ) {
						$templates[] = $element['settings']['template'];
					}

					if ( $element['name'] === 'post-content' && ! empty( $element['settings']['dataSource'] ) && $element['settings']['dataSource'] == 'bricks' ) {
						$templates[] = $post_id;
					}
				}
			}
		}

		// Not found yet?
		if ( empty( $output['element'] ) ) {

			// If we are still here, try to run through the found templates first, and remaining templates later
			$all_templates_query = Templates::get_templates_query( [ 'fields' => 'ids' ] );
			$all_templates       = ! empty( $all_templates_query->found_posts ) ? $all_templates_query->posts : [];

			$templates = array_merge( $templates, $all_templates );
			$templates = array_unique( $templates );

			foreach ( $templates as $template_id ) {
				$elements = get_post_meta( $template_id, BRICKS_DB_PAGE_CONTENT, true );

				if ( empty( $elements ) || ! is_array( $elements ) ) {
					continue;
				}

				foreach ( $elements as $element ) {
					if ( $element['id'] === $element_id ) {
						$output = [
							'element'   => $element,
							'elements'  => $elements,
							'source_id' => $template_id
						];

						break ( 2 );
					}
				}
			}
		}

		if ( empty( $output['element'] ) ) {
			return false;
		}

		return $output;
	}

	/**
	 * Get settings of specific element (for use in AJAX functions such as form submit)
	 *
	 * @since 1.0
	 */
	public static function get_element_settings( $post_id, $element_id ) {
		if ( ! isset( $post_id ) ) {
			return 'No postId provided';
		}

		if ( ! isset( $element_id ) ) {
			return 'No elementId provided';
		}

		$data = self::get_element_data( $post_id, $element_id );

		if ( ! $data || empty( $data['element']['settings'] ) ) {
			return false;
		}

		// Retrieve global settings if exist
		$global_settings = self::get_global_element( $data['element'], 'settings' );

		return is_array( $global_settings ) ? $global_settings : $data['element']['settings'];
	}

	/**
	 * Get data of specific global element
	 *
	 * @param array $element
	 *
	 * @return boolean|array false if no global element found, else return the global element data.
	 *
	 * @since 1.3.5
	 */
	public static function get_global_element( $element = [], $key = '' ) {
		$data = false;

		foreach ( Database::$global_data['elements'] as $global_element ) {
			// @since 1.2.1 (check against element 'global' property)
			if (
				! empty( $global_element['global'] ) &&
				! empty( $element['global'] ) &&
				$global_element['global'] === $element['global']
			) {
				$data = $key && isset( $global_element[ $key ] ) ? $global_element[ $key ] : $global_element;
			}

			// @pre 1.2.1 (check against element 'id' property)
			elseif (
				! empty( $global_element['id'] ) &&
				! empty( $element['id'] ) &&
				$global_element['id'] === $element['id']
			) {
				$data = $key && isset( $global_element[ $key ] ) ? $global_element[ $key ] : $global_element;
			}

			if ( $data ) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Get posts options (max 50 results)
	 *
	 * @param string
	 *
	 * @since 1.0
	 */
	public static function get_posts_by_post_id( $query_args = [] ) {
		// NOTE: Undocumented
		$query_args = apply_filters( 'bricks/helpers/get_posts_args', $query_args );

		$query_args = wp_parse_args(
			$query_args,
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'post_type',
				'order'          => 'DESC',
				'no_found_rows'  => true
			]
		);

		// Query max. 100 posts to avoid running into any memory limits
		if ( $query_args['posts_per_page'] == -1 ) {
			$query_args['posts_per_page'] = 100;
		}

		unset( $query_args['fields'] ); // Make sure the output is standard

		// Don't specify meta_key to get all posts for 'templatePreviewPostId'
		$posts = get_posts( $query_args );

		$posts_options = [];

		foreach ( $posts as $post ) {
			// Skip non-content templates (header template, footer template)
			if ( $post->post_type === BRICKS_DB_TEMPLATE_SLUG && Templates::get_template_type( $post->ID ) !== 'content' ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post->post_type );

			$post_title  = get_the_title( $post );
			$post_title .= $post_type_object ? ' (' . $post_type_object->labels->singular_name . ')' : ' (' . ucfirst( $post->post_type ) . ')';

			$posts_options[ $post->ID ] = $post_title;
		}

		return $posts_options;
	}

	/**
	 * Get a list of supported content types for template preview
	 *
	 * @return array
	 */
	public static function get_supported_content_types() {
		$types = [
			'archive-recent-posts' => esc_html__( 'Archive (recent posts)', 'bricks' ),
			'archive-author'       => esc_html__( 'Archive (author)', 'bricks' ),
			'archive-date'         => esc_html__( 'Archive (date)', 'bricks' ),
			'archive-cpt'          => esc_html__( 'Archive (posts)', 'bricks' ),
			'archive-term'         => esc_html__( 'Archive (term)', 'bricks' ),
			'search'               => esc_html__( 'Search results', 'bricks' ),
			'single'               => esc_html__( 'Single post/page', 'bricks' ),
		];

		// NOTE: Undocumented
		$types = apply_filters( 'bricks/template_preview/supported_content_types', $types );

		return $types;
	}

	/**
	 * Get editor mode of requested page
	 *
	 * @param int $post_id
	 *
	 * @since 1.0
	 */
	public static function get_editor_mode( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return get_post_meta( $post_id, BRICKS_DB_EDITOR_MODE, true );
	}

	/**
	 * Check if post/page/cpt renders with Bricks
	 *
	 * @param integer $post_id / $queried_object_id
	 * @return boolean
	 */
	public static function render_with_bricks( $post_id = 0 ) {
		// When editing with Elementor we need to tell Bricks to render templates as WordPress
		// @see https://elementor.com/help/the-content-area-was-not-found-error/
		if ( isset( $_GET['elementor-preview'] ) ) {
			return false;
		}

		// NOTE: Undocumented (@since 1.5.4)
		$render = apply_filters( 'bricks/render_with_bricks', null, $post_id );

		// Returm only if false otherwise it doesn't perform other important checks (@since 1.5.4)
		if ( $render === false ) {
			return false;
		}

		// Skip WooCommerce, if disabled on Bricks Settings in case is_shop
		if ( ! Woocommerce::$is_active && function_exists( 'is_shop' ) && is_shop() ) {
			return false;
		}

		// Check current page type
		$current_page_type = isset( Database::$page_data['current_page_type'] ) ? Database::$page_data['current_page_type'] : '';

		/**
		 * Password protected
		 *
		 * Execute post_password_required() only for posts or pages (@since 1.8.4 (#863h700vb))
		 * Otherwise will return incorrect results if the $post_id is a taxonomy ID, etc.
		 * https://developer.wordpress.org/reference/functions/post_password_required/
		 */
		if ( $current_page_type === 'post' && post_password_required( $post_id ) ) {
			return false;
		}

		$editor_mode = self::get_editor_mode( $post_id );

		if ( $editor_mode === 'wordpress' ) {
			return false;
		}

		/**
		 * Paid Memberships Pro: Restrict Bricks content (@since 1.5.4)
		 * Only execute if current page is a post (@since 1.8.4 (#863h700vb))
		 * https://www.paidmembershipspro.com/hook/pmpro_has_membership_access_filter/
		 */
		if ( $current_page_type === 'post' && function_exists( 'pmpro_has_membership_access' ) ) {
			$user_id                     = null; // Retrieve inside pmpro_has_membership_access directly
			$return_membership_levels    = false; // Return boolean
			$pmpro_has_membership_access = pmpro_has_membership_access( $post_id, $user_id, $return_membership_levels );

			return $pmpro_has_membership_access;
		}

		return true;
	}

	/**
	 * Get Bricks data for requested page
	 *
	 * @param integer $post_id The post ID.
	 * @param string  $type header, content, footer.
	 *
	 * @since 1.3.4
	 *
	 * @return boolean|array
	 */
	public static function get_bricks_data( $post_id = 0, $type = 'content' ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		// Return if requested post is not rendered with Bricks
		if ( ! self::render_with_bricks( $post_id ) ) {
			return false;
		}

		$bricks_data = Database::get_template_data( $type );

		if ( ! is_array( $bricks_data ) ) {
			return false;
		}

		if ( ! count( $bricks_data ) ) {
			return false;
		}

		return $bricks_data;
	}

	public static function delete_bricks_data_by_post_id( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		// Return post edit URL: No post ID found
		if ( ! $post_id ) {
			return get_edit_post_link();
		}

		return add_query_arg(
			[
				'bricks_delete_post_meta' => $post_id,
				'bricks_notice'           => 'post_meta_deleted',
			],
			get_edit_post_link()
		);
	}

	/**
	 * Generate random hash
	 *
	 * Default: 6 characters long
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	public static function generate_hash( $string, $length = 6 ) {
		// Generate SHA1 hexadecimal string (40-characters)
		$sha1        = sha1( $string );
		$sha1_length = strlen( $sha1 );
		$hash        = '';

		// Generate random site hash based on SHA1 string
		for ( $i = 0; $i < $length; $i++ ) {
			$hash .= $sha1[ rand( 0, $sha1_length - 1 ) ];
		}

		// Convert site path to lowercase
		$hash = strtolower( $hash );

		return $hash;
	}

	public static function generate_random_id( $echo = true ) {
		$hash = self::generate_hash( md5( uniqid( rand(), true ) ) );

		if ( $echo ) {
			echo $hash;
		}

		return $hash;
	}

	/**
	 * Get file contents from file system
	 *
	 * .svg, .json (Google fonts), etc.
	 *
	 * @since 1.8.1
	 */
	public static function file_get_contents( $file_path, ...$args ) {
		// Return: File not found
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		// Return: File not readable
		if ( ! is_readable( $file_path ) ) {
			return '';
		}

		// STEP: Get file contents
		$file_contents = file_get_contents( $file_path, ...$args );

		// Return: Empty file contents
		if ( empty( $file_contents ) ) {
			return '';
		}

		return $file_contents;
	}

	/**
	 * Return WP dashboard Bricks settings url
	 *
	 * @since 1.0
	 */
	public static function settings_url( $params = '' ) {
		return admin_url( "/admin.php?page=bricks-settings$params" );
	}

	/**
	 * Return Bricks Academy link
	 *
	 * @since 1.0
	 */
	public static function article_link( $path, $text ) {
		return '<a href="https://academy.bricksbuilder.io/article/' . $path . '" target="_blank" rel="noopener">' . $text . '</a>';
	}

	/**
	 * Return the edit post link (ot the preview post link)
	 *
	 * @since 1.2.1
	 * @param $post_id
	 * @return string
	 */
	public static function get_preview_post_link( $post_id ) {
		$template_preview_post_id = self::get_template_setting( 'templatePreviewPostId', $post_id );

		if ( $template_preview_post_id ) {
			$post_id = $template_preview_post_id;
		}

		return get_edit_post_link( $post_id );
	}

	/**
	 * Dev helper to var dump nicely formatted
	 *
	 * @since 1.0
	 */
	public static function pre_dump( $data ) {
		echo '<pre>';
		var_dump( $data );
		echo '</pre>';
	}

	/**
	 * Dev helper to error log array values
	 *
	 * @since 1.0
	 */
	public static function log( $data ) {
		error_log( print_r( $data, true ) );
	}

	/**
	 * Custom wp_remote_get function
	 */
	public static function remote_get( $url, $args = [] ) {
		if ( ! isset( $args['timeout'] ) ) {
			$args['timeout'] = 30;
		}

		// Disable to avoid Let's Encrypt SSL root certificate expiration issue
		if ( ! isset( $args['sslverify'] ) ) {
			$args['sslverify'] = false;
		}

		$args = apply_filters( 'bricks/remote_get', $args, $url );

		return wp_remote_get( $url, $args );
	}

	/**
	 * Custom wp_remote_post function
	 *
	 * @since 1.3.5
	 */
	public static function remote_post( $url, $args = [] ) {
		if ( ! isset( $args['timeout'] ) ) {
			$args['timeout'] = 30;
		}

		// Disable to avoid Let's Encrypt SSL root certificate expiration issue
		if ( ! isset( $args['sslverify'] ) ) {
			$args['sslverify'] = false;
		}

		$args = apply_filters( 'bricks/remote_post', $args, $url );

		return wp_remote_post( $url, $args );
	}

	/**
	 * Generate swiperJS breakpoint data-options (carousel, testimonial)
	 *
	 * Set slides to show & scroll per breakpoint.
	 * Swiper breakpoint values use "min-width". so descent breakpoints from largest to smallest.
	 *
	 * https://swiperjs.com/swiper-api#param-breakpoints
	 *
	 * @since 1.3.5
	 *
	 * @since 1.5.1: removed old 'responsive' repeater controls due to custom breakpoints
	 */
	public static function generate_swiper_breakpoint_data_options( $settings ) {
		$breakpoints = [];

		foreach ( Breakpoints::$breakpoints as $index => $breakpoint ) {
			$key = $breakpoint['key'];

			// Get min-width value from width of next smaller breakpoint
			$min_width = ! empty( Breakpoints::$breakpoints[ $index + 1 ]['width'] ) ? intval( Breakpoints::$breakpoints[ $index + 1 ]['width'] ) + 1 : 1;

			// 'desktop' breakpoint (plain setting key)
			if ( $key === 'desktop' ) {
				if ( ! empty( $settings['slidesToShow'] ) ) {
					$breakpoints[ $min_width ]['slidesPerView'] = intval( $settings['slidesToShow'] );
				}

				if ( ! empty( $settings['slidesToScroll'] ) ) {
					$breakpoints[ $min_width ]['slidesPerGroup'] = intval( $settings['slidesToScroll'] );
				}
			}

			// Non-desktop breakpoint
			else {
				if ( ! empty( $settings[ "slidesToShow:{$key}" ] ) ) {
					$breakpoints[ $min_width ]['slidesPerView'] = intval( $settings[ "slidesToShow:{$key}" ] );
				}

				if ( ! empty( $settings[ "slidesToScroll:{$key}" ] ) ) {
					$breakpoints[ $min_width ]['slidesPerGroup'] = intval( $settings[ "slidesToScroll:{$key}" ] );
				}
			}
		}

		return $breakpoints;
		// return array_reverse( $breakpoints, true );
	}

	/**
	 * Generate swiperJS autoplay options (carousel, slider, testimonial)
	 *
	 * @since 1.5.7
	 */
	public static function generate_swiper_autoplay_options( $settings ) {
		return [
			'delay'                => isset( $settings['autoplaySpeed'] ) ? intval( $settings['autoplaySpeed'] ) : 3000,

			// Set to false if 'pauseOnHover' is true to prevent swiper stopping after first hover
			'disableOnInteraction' => ! isset( $settings['pauseOnHover'] ),

			// Pause autoplay on mouse enter (new in v6.6: autoplay.pauseOnMouseEnter)
			'pauseOnMouseEnter'    => isset( $settings['pauseOnHover'] ),

			// Stop autoplay on last slide (@since 1.4)
			'stopOnLastSlide'      => isset( $settings['stopOnLastSlide'] ),
		];
	}

	/**
	 * Sanitize Bricks data
	 *
	 * @since 1.3.7
	 */
	public static function sanitize_bricks_data( $elements ) {
		if ( is_array( $elements ) ) {
			foreach ( $elements as $index => $element ) {
				// STEP: Code element: Remove "Execute Code" setting to prevent executing potentially malicious code
				if ( isset( $element['settings']['executeCode'] ) ) {
					unset( $elements[ $index ]['settings']['executeCode'] );
				}
			}
		}

		return $elements;
	}

	/**
	 * Set is_frontend = false to a element
	 *
	 * Use: $elements = array_map( 'Bricks\Helpers::set_is_frontend_to_false', $elements );
	 *
	 * @since 1.4
	 */
	public static function set_is_frontend_to_false( $element ) {
		$element['is_frontend'] = false;

		return $element;
	}

	/**
	 * Get post IDs of all Bricks-enabled post types
	 *
	 * @see admin.php get_converter_items()
	 * @see files.php get_css_files_list()
	 *
	 * @param $custom_args array Custom get_posts() arguments (@since 1.8; @see get_css_files_list)
	 *
	 * @since 1.4
	 */
	public static function get_all_bricks_post_ids( $custom_args = [] ) {
		$args = array_merge( [
			'post_type'              => array_keys( self::get_supported_post_types() ),
			'posts_per_page'         => -1,
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => BRICKS_DB_PAGE_CONTENT,
					'value'   => '',
					'compare' => '!=',
				],
			],
		], $custom_args );

		return get_posts( $args );
	}

	/**
	 * Search & replace: Works for strings & arrays
	 *
	 * @param string $search  The value being searched for.
	 * @param string $replace The replacement value that replaces found search values
	 * @param string $search  The string or array being searched and replaced on, otherwise known as the haystack.
	 *
	 * @see templates.php import_template()
	 *
	 * @since 1.4
	 */
	public static function search_replace( $search, $replace, $data ) {
		$is_array = is_array( $data );

		// Stringify array
		if ( $is_array ) {
			$data = json_encode( $data );
		}

		// Replace string
		$data = str_replace( $search, $replace, $data );

		// Convert back to array
		if ( $is_array ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}

	/**
	 * Google fonts are disabled (via filter OR Bricks setting)
	 *
	 * @see https://academy.bricksbuilder.io/article/filter-bricks-assets-load_webfonts
	 *
	 * @since 1.4
	 */
	public static function google_fonts_disabled() {
		return ! apply_filters( 'bricks/assets/load_webfonts', true ) || isset( Database::$global_settings['disableGoogleFonts'] );
	}

	/**
	 * Sort variable Google Font axis (all lowercase before all uppercase)
	 *
	 * https://developers.google.com/fonts/docs/css2#strictness
	 *
	 * @since 1.8
	 */
	public static function google_fonts_get_axis_rank( $axis ) {
		// lowercase axis first
		if ( ctype_lower( $axis ) ) {
			return 0;
		}

		// uppercase axis second
		return 1;
	}

	/**
	 * Stringify HTML attributes
	 *
	 * @param array $attributes key = attribute key; value = attribute value (string|array)
	 *
	 * @see bricks/header/attributes
	 * @see bricks/footer/attributes
	 * @see bricks/popup/attributes
	 *
	 * @return string
	 *
	 * @since 1.5
	 */
	public static function stringify_html_attributes( $attributes ) {
		$strings = [];

		foreach ( $attributes as $key => $value ) {
			// Array: 'class', etc.
			if ( is_array( $value ) ) {
				$value = join( ' ', $value );
			}

			// To escape json strings (@since 1.6)
			$value = esc_attr( $value );

			$strings[] = "{$key}=\"$value\"";
		}

		return join( ' ', $strings );
	}

	/**
	 * Return element attribute 'id'
	 *
	 * @since 1.5.1
	 *
	 * @since 1.7.1: Parse dynamic data for _cssId (same for _cssClasses)
	 */
	public static function get_element_attribute_id( $id, $settings ) {
		$attribute_id = "brxe-{$id}";

		if ( ! empty( $settings['_cssId'] ) ) {
			$attribute_id = bricks_render_dynamic_data( $settings['_cssId'] );
		}

		return esc_attr( $attribute_id );
	}

	/**
	 * Based on the current user capabilities, check if the new elements could be changed on save (AJAX::save_post())
	 *
	 * If user can only edit the content:
	 *  - Check if the number of elements is the same
	 *  - Check if the new element already existed before
	 *
	 * If user cannot execute code:
	 *  - Replace any code element (with execution enabled) by the saved element,
	 *  - or disable the execution (in case the element is new)
	 *
	 * @since 1.5.4
	 *
	 * @param array                                $new_elements
	 * @param integer                              $post_id
	 * @param string 'header', 'content', 'footer'
	 *
	 * @return array Array of elements
	 */
	public static function security_check_elements_before_save( $new_elements, $post_id, $area ) {
		$user_has_full_access  = Capabilities::current_user_has_full_access();
		$user_can_execute_code = Capabilities::current_user_can_execute_code();

		// Return elements (user has full access & execute code permission)
		if ( $user_has_full_access && $user_can_execute_code ) {
			return $new_elements;
		}

		// Get old data structure from the database
		$area_key     = Database::get_bricks_data_key( $area );
		$old_elements = get_post_meta( $post_id, $area_key, true );

		// Initial data integrity check
		$new_elements = is_array( $new_elements ) ? $new_elements : [];
		$old_elements = is_array( $old_elements ) ? $old_elements : [];

		// STEP: Return old elements: User is not allowed to edit the structure, but the number of new elements differs from old structure
		if ( ! $user_has_full_access && count( $new_elements ) !== count( $old_elements ) ) {
			return $old_elements;
		}

		$old_elements_indexed = [];

		// Index the old elements for faster check
		foreach ( $old_elements as $element ) {
			$old_elements_indexed[ $element['id'] ] = $element;
		}

		foreach ( $new_elements as $index => $element ) {
			// STEP: Check for code elements if user doesn't have permission and execution is allowed
			if ( $element['name'] === 'code' && ! $user_can_execute_code && ! empty( $element['settings']['executeCode'] ) ) {
				// Replace new element with old element (if it exists)
				if ( isset( $old_elements_indexed[ $element['id'] ] ) ) {
					$new_elements[ $index ] = $old_elements_indexed[ $element['id'] ];
				}

				// Disable execution mode
				else {
					unset( $new_elements[ $index ]['settings']['executeCode'] );
				}
			}

			// STEP: Data integrity check: New elements found despite the user can only edit content: Remove element
			if ( ! $user_has_full_access && ! isset( $old_elements_indexed[ $element['id'] ] ) ) {
				unset( $new_elements[ $index ] );
			}
		}

		return $new_elements;
	}

	/**
	 * Parse CSS & return empty string if checks are not fulfilled
	 *
	 * @since 1.6.2
	 */
	public static function parse_css( $css ) {
		if ( ! $css ) {
			return $css;
		}

		// CSS syntax error: Number of opening & closing tags differs
		if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) {
			return '';
		}

		return $css;
	}

	/**
	 * Save global classes in options table
	 *
	 * Skip saving empty global classes array.
	 *
	 * Triggered in:
	 *
	 * ajax.php:      wp_ajax_bricks_save_post (save post in builder)
	 * templates.php: wp_ajax_bricks_import_template (template import)
	 * converter.php: wp_ajax_bricks_run_converter (run converter from Bricks settings)
	 *
	 * @since 1.7
	 *
	 * @param array  $global_classes
	 * @param string $action
	 */
	public static function save_global_classes_in_db( $global_classes, $action ) {
		$response = '';

		// Update global classes (if not empty)
		if ( is_array( $global_classes ) && count( $global_classes ) ) {
			$response = update_option( BRICKS_DB_GLOBAL_CLASSES, $global_classes );

			// // Update global class options log entry for debug purposes
			// $date_format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			// $current_time = date_i18n( $date_format );
			// $action       = (string) $action;
			// $log          = [
			// 'time'   => $current_time,
			// 'action' => $action,
			// ];

			// update_option( 'bricks_global_classes_last_update', $log, false ); // Don't autoload
		}

		/**
		 * Delete empty global classes if filter below is set to true
		 *
		 * @since 1.7: No longer in use to prevent deleting all global classes by accident.
		 */
		// else {
		// $delete_all_global_classes = apply_filters( 'bricks/global_classes/delete_all', false );

		// if ( $delete_all_global_classes ) {
		// $response = delete_option( BRICKS_DB_GLOBAL_CLASSES );
		// }
		// }

		return $response;
	}

	/**
	 * Parse TinyMCE editor control data
	 *
	 * Use instead of applying 'the_content' filter to prevent rendering third-party content in within non "Post Content" elements.
	 *
	 * Available as static function to use in get_dynamic_data_preview_content as well (DD tag render on canvas)
	 *
	 * @see accordion, alert, icon-box, slider, tabs, text
	 *
	 * @since 1.7
	 */
	public static function parse_editor_content( $content = '' ) {
		// Return: Not a text string (e.g. ACF field type color array)
		if ( ! is_string( $content ) ) {
			return $content;
		}

		/**
		 * Remove outermost <p> tag (from rich text element) if it contains a block-level HTML tag (like an <div>, <h2>, etc.)
		 *
		 * Example: <p>{acf_eysiwyg}</p>, and the ACF DD tag contains: <h2>ACF heading</h2>
		 * Rendered as: <p></p><h2>ACF heading</h2><p></p>
		 * Expected: <h2>ACF heading</h2>
		 *
		 * @since 1.7
		 */
		if ( strpos( $content, '<p>' ) === 0 && strpos( $content, '</p>' ) !== false ) {
			$content = preg_replace( '/^<p>(.*)<\/p>$/is', '$1', $content );
		}

		/**
		 * WordPress code default-filters.php reference
		 *
		 * Priority: 8
		 * run_shortcode
		 * autoembed
		 *
		 * Priority: 9
		 * do_blocks
		 *
		 * Priority: 10
		 * wptexturize
		 * wpautop
		 * shortcode_unautop
		 * prepend_attachment
		 * wp_filter_content_tags
		 * wp_replace_insecure_home_url
		 *
		 * Priority: 11
		 * capital_P_dangit
		 * do_shortcode
		 *
		 * Priority: 20
		 * convert_smilies
		 */

		// Passes any unlinked URLs that are on their own line to WP_Embed::shortcode() for potential embedding (audio, video)
		if ( $GLOBALS['wp_embed'] instanceof \WP_Embed ) {
			$content = $GLOBALS['wp_embed']->autoembed( $content );
		}

		// Priority: 10
		$content = wptexturize( $content );
		$content = wpautop( $content );
		$content = shortcode_unautop( $content );
		// $content = prepend_attachment( $content );

		// Add srcset, sizes, and loading attributes to img HTML tags; and loading attributes to iframe HTML tags
		$content = wp_filter_content_tags( $content );
		$content = wp_replace_insecure_home_url( $content );

		// Priority: 11
		$content = do_shortcode( $content );

		// Priority: 20
		$content = convert_smilies( $content );

		return $content;
	}

	/**
	 * Check if post_id is a Bricks template
	 *
	 * Previously used get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG
	 * But this method might accidentally return true if $post_id is a term_id or user_id, etc.
	 *
	 * @since 1.8
	 */
	public static function is_bricks_template( $post_id ) {
		// Check current page type
		$current_page_type = isset( Database::$page_data['current_page_type'] ) ? Database::$page_data['current_page_type'] : '';

		// In loop: Get object type of loop
		if ( Query::is_any_looping() ) {
			$looping_query_id    = Query::is_any_looping();
			$looping_object_type = Query::get_loop_object_type( $looping_query_id );
			$current_page_type   = $looping_object_type;
		}

		return $current_page_type === 'post' && get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG;
	}

	/**
	 * Check if the element settings contain a specific value
	 *
	 * Useful if the setting has diffrent keys in different breakpoints.
	 *
	 * Example: 'overlay', 'overlay:mobile_portrait', 'overlay:tablet_landscape', etc.
	 *
	 * Usage:
	 * Helpers::element_setting_has_value( 'overlay', $settings ); // Check if $settings contains 'overlay' setting in any breakpoint
	 * Helpers::element_setting_has_value( 'overlay:mobile', $settings ); // Check if $settings contains 'overlay' setting in mobile breakpoint
	 *
	 * @since 1.8
	 *
	 * @param string $key
	 * @param array $settings
	 *
	 * @return bool
	 */
	public static function element_setting_has_value( $key = '', $settings = [] ) {
		if ( ! is_array( $settings ) || empty( $key ) ) {
			return false;
		}

		$has_setting = false;

		if ( is_array( $settings ) && count( $settings ) ) {
			// Search array keys for where starts with $key
			$setting_keys = array_filter( array_keys( $settings ), function ( $setting_key ) use ( $key ) {
				return strpos( $setting_key, $key ) === 0;
			} );

			if ( count( $setting_keys ) ) {
				// Assume the first key is the one we're looking for
				$first_key = reset( $setting_keys );
				// Check if the value is not empty
				$has_setting = ! empty( $settings[ $first_key ] );
			}
		}

		return $has_setting;
	}

	/**
	 * Check if the provided url string is the current landed page
	 *
	 * @since 1.8
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function maybe_set_aria_current_page( $url = '' ) {
		if ( empty( $url ) ) {
			return false;
		}

		$set_aria_current = false;

		// Try to get post ID from URL
		$post_id_of_link = url_to_postid( $url );

		if ( ! $post_id_of_link ) {
			// Not a post or page
			if ( is_front_page() ) {
				// Front page
				$front_page_id = absint( get_option( 'page_on_front' ) );

				// Static page as front page
				if ( $front_page_id > 0 ) {
					$set_aria_current = $front_page_id === url_to_postid( $url ) || '/' === $url;
				}

				// Latest posts as front page ($front_page_id === 0)
				// Check if homepage URL is the same as the URL we are checking after removing trailing slashes, maybe user will use '/' as well
				else {
					$set_aria_current = untrailingslashit( home_url('/') ) === untrailingslashit( $url ) || '/' === $url;
				}
			}

			// Posts page(is_home()), Category, tag, archive etc.
			else {
				global $wp;
				$requested_url = trailingslashit( home_url( $wp->request ) );

				// URL begins with one slash (e.g. /category/business/): Add home URL
				if ( substr( $url, 0, 1 ) === '/' && substr( $url, 0, 2 ) !== '/' ) {
					$url = trailingslashit( home_url( $url ) );
				}

				$set_aria_current = strcmp( rtrim( $url ), rtrim( $requested_url ) ) === 0;
			}
		}

		// Post or page
		else {
			// Inside query loop
			if ( Query::is_any_looping() ) {
				$set_aria_current = $post_id_of_link && $post_id_of_link == get_queried_object_id() && is_singular();
			}

			// Single post or page
			elseif ( $post_id_of_link == get_the_ID() ) {
				$set_aria_current = true;
			}

			// Check: Is anchestor of current page (check recursively on all parent levels)
			else {
				$set_aria_current = self::is_post_ancestor( get_queried_object_id(), $post_id_of_link );
			}
		}

		// Undocumented: Currently used in includes/woocommerce.php
		return apply_filters( 'bricks/element/maybe_set_aria_current_page', $set_aria_current, $url );
	}

	/**
	 * Check recursively if a post is an ancestor of another post.
	 *
	 * @since 1.8
	 *
	 * @param int $post_id
	 * @param int $ancestor_id
	 * @return bool
	 */
	public static function is_post_ancestor( $post_id, $ancestor_id ) {
		// Return: Not the same post type (@since 1.8.2)
		if ( get_post_type( $post_id ) !== get_post_type( $ancestor_id ) ) {
			return false;
		}

		// Return: Ancestor is 0
		if ( $ancestor_id == 0 ) {
			return false;
		}

		$parent_id = wp_get_post_parent_id( $post_id );

		if ( $parent_id == $ancestor_id ) {
			return true;
		}

		// Return: Top-level has no parent
		if ( $parent_id == 0 ) {
			return false;
		}

		// Recursively check parent's parent
		return self::is_post_ancestor( $parent_id, $ancestor_id );
	}

}