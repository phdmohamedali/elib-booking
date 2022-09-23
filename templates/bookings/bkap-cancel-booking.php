<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Template for Bookings View on the My Account Page
 *
 * @author   Tyche Softwares
 * @package  Bookings and Appointment Plugin
 */

defined( 'ABSPATH' ) || exit;

do_action( 'bkap_cancel_booking_actions' );

if ( $has_bookings ) :

	foreach ( $bookings as $booking_group_title => $booking_group ) :
		if ( 0 === count( $booking_group ) ) {
			continue;
		}

		$bkap_get_account_endpoint_columns = Bkap_Cancel_Booking::bkap_get_account_endpoint_columns();

		// Remove action column for Past Bookings.
		if ( 'Past Bookings' === $booking_group_title ) {
			unset( $bkap_get_account_endpoint_columns['booking-action'] );
		} ?>

		<h3 class="entry-title"><?php ( 'Upcoming Bookings' === $booking_group_title ) ? esc_html_e( 'Upcoming Bookings', 'woocommerce-booking' ) : esc_html_e( 'Past Bookings', 'woocommerce-booking' ); ?></h2>
		<table class="bkap-cancel-booking-table shop_table shop_table_responsive">
			<thead>
				<tr>
					<?php foreach ( $bkap_get_account_endpoint_columns as $column_id => $column_name ) : ?>
						<th><?php echo esc_html( $column_name ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>

			<tbody>
				<?php
				foreach ( $booking_group as $_booking ) {

					$booking_id                     = $_booking->id;
					$booking                        = new BKAP_Booking( $booking_id );
					$product_id                     = $booking->get_product_id();
					$product_url                    = get_permalink( $product_id );
					$product_name                   = get_the_title( $product_id );
					$order_id                       = $booking->get_order_id();
					$_order                         = wc_get_order( $order_id );
					$booking_status                 = ucwords( $booking->get_status() );
					$booking_start_date             = $booking->get_start_date() . ' ' . $booking->get_start_time();
					$booking_end_date               = $booking->get_end_date() . ' ' . $booking->get_end_time();
					$order_url                      = $_order->get_view_order_url();
					$order_number                   = $_order->get_order_number();
					$zoom_meeting_link              = Bkap_Cancel_Booking::bkap_get_zoom_meeting_link( $booking_id );
					$bkap_cancel_booking_action     = ( isset( $bkap_get_account_endpoint_columns['booking-action'] ) ) ? Bkap_Cancel_Booking::bkap_cancel_booking_action( $booking_id ) : '';
					$bkap_reschedule_booking_action = ( isset( $bkap_get_account_endpoint_columns['booking-action'] ) ) ? Bkap_Cancel_Booking::bkap_reschedule_booking_action( $booking_id ) : '';
					?>

					<tr class="bkap-cancel-booking-table__row">
						<?php foreach ( $bkap_get_account_endpoint_columns as $column_id => $column_name ) : ?>
							<td class="bkap-cancel-booking-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">

								<?php if ( 'id' === $column_id ) : ?>
									<?php echo esc_html( $booking_id ); ?>

								<?php elseif ( 'booked-product' === $column_id ) : ?>
									<a href="<?php echo esc_url( $product_url ); ?>">
										<?php echo esc_html( $product_name ); ?>
									</a>

								<?php elseif ( 'order-id' === $column_id ) : ?>
									<a href="<?php echo esc_url( $order_url ); ?>">
										<?php echo esc_html( __( '#', 'woocommerce-booking' ) . $order_number ); ?>
									</a>

								<?php elseif ( 'start-date' === $column_id ) : ?>
									<?php echo esc_html( $booking_start_date ); ?>

								<?php elseif ( 'end-date' === $column_id ) : ?>
									<?php echo esc_html( $booking_end_date ); ?>

								<?php elseif ( 'booking-status' === $column_id ) : ?>
									<?php echo esc_html( $booking_status ); ?>

								<?php elseif ( 'zoom-meeting' === $column_id ) : ?>
									<?php echo wp_kses_post( $zoom_meeting_link ); ?>

								<?php elseif ( 'booking-action' === $column_id ) : ?>
									<?php echo $bkap_cancel_booking_action; // phpcs:ignore ?>
									<?php echo $bkap_reschedule_booking_action; // phpcs:ignore ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
	<?php endforeach; ?>

<?php else : ?>
	<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
		<?php esc_html_e( 'No Booking has been placed.', 'woocommerce-booking' ); ?>
	</div>
<?php endif; ?>
