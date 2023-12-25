<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Create related orders for Rescheduled Bookings
 *
 * @author      Tyche Softwares
 * @package     BKAP/Reschedule
 * @category    Classes
 */

if ( ! class_exists( 'Bkap_Rescheduled_Order_Class' ) ) {

	/**
	 * Class for creating related orders for rescheduled bookings
	 *
	 * @since 4.2.0
	 */
	class Bkap_Rescheduled_Order_Class {

		/**
		 * Default Constructor. Attach functions to hooks
		 *
		 * @since 4.2.0
		 */
		public function __construct() {

			add_filter( 'woocommerce_hidden_order_itemmeta', array( &$this, 'bkap_rescheduled_hidden_order_itemmeta' ), 10, 1 );
			add_action( 'woocommerce_after_order_itemmeta', array( &$this, 'bkap_button_after_order_meta' ), 10, 3 );
			add_action( 'woocommerce_order_details_after_order_table', array( &$this, 'bkap_multidates_sub_view_related_bookings' ), 10, 1 );
		}

		/**
		 * Function will add Booking table for Multiple Dates Reschedule.
		 *
		 * @param obj $order Order Object.
		 *
		 * @since 5.5.0
		 */
		public function bkap_multidates_sub_view_related_bookings( $order ) {

			$order_items = $order->get_items();

			$booking_ids   = '';
			$booking_types = array();
			foreach ( $order_items as $order_item ) {
				$product_id      = $order_item->get_product_id();
				$booking_types[] = bkap_type( $product_id );
				$booking_ids     = bkap_common::get_booking_id( $order_item->get_id() );
			}

			if ( is_account_page() && ! empty( $booking_ids ) && ( in_array( 'multidates_fixedtime', $booking_types, true ) || in_array( 'multidates', $booking_types, true ) ) ) {

				?>
			<header>
				<h2><?php esc_html_e( 'Order Bookings', 'woocommerce-booking' ); ?></h2>
			</header>
			<table class="shop_table shop_table_responsive my_account_orders">
				<thead>
					<tr>
						<th class="booking-number">
							<span class="nobr"><?php esc_html_e( 'Booking ID', 'woocommerce-booking' ); ?></span>
						</th>
						<th class="booking-product">
							<span class="nobr"><?php esc_html_e( 'Product', 'woocommerce-booking' ); ?></span>
						</th>
						<th class="booking-date">
							<span class="nobr"><?php esc_html_e( 'Booking Details', 'woocommerce-booking' ); ?></span>
						</th>
						<th class="booking-date">
							<span class="nobr"><?php esc_html_e( 'Action', 'woocommerce-booking' ); ?></span>
						</th>
					</tr>
				</thead>

				<tbody>
					<?php

					$book_item_meta_date     = ( '' === get_option( 'book_item-meta-date' ) ) ? __( 'Start Date', 'woocommerce-booking' ) : get_option( 'book_item-meta-date' );
					$checkout_item_meta_date = ( '' === get_option( 'checkout_item-meta-date' ) ) ? __( 'End Date', 'woocommerce-booking' ) : get_option( 'checkout_item-meta-date' );
					$book_item_meta_time     = ( '' === get_option( 'book_item-meta-time' ) ) ? __( 'Booking Time', 'woocommerce-booking' ) : get_option( 'book_item-meta-time' );

					foreach ( $order_items as $item ) :
						$product_id   = $item->get_product_id();
						$booking_type = bkap_type( $product_id );

						if ( ! in_array( $booking_type, array( 'multidates_fixedtime', 'multidates' ), true ) ) {
							continue;
						}
						$item_id                   = $item->get_id();
						$booking_ids               = bkap_common::get_booking_id( $item_id );
						$all_booking_details       = array();
						$date_meta                 = 0;
						$hidden_date_meta          = 0;
						$date_checkout_meta        = 0;
						$hidden_date_checkout_meta = 0;
						$time_slot_meta            = 0;
						$resource_id_meta          = 0;
						$i                         = 0;

						foreach ( $item->get_meta_data() as $meta_index => $meta ) {

							if ( $meta->key === $book_item_meta_date ) {
								$all_booking_details[ $date_meta ]['date'] = $meta->value;
								$date_meta                             = $date_meta + 1;
							} elseif ( '_wapbk_booking_date' === $meta->key ) {
								$hidden_date                                             = explode( '-', $meta->value );
								$all_booking_details[ $hidden_date_meta ]['hidden_date'] = $hidden_date[2] . '-' . $hidden_date[1] . '-' . $hidden_date[0];

								$hidden_date_meta = $hidden_date_meta + 1;
							} elseif ( $meta->key === $checkout_item_meta_date ) {
								$all_booking_details[ $date_checkout_meta ]['date_checkout'] = $meta->value;

								$date_checkout_meta = $date_checkout_meta + 1;
							} elseif ( '_wapbk_checkout_date' === $meta->key ) {
								$hidden_date_checkout                                                  = explode( '-', $meta->value );
								$all_booking_details[ $hidden_date_checkout_meta ]['hidden_date_checkout'] = $hidden_date_checkout[2] . '-' . $hidden_date_checkout[1] . '-' . $hidden_date_checkout[0];

								$hidden_date_checkout_meta = $hidden_date_checkout_meta + 1;
							} elseif ( $meta->key === $book_item_meta_time ) {
								$all_booking_details[ $time_slot_meta ]['time_slot'] = $meta->value;

								$time_slot_meta = $time_slot_meta + 1;
							} elseif ( '_resource_id' === $meta->key ) {
								$all_booking_details[ $resource_id_meta ]['resource_id'] = $meta->value;

								$resource_id_meta = $resource_id_meta + 1;
							}
						}

						if ( is_array( $booking_ids ) ) :

							foreach ( $booking_ids as $b_id ) :
								$booking    = new BKAP_Booking( $b_id );
								$start_date = $booking->get_start_date();
								$end_date   = $booking->get_end_date();

								$start_time = $booking->get_start_time();
								$end_time   = $booking->get_end_time();

								$booking_details = $start_date;
								if ( $start_time != '' && $start_time != $end_time ) {
									$booking_details .= ' ' . $start_time . ' - ' . $end_time;
								} elseif ( $end_date != '' ) {
									$booking_details .= ' - ' . $end_date;
								}

								?>

								<tr class="order">
									<td class="booking-number" data-title="<?php esc_attr_e( 'ID', 'woocommerce-booking' ); ?>">
										<?php echo $b_id; ?>
									</td>
									<td class="booking-product" data-title="<?php esc_attr_e( 'Booking Product', 'woocommerce-booking' ); ?>">
										<?php echo $item->get_name(); ?>
									</td>
									<td class="booking-details" data-title="<?php esc_attr_e( 'Booking Details', 'woocommerce-booking' ); ?>">
										<?php echo $booking_details; ?>
									</td>
									<td class="booking-details" data-title="<?php esc_attr_e( 'Booking Details', 'woocommerce-booking' ); ?>">
										<?php
										$edit_booking_label = apply_filters(
											'bkap_edit_booking_label',
											__( 'Reschedule Booking', 'woocommerce-booking' )
										);

										if ( 'cancelled' !== $booking->get_status() ) {
											printf( '<input type="button" class="bkap_edit_bookings" onclick="bkap_edit_booking_class.bkap_edit_bookings(%d,%s,%d)" value="%s">', $item->get_product_id( 'view' ), $item_id . '.' . $i, $i, __( $edit_booking_label, 'woocommerce-booking' ) );
										} else {
											esc_html_e( 'Cancelled', 'woocommerce-booking' );
										}

										$localized_array = array(
											'bkap_booking_params' => $all_booking_details[ $i ],
											'bkap_cart_item' => $item,
											'bkap_cart_item_key' => $item_id . '_' . $i,
											'bkap_order_id' => $order->get_id(),
											'bkap_page_type' => 'view-order',
											'bkap_i' => $i,
											'bkap_booking_id' => $b_id,
										);

										// Additional Data for addons.
										$additional_addon_data = bkap_common::bkap_get_order_item_addon_data( $item );

										bkap_edit_bookings_class::bkap_load_template(
											$all_booking_details[ $i ],
											$item->get_product(),
											$item->get_product_id( 'view' ),
											$localized_array,
											$item_id . '_' . $i,
											$item->get_variation_id( 'view' ),
											$additional_addon_data
										);
										$i++;
										?>
									</td>
								</tr>

							<?php endforeach; ?>

						<?php else : ?>

							<?php

							$booking    = new BKAP_Booking( $booking_ids );
							$start_date = $booking->get_start_date();
							$end_date   = $booking->get_end_date();

							$start_time = $booking->get_start_time();
							$end_time   = $booking->get_end_time();

							$booking_details = $start_date;
							if ( $start_time != '' && $start_time != $end_time ){
								$booking_details .= ' ' . $start_time . ' - ' . $end_time;
							}elseif ( $end_date != '' ) {
								$booking_details .= ' - ' . $end_date;
							}

							?>

							<tr class="order">
								<td class="booking-number" data-title="<?php esc_attr_e( 'ID', 'woocommerce-booking' ); ?>">
									<?php echo $booking_ids; ?>
								</td>
								<td class="booking-product" data-title="<?php esc_attr_e( 'Booking Product', 'woocommerce-booking' ); ?>">
									<?php echo $item->get_name(); ?>
								</td>
								<td class="booking-details" data-title="<?php esc_attr_e( 'Booking Details', 'woocommerce-booking' ); ?>">
									<?php echo $booking_details; ?>
								</td>
							</tr>

						<?php endif; ?>

					<?php endforeach; ?>

				</tbody>
			</table>

			<?php
			}
		}

		/**
		 * Add hidden meta fields to order for reschedule details
		 *
		 * @param array $meta_keys Existing Meta Keys Array
		 * @return array Meta Keys
		 *
		 * @since 4.2.0
		 * @hook woocommerce_hidden_order_itemmeta
		 */

		public function bkap_rescheduled_hidden_order_itemmeta( $meta_keys ) {

			$meta_keys[] = '_bkap_resch_orig_order_id';
			$meta_keys[] = '_bkap_resch_rem_bal_order_id';

			return $meta_keys;
		}

		/**
		 * Add meta box on order page to display related order
		 *
		 * @param string        $original_order_id Parent Order ID
		 * @param WC_Order_Item $item Order Item Object
		 *
		 * @return string New Order ID
		 *
		 * @since 4.2.0
		 */

		public static function bkap_rescheduled_create_order( $original_order_id, $item ) {

			$original_order      = wc_get_order( $original_order_id );
			$new_remaining_order = wc_create_order(
				array(
					'status'      => 'wc-pending',
					'customer_id' => $original_order->get_user_id(),
				)
			);

			$new_remaining_order->set_address(
				array(
					'first_name' => $original_order->get_billing_first_name(),
					'last_name'  => $original_order->get_billing_last_name(),
					'company'    => $original_order->get_billing_company(),
					'address_1'  => $original_order->get_billing_address_1(),
					'address_2'  => $original_order->get_billing_address_2(),
					'city'       => $original_order->get_billing_city(),
					'state'      => $original_order->get_billing_state(),
					'postcode'   => $original_order->get_billing_postcode(),
					'country'    => $original_order->get_billing_country(),
					'email'      => $original_order->get_billing_email(),
					'phone'      => $original_order->get_billing_phone(),
				)
			);

			$new_remaining_order->set_address(
				array(
					'first_name' => $original_order->get_shipping_first_name(),
					'last_name'  => $original_order->get_shipping_last_name(),
					'company'    => $original_order->get_shipping_company(),
					'address_1'  => $original_order->get_shipping_address_1(),
					'address_2'  => $original_order->get_shipping_address_2(),
					'city'       => $original_order->get_shipping_city(),
					'state'      => $original_order->get_shipping_state(),
					'postcode'   => $original_order->get_shipping_postcode(),
					'country'    => $original_order->get_shipping_country(),
				)
			);

			$item_id = $new_remaining_order->add_product(
				$item['product'],
				$item['qty'],
				array(
					'totals' => array(
						'subtotal' => $item['amount'],
						'total'    => $item['amount'],
					),
				)
			);

			wc_update_order_item_meta( $item_id, '_bkap_resch_orig_order_id', $original_order_id, '' );
			wc_update_order_item( $item_id, array( 'order_item_name' => sprintf( __( 'Additional Payment for %1$s (Order #%2$d )' ), $item['product']->get_title(), $original_order_id ) ) );

			$new_remaining_order->calculate_totals();

			$new_remaining_order_post = array(
				'order_id' => $new_remaining_order->get_id(),
				'parent'   => $original_order_id,
			);

			wc_update_order( $new_remaining_order_post );

			return $new_remaining_order->get_id();
		}

		/**
		 * Add link for related order in admin side for rescheduled orders where there is an additional payment
		 *
		 * @param string|int    $item_id Order Item ID
		 * @param WC_Order_Item $item Order Item Object
		 * @param WC_Product    $product Product Object
		 *
		 * @since 4.2.0
		 *
		 * @hook woocommerce_after_order_itemmeta
		 */

		public function bkap_button_after_order_meta( $item_id, $item, $product ) {

			if ( $item['_bkap_resch_rem_bal_order_id'] !== '' && $item['_bkap_resch_rem_bal_order_id'] !== null ) {
				$order_url = bkap_order_url( $item['_bkap_resch_rem_bal_order_id'] );
				?>
					<a href="<?php echo esc_url( $order_url ); ?>" class="button button-small">
						<?php _e( 'Related Order', 'woocommerce-booking' ); ?>
					</a>
				<?php
			} elseif ( $item['_bkap_resch_orig_order_id'] !== '' && $item['_bkap_resch_orig_order_id'] !== null ) {
				$order_url = bkap_order_url( $item['_bkap_resch_orig_order_id'] );
				?>
					<a href="<?php echo esc_url( $order_url ); ?>" class="button button-small">
						<?php _e( 'Parent Order', 'woocommerce-booking' ); ?>
					</a>
				<?php
			}
		}
	}
}

$bkap_rescheduled_order_class = new bkap_rescheduled_order_class();
