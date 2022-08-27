<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Adding Booking & Appointment Availability Search Widget and allows to add settings
 *
 * @author      Tyche Softwares
 * @package     BKAP/Search-Widget
 * @since       1.7
 * @category    Classes
 */

if ( class_exists( 'WP_Widget' ) ) {

	/**
	 * Class for Booking & Appointment Availability Search functionality
	 *
	 * Custom_WooCommerce_Widget_Product_Search Class
	 */
	class Custom_WooCommerce_Widget_Product_Search extends WP_Widget {

		/**
		 * Default constructor.
		 *
		 * @since 1.7
		 */
		public function __construct() {

			/* Widget variable settings. */
			$this->woo_widget_cssclass    = 'Custom_widget_product_search';
			$this->woo_widget_description = __( 'Allows customers to search all the products based on checkin & checkout dates.', 'woocommerce-booking' );
			$this->woo_widget_idbase      = 'woocommerce_booking_availability_search';
			$this->woo_widget_name        = __( 'Booking & Appointment Availability Search', 'woocommerce-booking' );

			/* Widget settings. */
			$widget_ops = array(
				'classname'   => $this->woo_widget_cssclass,
				'description' => $this->woo_widget_description,
			);

			/* Create the widget. */
			parent::__construct( 'custom_product_search', $this->woo_widget_name, $widget_ops );
		}

		/**
		 * This function display the widget on the front end.
		 *
		 * @see WP_Widget
		 * @access public
		 * @since 1.7
		 * @param array $args Widget arguments.
		 * @param array $instance The settings for the particular instance of the widget.
		 */
		public function widget( $args, $instance ) {

			if ( ! is_array( $args ) ) {
				$args = array(
					'before_widget' => '',
					'before_title'  => '',
					'after_title'   => '',
					'after_widget'  => '',
				);
			}
			extract( $args );

			$title = ! empty( $instance['title_label'] ) ? __( $instance['title_label'], 'woocommerce-booking' ) : __( 'Check Availability', 'woocommerce-booking' );
			$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

			echo $before_widget;

			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			$this->bkap_search_widget_form( $instance, 'widget' );

			echo $after_widget;
		}

		/**
		 * This function display the widget on the front end.
		 *
		 * @see WP_Widget
		 * @access public
		 * @since 1.7
		 * @param array $instance List of information needed to be displayed correct widget form.
		 * @param array $type Type could be widget or shortcode.
		 *
		 * @param array $instance The settings for the particular instance of the widget.
		 */
		public static function bkap_search_widget_form( $instance, $type ) {

			$start_date       = ! empty( $instance['start_date_label'] ) ? __( $instance['start_date_label'], 'woocommerce-booking' ) : __( 'Start Date', 'woocommerce-booking' );
			$end_date         = ! empty( $instance['end_date_label'] ) ? __( $instance['end_date_label'], 'woocommerce-booking' ) : __( 'End Date', 'woocommerce-booking' );
			$search_label     = ! empty( $instance['search_label'] ) ? __( $instance['search_label'], 'woocommerce-booking' ) : __( 'Search', 'woocommerce-booking' );
			$clear_label      = ! empty( $instance['clear_label'] ) ? __( $instance['clear_label'], 'woocommerce-booking' ) : __( 'Clear Filter', 'woocommerce-booking' );
			$category_label   = ! empty( $instance['category_title'] ) ? __( $instance['category_title'], 'woocommerce-booking' ) : __( 'Category', 'woocommerce-booking' );
			$resource_label   = ! empty( $instance['resource_title'] ) ? __( $instance['resource_title'], 'woocommerce-booking' ) : __( 'Resource', 'woocommerce-booking' );
			$text_label       = ! empty( $instance['text_label'] ) ? trim( __( $instance['text_label'], 'woocommerce-booking' ) ) : '';

			$clear_button_css = $clear_label !== '' ? 'inline' : 'none';
			$clear_filter     = 'bkap_clear_filter()';

			$text_information = '';
			if ( isset( $text_label ) && $text_label != '' ) {
				$text_information = "<div><div class='Cell'>
									<div style= 'text-align: center;'>$text_label</div>
									</div>";
			}

			if ( isset( $instance['enable_day_search_label'] ) && $instance['enable_day_search_label'] == 'on' ) {
				$allow_single_day_search = __( $instance['enable_day_search_label'], 'woocommerce-booking' );
				$hide_checkout_field     = 'none';
			} else {
				$allow_single_day_search = '';
				$hide_checkout_field     = 'table-row';
			}

			if ( isset( $instance['category'] ) && $instance['category'] == 'on' ) {
				$allow_category_search = 'on';
				$hide_category_filter  = 'table-row';
				$child_category = false;
				if ( isset( $instance['child-category'] ) && $instance['child-category'] == 'on' ) {
					$child_category = true;
				}
			} else {
				$allow_category_search = '';
				$hide_category_filter  = 'none';
			}

			if ( isset( $instance['resource'] ) && $instance['resource'] == 'on' ) {
				$allow_resource_search = 'on';
				$hide_resource_filter  = 'table-row';
			} else {
				$allow_resource_search = '';
				$hide_resource_filter  = 'none';
			}

			$url            = plugins_url();
			$shop           = get_permalink( wc_get_page_id( 'shop' ) );
			$shop           = apply_filters( 'bkap_change_search_result_page', $shop );
			$add_anchor_tag = '';
			$add_anchor_tag = apply_filters( 'bkap_add_search_widget_id', $add_anchor_tag );
			$action         = $shop;

			if ( $add_anchor_tag != '' ) {
				$action .= '#' . $add_anchor_tag;
			}

			$g_setting = bkap_global_setting();

			$calendar_theme_sel = $g_setting->booking_themes;
			$booking_language   = bkap_icl_lang_code( $g_setting->booking_language );
			$date_format        = $g_setting->booking_date_format;
			$firstDay           = $g_setting->booking_calendar_day;

			$bkap_language = '';
			if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				if ( isset( $_GET['lang'] ) && $_GET['lang'] != '' ) {
					$bkap_language = '<input type="hidden" name="lang" id="lang" value="' . $booking_language . '"/>';
				}
			}

			// Ensure that the start and end date are retained in the widget
			$start_date_value = '';
			$end_date_value   = '';
			$category_value   = '';
			$resource_value   = '';
			$date_formats     = bkap_get_book_arrays( 'bkap_date_formats' );
			$php_date_format  = $date_formats[ $date_format ];

			$session_start_date = bkap_common::bkap_date_from_session_cookie( 'start_date' );
			$session_end_date   = bkap_common::bkap_date_from_session_cookie( 'end_date' );

			if ( $session_start_date ) {
				$start_date_value = date( $php_date_format, strtotime( $session_start_date ) );
			}
			if ( $session_end_date ) {
				$end_date_value = date( $php_date_format, strtotime( $session_end_date ) );
			}

			$contents = '';
			// category drop down.
			if ( WC()->session && WC()->session->get( 'selected_category' ) ) {
				$category_value = WC()->session->get( 'selected_category' );
			}

			if ( 'on' == $allow_category_search || ( 'disable' !== $category_value && '' !== $category_value ) ) {
				$contents = self::bkap_widget_category_content( $category_value, $child_category );
			}

			/* Resource Content */
			$resource_contents = '';
			// category drop down.
			if ( WC()->session && WC()->session->get( 'selected_resource' ) ) {
				$resource_value = WC()->session->get( 'selected_resource' );
			}

			if ( 'on' == $allow_resource_search || ( 'disable' !== $resource_value && '' !== $resource_value ) ) {
				$resource_contents = self::bkap_widget_resource_content( $resource_value );
			}

			$admin_url    = AJAX_URL;
			$bkap_version = BKAP_VERSION;

			wp_enqueue_style(
				'jquery-ui',
				bkap_load_scripts_class::bkap_asset_url( "/assets/css/themes/$calendar_theme_sel/jquery-ui.css", BKAP_FILE, true, false ),
				'',
				$bkap_version,
				false
			);

			wp_enqueue_script( 'jquery-ui' );

			wp_enqueue_style(
				'bkap-booking',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/booking.css', BKAP_FILE ),
				'',
				$bkap_version,
				false
			);

			wp_register_script(
				'jquery-ui-datepicker2',
				bkap_load_scripts_class::bkap_asset_url( "/assets/js/i18n/jquery.ui.datepicker-$booking_language.js", BKAP_FILE, true )
			);

			wp_enqueue_script( 'jquery-ui-datepicker2' );
			wp_enqueue_script( 'jquery-ui-datepicker' );

			wp_register_script(
				'bkap-search-widget',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-search-widget.js', BKAP_FILE )
			);

			wp_localize_script(
				'bkap-search-widget',
				'bkap_search_widget_params',
				array(
					'allow_single_day_search' => $allow_single_day_search,
					'ajax_url'                => $admin_url,
					'bkap_global_setting'     => $g_setting,
					'bkap_language'           => $booking_language,
					'shop_url'                => $shop,
				)
			);

			wp_enqueue_script( 'bkap-search-widget' );

			$data = array(
				'allow_category_search' => $allow_category_search,
				'allow_resource_search' => $allow_resource_search,
				'bkap_language'         => $bkap_language,
				'start_date'            => $start_date,
				'start_date_value'      => $start_date_value,
				'hide_checkout_field'   => $hide_checkout_field,
				'end_date'              => $end_date,
				'end_date_value'        => $end_date_value,
				'hide_category_filter'  => $hide_category_filter,
				'category_label'        => $category_label,
				'contents'              => $contents,
				'hide_resource_filter'  => $hide_resource_filter,
				'resource_label'        => $resource_label,
				'resource_contents'     => $resource_contents,
				'search_label'          => $search_label,
				'clear_label'           => $clear_label,
				'clear_button_css'      => $clear_button_css,
				'text_information'      => $text_information,
				'action'                => $action,
				'session_start_date'    => $session_start_date,
				'session_end_date'      => $session_end_date,
			);

			if ( 'widget' == $type ) {
				/**
				 * Adding Search Widget Form Template on the front end of the website
				 */
				wc_get_template(
					'/search-widget-form.php',
					$data,
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);
			} else {

				// Shortcode.
				ob_start();
				wc_get_template(
					'/search-widget-form.php',
					$data,
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			}
		}

		/**
		 * This function will return the html content to be displayed in the search widget.
		 *
		 * @param string $category_value Selected category value.
		 *
		 * @return string $contents HTML of category dropdown.
		 */
		public static function bkap_widget_category_content( $category_value, $child_category ) {

			$parent_cat_ids = get_terms( 'product_cat', array(
				'parent'     => 0,
				'hide_empty' => false,
				'fields'     => 'ids'
			) );

			$args = array(
				'taxonomy'     => 'product_cat',
				'orderby'      => 'name',
				'show_count'   => 0,
				'pad_counts'   => 0,
				'hierarchical' => 1,
				'title_li'     => '',
				'hide_empty'   => 0,
			);

			if ( $child_category ) {
				$args['exclude'] = $parent_cat_ids;
			}

			$all_categories = get_categories( $args );

			foreach ( $all_categories as $cat ) {
				if ( $cat->category_parent == 0 || apply_filters( 'bkap_show_all_categories_in_widget', true, $child_category ) ) {
					$category_id              = $cat->term_id;
					$category[ $category_id ] = $cat->name;
				}
			}

			$category = apply_filters( 'bkap_all_categories_in_widget', $category );

			$contents = '';
			if ( isset( $category ) ) {
				$default_category = __( 'All categories', 'woocommerce-booking' );
				$contents         = "<select id='cat' name='select_cat'><option value = '0'>" . $default_category . '</option>';
				foreach ( $category as $c_key => $c_value ) {

					$selected = '';
					if ( $c_key == $category_value ) {
						$selected = 'selected';
					}
					$contents .= '<option value=' . $c_key . ' ' . $selected . ' >' . $c_value . '</option>';
				}
				$contents .= '</select>';
				$contents .= "<input type='hidden' id='w_category' name='w_category' value = " . $category_value . ' >';
			}

			return $contents;
		}

		public static function bkap_widget_resource_content( $resource_value ) {
			$all_resources = Class_Bkap_Product_Resource::bkap_get_all_resources();

			foreach ( $all_resources as $res ) {
				$resources[ $res->ID ] = $res->post_title;
			}

			$resource_contents = '';
			if ( isset( $resources ) ) {
				$default_resource = __( 'Select Resource', 'woocommerce-booking' );
				$resource_contents         = "<select id='res' name='select_res'><option value = '0'>" . $default_resource . '</option>';
				foreach ( $resources as $r_key => $r_value ) {

					$selected = '';
					if ( $r_key == $resource_value ) {
						$selected = 'selected';
					}
					$resource_contents .= '<option value=' . $r_key . ' ' . $selected . ' >' . $r_value . '</option>';
				}
				$resource_contents .= '</select>';
				$resource_contents .= "<input type='hidden' id='w_resource' name='w_resource' value = " . $resource_value . ' >';
			}

			return $resource_contents;
		}

		/**
		 * This function will chnage the value when save buutton click on admin widgets page..
		 *
		 * @see WP_Widget->update
		 * @access public
		 * @since 1.7
		 * @param array $new_instance New settings for this instance as input by the user via
		 * @param array $old_instance Old settings for this instance.
		 *
		 * @return array $instance New data for the fields in the backend Widget form
		 */
		public function update( $new_instance, $old_instance ) {

			$instance['start_date_label'] = wp_strip_all_tags( stripslashes( $new_instance['start_date_label'] ) );
			$instance['end_date_label']   = wp_strip_all_tags( stripslashes( $new_instance['end_date_label'] ) );
			$instance['search_label']     = wp_strip_all_tags( stripslashes( $new_instance['search_label'] ) );
			$instance['clear_label']      = wp_strip_all_tags( stripslashes( $new_instance['clear_label'] ) );
			$instance['text_label']       = stripslashes( $new_instance['text_label'] );
			$instance['title_label']      = stripslashes( $new_instance['title_label'] );
			$instance['category_title']   = wp_strip_all_tags( stripslashes( $new_instance['category_title'] ) );
			$instance['resource_title']   = wp_strip_all_tags( stripslashes( $new_instance['resource_title'] ) );

			if ( isset( $new_instance['category'] ) ) {
				$instance['category'] = strip_tags( stripslashes( $new_instance['category'] ) );
			}

			if ( isset( $new_instance['child-category'] ) ) {
				$instance['child-category'] = strip_tags( stripslashes( $new_instance['child-category'] ) );
			}

			if ( isset( $new_instance['resource'] ) ) {
				$instance['resource'] = strip_tags( stripslashes( $new_instance['resource'] ) );
			}

			$instance['enable_day_search_label'] = '';
			if ( isset( $new_instance['enable_day_search_label'] ) ) {
				$instance['enable_day_search_label'] = stripslashes( $new_instance['enable_day_search_label'] );
			}
			return $instance;
		}

		/**
		 * This function display the setting field on the admin side.
		 *
		 * @see WP_Widget->form
		 * @access public
		 * @since 1.7
		 * @param array $instance Current settings.
		 */
		public function form( $instance ) {
			global $wpdb;

			$title_label_id = esc_attr( $this->get_field_id( 'title_label' ) );
			$title_label    = '';
			if ( isset( $instance['title_label'] ) ) {
				$title_label = esc_attr( $instance['title_label'] );
			}

			$start_date_label_id = esc_attr( $this->get_field_id( 'start_date_label' ) );
			$start_date_label    = '';
			if ( isset( $instance['start_date_label'] ) ) {
				$start_date_label = esc_attr( $instance['start_date_label'] );
			}

			$end_date_label_id = esc_attr( $this->get_field_id( 'end_date_label' ) );
			$end_date_label    = '';
			if ( isset( $instance['end_date_label'] ) ) {
				$end_date_label = esc_attr( $instance['end_date_label'] );
			}

			$category_id = esc_attr( $this->get_field_id( 'category' ) );
			$child_category_id = esc_attr( $this->get_field_id( 'child-category' ) );

			$search_category = '';
			if ( isset( $instance['category'] ) && $instance['category'] == 'on' ) {
				$search_category = 'checked';
			}

			$child_category = '';
			if ( isset( $instance['child-category'] ) && $instance['child-category'] == 'on' ) {
				$child_category = 'checked';
			}

			$category_title_id = esc_attr( $this->get_field_id( 'category_title' ) );
			$category_title    = '';
			if ( isset( $instance['category_title'] ) ) {
				$category_title = esc_attr( $instance['category_title'] );
			}

			$resource_id = esc_attr( $this->get_field_id( 'resource' ) );

			$search_resource = '';
			if ( isset( $instance['resource'] ) && $instance['resource'] == 'on' ) {
				$search_resource = 'checked';
			}

			/* Getting Resource Data */

			$resource_title_id = esc_attr( $this->get_field_id( 'resource_title' ) );
			$resource_title    = '';
			if ( isset( $instance['resource_title'] ) ) {
				$resource_title = esc_attr( $instance['resource_title'] );
			}

			$search_label_id = esc_attr( $this->get_field_id( 'search_label' ) );
			$search_label    = '';
			if ( isset( $instance['search_label'] ) ) {
				$search_label = esc_attr( $instance['search_label'] );
			}

			$clear_label_id = esc_attr( $this->get_field_id( 'clear_label' ) );
			$clear_label    = '';
			if ( isset( $instance['clear_label'] ) ) {
				$clear_label = esc_attr( $instance['clear_label'] );
			}

			$text_label_id = esc_attr( $this->get_field_id( 'text_label' ) );
			$text_label    = '';
			if ( isset( $instance['text_label'] ) ) {
				$text_label = esc_attr( $instance['text_label'] );
			}

			$enable_single_day_search = '';
			if ( isset( $instance['enable_day_search_label'] ) && $instance['enable_day_search_label'] == 'on' ) {
				$enable_single_day_search = 'checked';
			}

			$enable_day_search_label_id = esc_attr( $this->get_field_id( 'enable_day_search_label' ) );

			?>
				<!-- Widget Title -->
				<p>
					<label for="<?php echo $this->get_field_id( 'title_label' ); ?>">
						<?php _e( 'Title', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $title_label_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'title_label' ) ); ?>"
							value="<?php echo $title_label; ?>" />
				</p>

				<!-- Start Date Label -->
				<p>
					<label for="<?php echo $start_date_label_id; ?>">
						<?php _e( 'Start Date Label', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $start_date_label_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'start_date_label' ) ); ?>"
							value="<?php echo $start_date_label; ?>" />
				</p>

				<!-- End Date Label -->
				<p>
					<label for="<?php echo $end_date_label_id; ?>">
						<?php _e( 'End Date Label', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $end_date_label_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'end_date_label' ) ); ?>"
							value="<?php echo $end_date_label; ?>" />
				</p>

				<!-- Field to display end date on front end widget -->

				<p>
					<label for="<?php echo $enable_day_search_label_id; ?>">
						<?php _e( 'Hide End date field', 'woocommerce-booking' ); ?>
					</label>
				<input 	class="checkbox"
						type="checkbox" <?php echo $enable_single_day_search; ?>
						id="<?php echo $enable_day_search_label_id; ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'enable_day_search_label' ) ); ?>" />
				</p>

				<!-- Category option -->
				<p>
					<label for="<?php echo $category_id; ?>">
						<?php _e( 'Search By Category', 'woocommerce-booking' ); ?>
					</label>
					<input 	class="checkbox"
							type="checkbox" <?php echo $search_category; ?>
							id="<?php echo $category_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'category' ) ); ?>" />
				</p>

				<p>
					<label for="<?php echo $child_category_id; ?>">
						<?php _e( 'Show only Child Categories', 'woocommerce-booking' ); ?>
					</label>
					<input 	class="checkbox"
							type="checkbox" <?php echo $child_category; ?>
							id="<?php echo $child_category_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'child-category' ) ); ?>" />
				</p>

				<!-- Category dropdown title -->
				<p>
					<label for="<?php echo $category_title_id; ?>">
						<?php _e( 'Category Title', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $category_title_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'category_title' ) ); ?>"
							value="<?php echo $category_title; ?>" />
				</p>

				<!-- Resource option -->
				<p>
					<label for="<?php echo $resource_id; ?>">
						<?php _e( 'Search By Resource', 'woocommerce-booking' ); ?>
					</label>
					<input 	class="checkbox"
							type="checkbox" <?php echo $search_resource; ?>
							id="<?php echo $resource_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'resource' ) ); ?>" />
				</p>

				<!-- Resource dropdown title -->
				<p>
					<label for="<?php echo $resource_title_id; ?>">
						<?php _e( 'Resource Title', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $resource_title_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'resource_title' ) ); ?>"
							value="<?php echo $resource_title; ?>" />
				</p>

				<!-- Search button field -->
				<p>
					<label for="<?php echo $search_label_id; ?>">
						<?php _e( 'Search Button', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $search_label_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'search_label' ) ); ?>"
							value="<?php echo $search_label; ?>" />
				</p>

				<!-- Clear button field -->
				<p>
					<label for="<?php echo $clear_label_id; ?>">
						<?php _e( 'Clear Button', 'woocommerce-booking' ); ?>
					</label>
					<input 	type="text"
							class="widefat"
							id="<?php echo $clear_label_id; ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'clear_label' ) ); ?>"
							value="<?php echo $clear_label; ?>" />
				</p>

				<!-- Textarea field to show text below buttons -->
				<p>
					<label for="<?php echo $text_label_id; ?>">
						<?php _e( 'Text (appears below Search button)', 'woocommerce-booking' ); ?>
					</label>
					<textarea 	class="widefat"
								id="<?php echo $text_label_id; ?>"
								name="<?php echo esc_attr( $this->get_field_name( 'text_label' ) ); ?>"><?php echo $text_label; ?></textarea>
				</p>
			<?php
		}

		/**
		 * This function clear the session when Clear button in Booking Wirdget is clicked.
		 *
		 * @since 4.3.0
		 */
		public static function clear_widget_dates() {

			// Removing from woocommerce session.
			WC()->session->__unset( 'start_date' );
			WC()->session->__unset( 'end_date' );
			WC()->session->__unset( 'selected_category' );
			WC()->session->__unset( 'selected_resource' );
			die();
		}
	}
}
?>
