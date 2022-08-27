<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Reuires Confirmation feature
 *
 * @author   Tyche Softwares
 * @package  BKAP/Booking-Confirmation
 * @category Classes
 */

if ( ! class_exists( 'bkap_booking_confirmation' ) ) {

	/**
	 * Class for handling Reuires Confirmation feature
	 *
	 * @class bkap_booking_confirmation
	 */
	class bkap_booking_confirmation {

		/**
		 * Default constructor
		 *
		 * @since 2.5
		 */
		public function __construct() {

			// add checkbox in admin.
			add_action( 'bkap_after_purchase_wo_date', array( &$this, 'confirmation_checkbox' ), 10, 2 );

			// Checkbox for Cancelling Bookings.
			add_action( 'bkap_after_purchase_wo_date', array( &$this, 'bkap_can_be_cancelled_checkbox' ), 10, 2 );

			// change the button text product page.
			add_filter( 'woocommerce_product_single_add_to_cart_text', array( &$this, 'change_button_text' ), 10, 1 );
			// change the button text on Shop page.
			add_filter( 'woocommerce_product_add_to_cart_text', array( &$this, 'change_button_text' ), 10, 1 );
			// Check if Cart contains any product that requires confirmation.
			add_filter( 'woocommerce_cart_needs_payment', array( &$this, 'bkap_cart_requires_confirmation' ), 10, 2 );
			// change the payment gateway at Checkout.
			add_filter( 'woocommerce_available_payment_gateways', array( &$this, 'bkap_remove_payment_methods' ), 10, 1 );
			// Prevent pending being cancelled.
			add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'bkap_prevent_cancel' ), 10, 2 );
			// Control the my orders actions.
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'bkap_my_orders_actions' ), 10, 2 );
			// Add the View Bookings link in Woo->Orders edit orders page.
			add_action( 'woocommerce_admin_order_item_headers', array( $this, 'bkap_link_header' ) );
			add_action( 'woocommerce_admin_order_item_values', array( $this, 'bkap_link' ), 10, 3 );
			// Cart Validations.
			add_filter( 'bkap_validate_cart_products', array( &$this, 'bkap_validate_conflicting_products' ), 10, 2 );
		}

		/**
		 * Add a Requires Confirmation checkbox in the Booking meta box
		 *
		 * @param int $product_id - Product ID.
		 *
		 * @hook bkap_after_purchase_wo_date
		 * @since 2.5
		 */
		public function confirmation_checkbox( $product_id, $booking_settings ) {

			?>
			<div id="requires_confirmation_section" class="booking_options-flex-main">
				<div class="booking_options-flex-child">
					<label for="bkap_requires_confirmation"><?php esc_html_e( 'Requires Confirmation?', 'woocommerce-booking' ); ?></label>
				</div>
				<?php
					$date_show = '';
				if ( isset( $booking_settings['booking_confirmation'] ) && 'on' == $booking_settings['booking_confirmation'] ) {
					$requires_confirmation = 'checked';
				} else {
					$requires_confirmation = '';
				}
				?>
				<div class="booking_options-flex-child">
					<label class="bkap_switch">
						<input type="checkbox" name="bkap_requires_confirmation" id="bkap_requires_confirmation" <?php echo $requires_confirmation; ?>>
						<div class="bkap_slider round"></div>
					</label>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable this setting if the booking requires admin approval/confirmation. Payment will not be taken at Checkout', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
				</div>

			</div>
			<?php
		}

		/**
		 * Modify the Add to cart button text for products that require confirmations
		 *
		 * @param string $var - Text for the button.
		 * @global object $post WP_Post
		 * @hook woocommerce_product_single_add_to_cart_text
		 * @since 2.5
		 */
		public function change_button_text( $var ) {

			if ( ( isset( $_GET['update-bundle'] ) && $_GET['update-bundle'] !== '' ) ||
				( isset( $_GET['update-composite'] ) && $_GET['update-composite'] !== '' ) ) {
					return $var;
			}

			global $post;

			if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
				return $var;
			}

			$product_id = $post->ID; // Product ID.

			// Return if the prpduct is not bookable.
			$bookable_status = bkap_common::bkap_get_bookable_status( $product_id );
			if ( ! $bookable_status ) {
				return $var;
			}

			$requires_confirmation = bkap_common::bkap_product_requires_confirmation( $product_id );

			if ( $requires_confirmation ) {
				$bkap_check_availability_text = get_option( 'bkap_check_availability' );

				if ( $bkap_check_availability_text == '' ) {
					return __( 'Check Availability', 'woocommerce-booking' );
				} else {
					return __( $bkap_check_availability_text, 'woocommerce-booking' ); // phpcs:ignore
				}
			} else {

				$bkap_add_to_cart_text = get_option( 'bkap_add_to_cart' );

				if ( $bkap_add_to_cart_text == '' ) {
					return $var;
				} else {
					return __( $bkap_add_to_cart_text, 'woocommerce-booking' ); // phpcs:ignore
				}
			}
		}

		/**
		 * Return true if the cart contains a product that requires confirmation.
		 * In this scenario no payment is taken at Checkout
		 *
		 * @param boolean $needs_payment Need Payment or not.
		 * @param array   $cart Cart Object.
		 * @return boolean
		 *
		 * @hook woocommerce_cart_needs_payment
		 * @since 2.5
		 */
		public function bkap_cart_requires_confirmation( $needs_payment, $cart ) {

			if ( ! $needs_payment ) {
				foreach ( $cart->cart_contents as $cart_item ) {
					$requires_confirmation = bkap_common::bkap_product_requires_confirmation( $cart_item['product_id'] );

					if ( $requires_confirmation ) {
						$needs_payment = true;
						break;
					}
				}
			}

			return $needs_payment;
		}

		/**
		 * Modify Payment Gateways
		 *
		 * Remove the existing payment gateways and add the Bookign payment gateway
		 * when the Cart contains a product that requires confirmation.
		 *
		 * @param array $available_gateways - Array containing all the Payment Gateways.
		 *
		 * @return array $available_gateways - Array containing the Payment Gateways.
		 *
		 * @hook woocommerce_available_payment_gateways
		 * @since 2.5
		 */
		public function bkap_remove_payment_methods( $available_gateways ) {

			$cart_requires_confirmation = bkap_common::bkap_cart_requires_confirmation();

			if ( $cart_requires_confirmation ) {
				unset( $available_gateways );

				$available_gateways                         = array();
				$available_gateways['bkap-booking-gateway'] = new BKAP_Payment_Gateway();
			}

			return $available_gateways;
		}

		/**
		 * Prevent Order Cancellation
		 *
		 * Prevent WooCommerce from cancelling an order if the order contains
		 * an item that is awaiting booking confirmation once Hold Stock limit is reached.
		 *
		 * @param boolean  $return Return true false
		 * @param WC_Order $order Order Object
		 * @return boolean $return
		 *
		 * @hook woocommerce_cancel_unpaid_order
		 * @since 2.5
		 */
		public function bkap_prevent_cancel( $return, $order ) {
			if ( '1' === get_post_meta( $order->get_id(), '_bkap_pending_confirmation', true ) ) {
				return false;
			}

			return $return;
		}

		/**
		 * Hide the Pay button in My Accounts
		 *
		 * Hide the Pay button in My Accounts for orders that contain
		 * an item that's still awaiting booking confirmation.
		 *
		 * @param array $actions - List of Actions for an order on My Account page.
		 * @param obj   $order Order Object.
		 * @global object $wpdb Global wpdb object
		 * @return array $actions - List of Actions for an order on My Account page
		 * @since 2.5
		 */
		public function bkap_my_orders_actions( $actions, $order ) {

			global $wpdb;

			$order_payment_method = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->payment_method : $order->get_payment_method();
			if ( $order->has_status( 'pending' ) && 'bkap-booking-gateway' === $order_payment_method ) {

				$status = array();
				foreach ( $order->get_items() as $order_item_id => $item ) {
					if ( 'line_item' == $item['type'] ) {

						$_status  = $item['wapbk_booking_status'];
						$status[] = $_status;

					}
				}

				if ( in_array( 'pending-confirmation', $status, true ) && isset( $actions['pay'] ) ) {
					unset( $actions['pay'] );
				} elseif ( in_array( 'cancelled', $status, true ) && isset( $actions['pay'] ) || count( $status ) == 0 ) {
					unset( $actions['pay'] );
				}
			}

			return $actions;
		}

		/**
		 * Create a column in WooCommerce->Orders->Edit Orders page
		 * for each item
		 *
		 * @hook woocommerce_admin_order_item_headers
		 * @since 2.5
		 */
		public function bkap_link_header() {
			?>
		<th class="bkap_edit_header">&nbsp;</th>
			<?php
		}

		/**
		 * Display View Booking Link
		 *
		 * Add the View Booking Link for a given item in
		 * WooCommerce->orders Edit Orders
		 *
		 * @param WC_Product      $_product - Product Details.
		 * @param WC_Product_Item $item - Item Details.
		 * @param int             $item_id - Item ID.
		 * @global object $wpdb Global wpdb object
		 *
		 * @hook woocommerce_admin_order_item_values
		 * @since 2.5
		 */
		public function bkap_link( $_product, $item, $item_id ) {

			global $wpdb;

			if ( isset( $_product ) && ! empty( $_product ) ) {
				$product_id       = $_product->get_id();
				$booking_settings = bkap_setting( $product_id );

				// order ID
				$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
                                        WHERE order_item_id = %d';
				$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_id ) );

				$order_id = 0;
				if ( isset( $get_order_id ) && is_array( $get_order_id ) && count( $get_order_id ) > 0 ) {
					$order_id = $get_order_id[0]->order_id;
				}

				// get booking posts for the order.
				$query_posts = 'SELECT ID FROM `' . $wpdb->prefix . 'posts`
                                    WHERE post_type = %s
                                    AND post_parent = %d';
				$get_posts   = $wpdb->get_results( $wpdb->prepare( $query_posts, 'bkap_booking', $order_id ) );

				$booking_post_ids = array();
				foreach ( $get_posts as $loop_post_id ) {

					$get_item_id = get_post_meta( $loop_post_id->ID, '_bkap_order_item_id', true );
					if ( $get_item_id == $item_id ) {
						$booking_post_ids[] = $loop_post_id->ID;
					}
				}

				if ( count( $booking_post_ids ) > 0 ) {
					$item_type = '';
					$_status   = '';
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
						$_status   = ( isset( $item['wapbk_booking_status'] ) ) ? $item['wapbk_booking_status'] : ''; // booking status
						$item_type = $item['type']; // line item type.
					} else {
						if ( $item ) {
							// booking status.
							$meta_data = $item->get_meta_data();
							foreach ( $meta_data as $m_key => $m_value ) {
								if ( isset( $m_value->key ) && '_wapbk_booking_status' == $m_value->key ) {
									$_status = $m_value->value;
									break;
								}
							}
							// line item type.
							$item_type = $item->get_type();
						}
					}

					if ( 'line_item' == $item_type && ( ( isset( $_status ) && '' != $_status ) || ( ! isset( $_status ) ) ) ) {

						?>
						<td class="bkap_edit_column">
							<div class="view">
								<table class = "display_meta" cellspacing="0">
									<tbody>
										<tr>
											<td><br></td>
										</tr>
										<?php
										foreach ( $booking_post_ids as $key => $booking_post_id ) {
											$args = array(
												'post'   => $booking_post_id,
												'action' => 'edit',
											);
											/* translators: %s: Booking ID. */
											$edit_booking_button = sprintf( __( 'Edit Booking #%s', 'woocommerce-booking' ), $booking_post_id );
											?>
											<tr>
												<td>
												<a class="button" style="margin: 2px" href="<?php echo esc_url_raw( add_query_arg( $args, admin_url() . 'post.php' ) ); ?>"><?php esc_html_e( $edit_booking_button ); ?></a><br>
												</td>
											</tr>
										<?php } ?>
										<?php do_action( 'bkap_woo_order_item_values', $_product, $item, $item_id ); ?>
									</tbody>
								</table>
							</div>
						</td>
						<?php
					}
				} else {
					echo '<td></td>';
				}
			} else {
				echo '<td></td>';
			}
		}

		/**
		 * Update Item status
		 *
		 * This function updates the item booking status.
		 * It is called from the Edit Booking page Save button click
		 *
		 * @param integer $item_id - Item ID.
		 * @param string  $_status - New Booking Status.
		 * @global object $wpdb Global wpdb object.
		 *
		 * @since 2.5
		 */
		public static function bkap_save_booking_status( $item_id, $_status, $booking_id = null ) {
			global $wpdb;

			wc_update_order_item_meta( $item_id, '_wapbk_booking_status', $_status );

			if ( null === $booking_id ) { // For recurring bookings future renewals.
				$booking_id = bkap_common::get_booking_id( $item_id ); // get the booking ID using the item ID.
			}

			if ( $booking_id ) { // update the booking post status.
				$new_booking = bkap_checkout::get_bkap_booking( $booking_id );
				$old_status  = $new_booking->get_status();

				if ( 'cancelled' === $_status ) {
					do_action( 'bkap_rental_delete', $new_booking, $booking_id );
				}

				$new_booking->update_status( $_status );
			} else {
				return;
			}

			// get the order ID.
			$order_id       = 0;
			$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
                                    WHERE order_item_id = %d';
			$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_id ) );

			if ( isset( $get_order_id ) && is_array( $get_order_id ) && count( $get_order_id ) > 0 ) {
				$order_id = $get_order_id[0]->order_id;
			}

			$order      = wc_get_order( $order_id ); // create order object.
			if ( ! $order ) {
				return;
			}

			$order_data = $order->get_items(); // order details.
			$item_value = isset( $order_data[ $item_id ] ) ? $order_data[ $item_id ] : false;

			if ( ! $item_value ) {
				return;
			}

			// update the booking history tables and GCal.
			self::update_booking_tables( $_status, $order_id, $item_id, $item_value, $order, $booking_id );

			// now check if the product is a bundled product.
			// if yes, then we need to update the booking status of all the child products.
			$bundled_items = wc_get_order_item_meta( $item_id, '_bundled_items' );

			if ( isset( $bundled_items ) && '' != $bundled_items ) {
				$bundle_cart_key = wc_get_order_item_meta( $item_id, '_bundle_cart_key' );
				foreach ( $order_data as $o_key => $o_value ) {
					$bundled_by = wc_get_order_item_meta( $o_key, '_bundled_by' );

					// check if it is a part of the bundle.
					if ( isset( $bundled_by ) && $bundled_by == $bundle_cart_key ) {
						// update the booking status.
						wc_update_order_item_meta( $o_key, '_wapbk_booking_status', $_status );
						// update the booking history tables and GCal.
						self::update_booking_tables( $_status, $order_id, $o_key, $o_value, $order, $booking_id );
					}
				}
			}

			$check_if_renewal_booking = get_post_meta( $booking_id, '_bkap_status', true );

			if ( ! $check_if_renewal_booking ) {
				if ( 'cancelled' == $_status ) {
					$wc_email = WC_Emails::instance();
					$email    = $wc_email->emails['BKAP_Email_Booking_Cancelled'];
					$email->trigger( $item_id );
					do_action( 'bkap_booking_is_cancelled', $booking_id );
				} elseif ( 'confirmed' == $_status ) {
					$wc_email = WC_Emails::instance();
					$email    = $wc_email->emails['BKAP_Email_Booking_Confirmed'];
					$email->trigger( $item_id );
					do_action( 'bkap_booking_is_confirmed', $booking_id );
				}

				if ( 'cancelled' === $old_status && in_array( $_status, array( 'paid', 'confirmed' ) ) ) {
					$details   = array();
					$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $item_value['product_id'] );
					$post_id   = bkap_common::bkap_get_product_id( $item_value['product_id'] );
					$quantity  = $item_value['qty'];
					$booking   = array(
						'date'                 => $item_value['_wapbk_booking_date'],
						'hidden_date'          => date( 'd-m-Y', strtotime( $item_value['_wapbk_booking_date'] ) ),
						'date_checkout'        => $item_value['wapbk_checkout_date'],
						'hidden_date_checkout' => date( 'd-m-Y', strtotime( $item_value['_wapbk_checkout_date'] ) ),
						'price'                => $item_value['cost'],
						'time_slot'            => $item_value['_wapbk_time_slot'],
					);

					$details = bkap_checkout::bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking );
					// update the global time slot lockout.
					if ( isset( $booking['time_slot'] ) && $booking['time_slot'] != '' ) {
						bkap_checkout::bkap_update_global_lockout( $post_id, $quantity, $details, $booking );
					}
				}
			}

			do_action( 'bkap_requires_confirmation_after_save_booking_status', $booking_id, $_status );
		}

		/**
		 * Update the plugin tables and GCal for booking status
		 * for each Item ID passed
		 *
		 * @param string          $_status - New Booking Status.
		 * @param integer         $order_id - Order ID.
		 * @param integer         $item_id - Item ID.
		 * @param WC_Product_Item $item_value - Item Details.
		 * @param WC_Order        $order - Order Details.
		 * @param integer         $booking_id - Booking ID.
		 * @global object $wpdb Global wpdb object
		 *
		 * @since 3.5
		 */
		public static function update_booking_tables( $_status, $order_id, $item_id, $item_value, $order, $booking_id = null ) {

			global $wpdb;

			$booking_post_id             = bkap_common::get_booking_id( $item_id );
			$order_has_multiple_bookings = is_array( $booking_post_id );

			if ( $order_has_multiple_bookings && null !== $booking_id && in_array( $booking_id, $booking_post_id ) ) {
				$booking_post_id = $booking_id;
			}

			// if the booking has been denied, release the bookings for re-allotment.
			if ( 'cancelled' === $_status ) {

				$select_query    = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history`
						              WHERE order_id= %d';
				$results         = $wpdb->get_results( $wpdb->prepare( $select_query, $order_id ) );
				$booking_details = array();

				foreach ( $results as $key => $value ) {

					$select_query_post = 'SELECT post_id,start_date, end_date, from_time, to_time FROM `' . $wpdb->prefix . 'booking_history`
								            WHERE id= %d';
					$results_post      = $wpdb->get_results( $wpdb->prepare( $select_query_post, $value->booking_id ) );

					if ( ! empty( $results_post ) ) {

						$booking_info                          = array(
							'post_id'    => $results_post[0]->post_id,
							'start_date' => $results_post[0]->start_date,
							'end_date'   => $results_post[0]->end_date,
							'from_time'  => $results_post[0]->from_time,
							'to_time'    => $results_post[0]->to_time,
						);
						$booking_details[ $value->booking_id ] = $booking_info;
					}
				}

				if ( ! empty( $booking_details ) ) {

					foreach ( $booking_details as $booking_id => $booking_data ) {
						if ( $item_value['product_id'] == $booking_data['post_id'] ) {
							$product_id = $booking_data['post_id'];
							// cross check the date and time as well as the product can be added to the cart more than once with different booking details.
							if ( $item_value['wapbk_booking_date'] == $booking_data['start_date'] ) {
								$time = $booking_data['from_time'];
								if ( '' !== $booking_data['to_time'] ) {
									$time = $booking_data['from_time'] . ' - ' . $booking_data['to_time'];
								}

								if ( isset( $item_value['wapbk_checkout_date'] ) && ( $item_value['wapbk_checkout_date'] == $booking_data['end_date'] ) ) {
									$item_booking_id = $booking_id;
									break;
								} elseif ( isset( $item_value['wapbk_time_slot'] ) ) {

									$metatime = $item_value['wapbk_time_slot'];

									if ( isset( $item_value['wapbk_timezone'] ) && $item_value['wapbk_timezone'] != '' ) {
										$offset   = bkap_get_offset( $item_value['wapbk_timeoffset'] );
										$metatime = bkap_convert_timezone_time_to_system_time( $metatime, $item_value, 'H:i' );
									}

									if ( $metatime == $time ) {
										$item_booking_id = $booking_id;
										break;
									}
								} else {
									$item_booking_id = $booking_id;
									break;
								}
							}
						}
					}

					// Delete Zooom Meeting.
					$booking_post_obj = bkap_checkout::get_bkap_booking( $booking_post_id );
					Bkap_Zoom_Meeting_Settings::bkap_delete_zoom_meeting( $booking_post_id, $booking_post_obj );

					bkap_delete_event_from_gcal( $product_id, $item_id );
					if ( isset( $item_booking_id ) ) {
						bkap_cancel_order::bkap_reallot_item( $item_value, $item_booking_id, $order_id );
					}

					bkap_delete_from_order_hitory( $order_id, $item_booking_id );

					do_action( 'bkap_update_booking_tables_after_cancelled_state', $_status, $item_id, $item_value, $order_id, $order );
				}
			} elseif ( 'confirmed' === $_status ) {

				$new_booking_data = bkap_get_meta_data( $booking_post_id );

				foreach ( $new_booking_data as $data ) {
					Bkap_Zoom_Meeting_Settings::bkap_create_zoom_meeting( $booking_post_id, $data );
				}

				$wapbk_booking_date = $item_value['wapbk_booking_date'];

				if ( $order_has_multiple_bookings ) {
					$_booking           = new BKAP_Booking( $booking_post_id );
					$wapbk_booking_date = date( 'Y-m-d', strtotime( $_booking->get_start() ) );
				}

				$valid_date = isset( $wapbk_booking_date ) ? bkap_common::bkap_check_date_set( $wapbk_booking_date ) : false;

				if ( $valid_date ) {

					$additional_data = array(
						'order_has_multiple_bookings' => $order_has_multiple_bookings,
						'booking_id'                  => $booking_post_id,
						'wapbk_booking_date'          => $wapbk_booking_date,
					);

					$event_details = bkap_event_details_from_item( $item_id, $item_value, $order_id, $order, $additional_data );

					require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class.gcal.php';

					$gcal    = new BKAP_Gcal();
					$user_id = get_current_user_id();

					if ( in_array( $gcal->get_api_mode( $user_id, $item_value['product_id'] ), array( 'directly', 'oauth' ), true ) ) {
						// if sync is disabled at the product level, set post_id to 0 to ensure admin settings are taken into consideration.
						$booking_settings = bkap_setting( $item_value['product_id'] );
						$post_id          = $item_value['product_id'];
						if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' === $booking_settings['product_sync_integration_mode'] ) ) {
							$post_id = 0;
						}

						$event_status = $gcal->insert_event( $event_details, $item_id, $user_id, $post_id, false );

						if ( $event_status ) {
							// add an order note, mentioning an event has been created for the item.
							$post_title = $event_details['product_name'];
							$order_note = __( "Details of Booking #".$booking_post_id." has been exported to Google Calendar", 'woocommerce-booking' );
							$order->add_order_note( $order_note );
						}
					}

					do_action( 'bkap_update_booking_tables_confirmed_integration', $_status, $item_id, $item_value, $order_id, $order, $event_details );
				}
				do_action( 'bkap_update_booking_tables_after_confirmed_state', $_status, $item_id, $item_value, $order_id, $order );
			}
		}

		/**
		 * Validate bookable products
		 *
		 * This function displays a notice and empties the cart if the cart contains
		 * any products that conflict with the new product being added.
		 *
		 * @param array $POST POST DATA.
		 * @param int   $product_id - Product ID.
		 * @return string
		 *
		 * @hook bkap_validate_cart_products
		 * @since 2.5
		 */
		public function bkap_validate_conflicting_products( $POST, $product_id ) {

			if ( ! apply_filters( 'bkap_validate_conflicting_products', true, $product_id ) ) {
				return 'yes';
			}

			$quantity_check_pass           = 'yes';
			$product_requires_confirmation = bkap_common::bkap_product_requires_confirmation( $product_id ); // check if the product being added requires confirmation.
			$cart_requires_confirmation    = bkap_common::bkap_cart_requires_confirmation(); // check if the cart contains a product that requires confirmation.
			$validation_status             = 'warn_modify_yes';

			switch ( $validation_status ) {
				case 'warn_modify_yes':
					$conflict = 'NO';

					if ( count( WC()->cart->cart_contents ) > 0 ) {
						// if product requires confirmation and cart contains product that does not.
						if ( $product_requires_confirmation && ! $cart_requires_confirmation ) {
							$conflict = 'YES';
						}
						// if product does not need confirmation and cart contains a product that does.
						if ( ! $product_requires_confirmation && $cart_requires_confirmation ) {
							$conflict = 'YES';
						}
						// if conflict.
						if ( 'YES' == $conflict ) {
							// remove existing products.
							WC()->cart->empty_cart();

							// add a notice.
							$message = bkap_get_book_t( 'book.conflicting-products' );
							wc_add_notice( __( $message, 'woocommerce-booking' ), $notice_type = 'notice' );
						}
					}
					break;
			}

			return $quantity_check_pass;
		}

		/**
		 * Show edit/delete link of the Order Item
		 *
		 * @param bool $status True/False.
		 * @param obj  $order Order Object.
		 * @hook wc_order_is_editable
		 * @since 4.10.2
		 */
		public function wc_order_is_editable_callback( $status, $order ) {

			global $wpdb;

			$order_id     = $order->get_id();
			$select_query = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history`
                                  WHERE order_id= %d';
			$results      = $wpdb->get_results( $wpdb->prepare( $select_query, $order_id ) );

			if ( ! empty( $results ) ) {
				return false;
			}
			return $status;
		}

		/**
		 * Add a checkbox in the Booking meta box for Cancelling Bookings.
		 *
		 * @param int $product_id - Product ID.
		 *
		 * @hook bkap_after_purchase_wo_date
		 * @since 5.9.0
		 */
		public function bkap_can_be_cancelled_checkbox( $product_id, $booking_settings ) {

			// Conditions to show/hide blocks.
			$set_duration_cancellation_display = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && isset( $booking_settings['booking_can_be_cancelled']['period'] ) && isset( $booking_settings['booking_can_be_cancelled']['duration'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] && '' !== $booking_settings['booking_can_be_cancelled']['period'] && '' !== $booking_settings['booking_can_be_cancelled']['duration'] ) ? 'can_be_cancelled_hide' : '';

			// Hide elements in case of fresh install of plugin.
			if ( ! isset( $booking_settings['booking_can_be_cancelled'] ) && ! isset( $booking_settings['booking_can_be_cancelled']['status'] ) ) {
				$set_duration_cancellation_display = 'can_be_cancelled_hide';
			}

			// Mark checkbox as checked/unchecked.
			$mark_checkbox = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] ) ? 'checked' : '';

			// Input and Select elements for duration and period for booking cancelling.
			$can_be_cancelled_duration = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] && isset( $booking_settings['booking_can_be_cancelled']['duration'] ) && '' !== $booking_settings['booking_can_be_cancelled']['duration'] ) ? $booking_settings['booking_can_be_cancelled']['duration'] : '';

			$can_be_cancelled_period = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] && isset( $booking_settings['booking_can_be_cancelled']['period'] ) && '' !== $booking_settings['booking_can_be_cancelled']['period'] ) ? $booking_settings['booking_can_be_cancelled']['period'] : '';

			// Show Input and Select eleemnts if data is existing for them in the database.
			$can_be_cancelled_until_section_display = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] && '' !== $set_duration_cancellation_display ) ? '' : 'can_be_cancelled_hide';
			?>		

			<div id="can_be_cancelled_section" class="booking_options-flex-main">
				<div class="booking_options-flex-child">
					<label for="bkap_can_be_cancelled"><?php esc_html_e( 'Can be cancelled?', 'woocommerce-booking' ); ?></label>
					<span id="set_duration_cancellation" class="can_be_cancelled_span <?php echo esc_attr( $set_duration_cancellation_display ); ?>"><?php echo wp_kses_post( 'click here to set a duration <br/>for the booking cancellation', 'woocommerce-booking' ); ?></span>
				</div>

				<div class="booking_options-flex-child">
					<label class="bkap_switch">
						<input type="checkbox" name="bkap_can_be_cancelled" id="bkap_can_be_cancelled" <?php echo esc_attr( $mark_checkbox ); ?>>
						<div class="bkap_slider round"></div>
					</label>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'When enabled, this allows bookings to be cancelled by customers. Bookings can be cancelled anytime or at some duration before the booking.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
				</div>
			</div>

			<div id="can_be_cancelled_until_section" class="booking_options-flex-main <?php echo esc_attr( $can_be_cancelled_until_section_display ); ?>">
				<div class="booking_options-flex-child">
					<label for="bkap_can_be_cancelled_until"><?php esc_html_e( 'Booking can be cancelled until', 'woocommerce-booking' ); ?></label>
				</div>

				<div class="booking_options-flex-child">
					<input type="number" name="bkap_can_be_cancelled_duration" id="bkap_can_be_cancelled_duration" min="1" value="<?php echo esc_attr( $can_be_cancelled_duration ); ?>">

					<select id="bkap_can_be_cancelled_period" name="bkap_can_be_cancelled_period">
						<option value="day" <?php selected( 'day', $can_be_cancelled_period ); ?>><?php echo esc_html_e( 'Day(s)', 'woocommerce-booking' ); ?></option>
						<option value="hour" <?php selected( 'hour', $can_be_cancelled_period ); ?>><?php echo esc_html_e( 'Hour(s)', 'woocommerce-booking' ); ?></option>
						<option value="minute" <?php selected( 'minute', $can_be_cancelled_period ); ?>><?php echo esc_html_e( 'Minute(s)', 'woocommerce-booking' ); ?></option>
					</select>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<span id="clear_duration_cancellation" class="can_be_cancelled_span" style="height:30px;line-height:30px;"><?php echo esc_html_e( 'clear selection', 'woocommerce-booking' ); ?></span>
				</div>
			</div>
			<?php
		}
	}
}
$bkap_booking_confirmation = new bkap_booking_confirmation();
?>
