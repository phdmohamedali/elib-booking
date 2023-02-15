<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Manual Bookings using Bookings->Create Booking
 *
 * @author   Tyche Softwares
 * @package  BKAP/Admin-Bookings
 * @category Classes
 */
if ( ! class_exists( 'bkap_admin_bookings' ) ) {

	/**
	 * Class for creating Manual Bookings using Bookings->Create Booking
	 *
	 * @class bkap_admin_bookings
	 */

	class bkap_admin_bookings {

		/**
		 * Stores errors.
		 *
		 * @var array
		 */

		private $errors = array();

		/**
		 * Default Constructor.
		 *
		 * @since 4.1.0
		 */

		public function __construct() {
			add_action( 'woocommerce_order_after_calculate_totals', array( &$this, 'woocommerce_order_after_calculate_totals_callback' ), 10, 2 );
			add_action( 'wp_loaded', array( $this, 'bkap_wp_loaded' ), 10 );
		}

		/**
		 * Create Booking upon Click of Create Booking Button.
		 * Function loaded on wp_loaded as the same is being used on front end.
		 * Creating Manual Booking fron end was throwing header already sent by message.
		 *
		 * @since 5.10.0
		 */
		public function bkap_wp_loaded() {

			try {
				if ( ! empty( $_POST['bkap_create_booking_2'] ) ) {

					$create_order = ( 'new' === $_POST['bkap_order'] ) ? true : false;

					// validate the booking data.
					$validations = true;
					$_product    = wc_get_product( $_POST['bkap_product_id'] );

					if ( $_product->post_type === 'product_variation' ) {
						$settings_id = $_product->get_parent_id();
					} else {
						$settings_id = $_POST['bkap_product_id'];
					}

					if ( $_POST['wapbk_hidden_date'] === '' ) {
						$validations = false;
					}

					$booking_type  = get_post_meta( $settings_id, '_bkap_booking_type', true );
					$bkap_settings = bkap_setting( $settings_id );

					switch ( $booking_type ) {
						case 'multiple_days':
							if ( $_POST['wapbk_hidden_date_checkout'] === '' ) {
								$validations = false;
							}
							break;
						case 'date_time':
							if ( $_POST['time_slot'] === '' ) {
								$validations = false;
							}
							break;
						case 'duration_time':
							if ( $_POST['duration_time_slot'] === '' ) {
								$validations = false;
							}
							break;
						case 'multidates':
						case 'multidates_fixedtime':
							$validations = true;
							if ( ! isset( $_POST['bkap_multidate_data'] ) && '' === $_POST['bkap_multidate_data'] ) {
								$validations = false;
							}
							break;
					}

					if ( ! $validations ) {
						throw new Exception( __( 'Please select the Booking Details.', 'woocommerce-booking' ) );
					}

					// setup the data.
					$time_slot = ( isset( $_POST['time_slot'] ) ) ? $_POST['time_slot'] : '';

					$duration_time_slot = ( isset( $_POST['duration_time_slot'] ) ) ? $_POST['duration_time_slot'] : '';

					$checkout_date = ( isset( $_POST['wapbk_hidden_date_checkout'] ) && '' !== $_POST['wapbk_hidden_date_checkout'] ) ? $_POST['wapbk_hidden_date_checkout'] : '';

					$booking_details['product_id']  = $_POST['bkap_product_id'];
					$booking_details['customer_id'] = $_POST['bkap_customer_id'];

					if ( $time_slot !== '' ) {
						if ( is_array( $time_slot ) ) {
							$times = explode( ' - ', $time_slot[0] ); // temporarily fetching only first timeslot to create manual order.
						} else {
							$times = explode( ' - ', $time_slot );
						}
						$start_time = ( isset( $times[0] ) && '' !== $times[0] ) ? date( 'H:i', strtotime( $times[0] ) ) : '00:00';
						$end_time   = ( isset( $times[1] ) && '' !== $times[1] ) ? date( 'H:i', strtotime( $times[1] ) ) : '00:00';

						$booking_details['start'] = strtotime( $_POST['wapbk_hidden_date'] . $start_time );
						$booking_details['end']   = strtotime( $_POST['wapbk_hidden_date'] . $end_time );

					} elseif ( $checkout_date !== '' ) {
						$booking_details['start'] = strtotime( $_POST['wapbk_hidden_date'] );
						$booking_details['end']   = strtotime( $checkout_date );
					} elseif ( $duration_time_slot !== '' ) {

						$d_setting = get_post_meta( $settings_id, '_bkap_duration_settings', true );

						$start_date               = $_POST['wapbk_hidden_date']; // hiddendate.
						$booking_details['start'] = strtotime( $start_date . ' ' . $duration_time_slot ); // creating start date based on date and time.

						$selected_duration = $_POST['bkap_duration_field']; // selected duration.
						$duration          = $d_setting['duration']; // Numbers of hours set for product.

						$hour   = $selected_duration * $duration; // calculating numbers of duration by customer.
						$d_type = $d_setting['duration_type']; // hour/min.

						$booking_details['end'] = bkap_common::bkap_add_hour_to_date( $start_date, $duration_time_slot, $hour, $settings_id, $d_type );

						$booking_details['duration'] = $hour . '-' . $d_type;

					} else {
						$booking_details['start'] = strtotime( $_POST['wapbk_hidden_date'] );
						$booking_details['end']   = strtotime( $_POST['wapbk_hidden_date'] );
					}

					if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
						$product                       = wc_get_product( $settings_id );
						$product_price                 = wc_get_price_excluding_tax(
							$product,
							array( 'price' => $_POST['bkap_price_charged'] )
						);
						$booking_details['price']      = $product_price;
						$booking_details['price_incl'] = $_POST['bkap_price_charged'];
					} else {
						$booking_details['price'] = $_POST['bkap_price_charged'];
					}

					if ( isset( $_POST['bkap_front_resource_selection'] ) && $_POST['bkap_front_resource_selection'] != '' ) {
						$booking_details['bkap_resource_id'] = $_POST['bkap_front_resource_selection'];
					}

					/* Persons Calculations */
					if ( isset( $bkap_settings['bkap_person'] ) && 'on' === $bkap_settings['bkap_person'] ) {
						if ( isset( $_POST[ 'bkap_field_persons' ] ) ) {
							$total_person              = (int) $_POST[ 'bkap_field_persons' ];
							$booking_details['persons'] = array( $total_person );
						} else {
							$person_data      = $bkap_settings['bkap_person_data'];
							$person_post_data = array();
							foreach ( $person_data as $p_id => $p_data ) {
								$p_key = 'bkap_field_persons_' . $p_id;
								if ( isset( $_POST[ $p_key ] ) && '' !== $_POST[ $p_key ] ) {
									$person_post_data[ $p_id ] = (int) $_POST[ $p_key ];
								}
							}
							$booking_details['persons'] = $person_post_data;
						}
					}

					if ( isset( $_POST['block_option'] ) && $_POST['block_option'] != '' ) {
						$booking_details['fixed_block'] = $_POST['block_option'];
					}

					if ( isset( $_POST['quantity'] ) && $_POST['quantity'] != '' ) {
						$booking_details['quantity'] = $_POST['quantity'];
					}

					if ( isset( $_POST['bkap_multidate_data'] ) && '' != $_POST['bkap_multidate_data'] ) {

						$booking_details['has_multidates'] = true;

						$posted_multidate_data = $_POST['bkap_multidate_data'];
						$temp_data             = str_replace( '\\', '', $posted_multidate_data );
						$bkap_multidate_data   = (array) json_decode( $temp_data );

						foreach ( $bkap_multidate_data as $value ) {

							$booking                = array();
							$booking['date']        = $value->date;
							$booking['hidden_date'] = $value->hidden_date;

							if ( isset( $_POST['block_option'] ) && '' !== $_POST['block_option'] ) {
								$booking['fixed_block'] = $_POST['block_option'];
							}

							if ( isset( $_POST['booking_calender_checkout'] ) ) {
								$booking['date_checkout'] = $_POST['booking_calender_checkout'];
							}

							if ( isset( $_POST['wapbk_hidden_date_checkout'] ) ) {
								$booking['hidden_date_checkout'] = $_POST['wapbk_hidden_date_checkout'];
							}

							if ( isset( $value->time_slot ) ) {
								$booking['time_slot'] = $value->time_slot;
							}

							$booking['price_charged']                = $value->price_charged;
							$booking_details['multidates_booking'][] = $booking;
						}
					}

					$booking_details = apply_filters( 'bkap_detail_before_creating_manual_order', $booking_details );

					if ( 'new' === $_POST['bkap_order'] ) {
						// create a new order.
						$status = import_bookings::bkap_create_order( $booking_details, false );
						// get the new order ID.
						$order_id = ( absint( $status['order_id'] ) > 0 ) ? $status['order_id'] : 0;

						do_action( 'bkap_manual_booking_created_with_new_order', $order_id );

					} else {
						$order_id = ( isset( $_POST['bkap_order_id'] ) ) ? $_POST['bkap_order_id'] : 0;

						if ( $order_id > 0 ) {
							$booking_details['order_id'] = $order_id;
							$status                      = import_bookings::bkap_create_booking( $booking_details, false );
						}
					}

					$redirect_url = bkap_order_url( $order_id );
					$redirect_url = apply_filters( 'bkap_after_successful_manual_booking', $redirect_url, $order_id );

					if ( isset( $status['new_order'] ) && $status['new_order'] ) {
						// redirect to the order.
						wp_safe_redirect( $redirect_url );
						exit;
					} elseif ( isset( $status['item_added'] ) && $status['item_added'] ) {
						// redirect to the order.
						wp_safe_redirect( $redirect_url );
						exit;
					} else {
						if ( 1 == $status['backdated_event'] ) {
							throw new Exception( __( 'Back Dated bookings cannot be created. Please select a future date.', 'woocommerce-booking' ) );
						}

						if ( 1 == $status['validation_check'] ) {
							throw new Exception( __( 'The product is not available for the given date for the desired quantity.', 'woocommerce-booking' ) );
						}

						if ( 1 == $status['grouped_product'] ) {
							throw new Exception( __( 'Bookings cannot be created for grouped products.', 'woocommerce-booking' ) );
						}
					}
				}
			} catch ( Exception $e ) {
				$bkap_admin_bookings           = new bkap_admin_bookings();
				$bkap_admin_bookings->errors[] = $e->getMessage();
			}
		}

		/**
		 * Updating the price in the booking when discount is appied from Edit Order page.
		 *
		 * @param bool   $and_taxes true if calculation for taxes else false.
		 * @param Object $order Shop Order post.
		 * @since 4.9.0
		 *
		 * @hook woocommerce_order_after_calculate_totals
		 */
		public function woocommerce_order_after_calculate_totals_callback( $and_taxes, $order ) {

			$item_values = $order->get_items();

			foreach ( $item_values as $cart_item_key => $values ) {

				$product_id = $values['product_id'];
				$bookable   = bkap_common::bkap_get_bookable_status( $product_id );

				if ( ! $bookable ) {
					continue;
				}

				$booking_id    = bkap_common::get_booking_id( $cart_item_key );
				$item_quantity = $values->get_quantity(); // Get the item quantity.
				$item_total    = number_format( ( float ) $values->get_total(), wc_get_price_decimals(), '.', '' );
				$item_tax      = number_format( ( float ) $values->get_total_tax(), wc_get_price_decimals(), '.', '' );
				$item_total    = $item_total + $item_tax;
				$item_total    = $item_total / $item_quantity;

				// update booking post meta.
				update_post_meta( $booking_id, '_bkap_cost', $item_total );
			}
		}

		/**
		 * Loads the Create Booking Pages or saves the booking based on
		 * the data passed in $_POST
		 *
		 * @since 4.1.0
		 */

		public static function bkap_create_booking_page() {

			bkap_include_select2_scripts();

			$bookable_product_id = 0;
			$bkap_admin_bookings = new bkap_admin_bookings();

			$step = 1;

			try {
				if ( ! empty( $_POST['bkap_create_booking'] ) ) {

					$customer_id         = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
					$bookable_product_id = absint( $_POST['bkap_product_id'] );
					$booking_order       = wc_clean( $_POST['bkap_order'] );

					if ( ! $bookable_product_id ) {
						throw new Exception( __( 'Please choose a bookable product', 'woocommerce-booking' ) );
					}

					if ( 'existing' === $booking_order ) {
						$order_id      = absint( $_POST['bkap_order_id'] );
						$booking_order = $order_id;
						if ( ! wc_get_order( $order_id ) ) {
							throw new Exception( __( 'Invalid order ID provided', 'woocommerce-booking' ) );
						}
					}

					$bkap_data['customer_id'] = $customer_id;
					$bkap_data['product_id']  = $bookable_product_id;
					$bkap_data['order_id']    = $booking_order;
					$bkap_data['bkap_order']  = $_POST['bkap_order'];
					$step++;
				}
			} catch ( Exception $e ) {
				$bkap_admin_bookings           = new bkap_admin_bookings();
				$bkap_admin_bookings->errors[] = $e->getMessage();
			}

			switch ( $step ) {
				case '1':
					$bkap_admin_bookings->create_bookings_1();
					break;
				case '2':
					$bkap_admin_bookings->create_bookings_2( $bkap_data );
					break;
				default:
					$bkap_admin_bookings->create_bookings_1();
					break;
			}
		}

		/**
		 * Output any warnings/errors that occur when creating a manual booking.
		 *
		 * @since 4.1.0
		 */

		public function show_errors() {
			foreach ( $this->errors as $error ) {
				echo '<div class="error bkap-error"><p>' . esc_html( $error ) . '</p></div>';
			}
		}

		/**
		 * Display the first page for manual bookings
		 *
		 * @since 4.1.0
		 * @todo Change to function name as per its functionality
		 */
		public function create_bookings_1() {
			$this->show_errors();

			$bkap_customers = array();
			$args           = apply_filters( 'bkap_create_booking_page_users_dropdown_args', array( 'fields' => array( 'id', 'display_name', 'user_email' ), 'orderby' => 'display_name', 'order' => 'ASC' ) );
			$wp_users       = get_users( $args );

			foreach ( $wp_users as $users ) {
				$customer_id                    = $users->id;
				$user_email                     = $users->user_email;
				$user_name                      = $users->display_name;
				$bkap_customers[ $customer_id ] = "$user_name (#$customer_id - $user_email )";
			}

			$product_status             = array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' );
			$php_version                = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );
			$bkap_all_bookable_products = bkap_common::get_woocommerce_product_list( true, 'on', '', $product_status );
			$bkap_admin_bookings        = new bkap_admin_bookings();

			/* Create Booking Main Page Template */
			wc_get_template(
				'create-booking/bkap-create-booking-form.php',
				array(
					'bkap_admin_bookings'        => $bkap_admin_bookings,
					'bkap_customers'             => $bkap_customers,
					'bkap_all_bookable_products' => $bkap_all_bookable_products,
					'php_version'                => $php_version,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Display the second page for manual bookings.
		 *
		 * @since 4.1.0
		 * @todo Change to function name as per its functionality
		 */
		public function create_bookings_2( $booking_data ) {

			$this->show_errors();
			// check if the passed product ID is a variation ID.
			$_product     = wc_get_product( $booking_data['product_id'] );
			$variation_id = 0;
			$parent_id    = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->parent->id : $_product->get_parent_id();
			$product_id   = $booking_data['product_id'];
			$duplicate_id = ( $parent_id > 0 ) ? $parent_id : $product_id;
			$duplicate_id = bkap_common::bkap_get_product_id( $duplicate_id );

			/* Create Booking Details Selection Template */
			wc_get_template(
				'create-booking/bkap-booking-selection-form.php',
				array(
					'product_id'   => $product_id,
					'duplicate_id' => $duplicate_id,
					'_product'     => $_product,
					'booking_data' => $booking_data,
					'parent_id'    => $parent_id,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}
	} // end of class.

	$bkap_admin_bookings = new bkap_admin_bookings();
}
