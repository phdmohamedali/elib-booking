<?php
/**
 * Customer booking confirmed email
 */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php
$order = new WC_order( $booking->order_id );
if ( $order ) :
	$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
	?>
	<p><?php printf( __( 'Hello %s', 'woocommerce-booking' ), $billing_first_name ); ?></p>
<?php endif; ?>

<p><?php _e( 'Your booking has been confirmed. The details of your booking are shown below.', 'woocommerce-booking' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Booked Product', 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->product_title; ?></td>
		</tr>
		<tr>
			<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'book_item-meta-date' ), 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_booking_date; ?></td>
		</tr>
		<?php
		if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'checkout_item-meta-date' ), 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_checkout_date; ?></td>
			</tr>
			<?php
		}
		if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'book_item-meta-time' ), 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_booking_time; ?></td>
			</tr>
			<?php
		}
		if ( isset( $booking->resource_title ) && '' != $booking->resource_title ) {
			?>
				<tr>
					<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->resource_label; ?></th>
					<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->resource_title; ?></td>
				</tr>
			<?php
		}

		if ( isset( $booking->person_data ) && '' != $booking->person_data ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->person_label, 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->person_data; ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>

<?php if ( $order ) : ?>

	<?php
		$order_status = $order->get_status();
	if ( $order_status == 'pending' ) :
		?>
		<p><?php printf( __( 'To pay for this booking please use the following link: %s', 'woocommerce-booking' ), '<a id="bkap_pay_for_booking" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . __( 'Pay for booking', 'woocommerce-booking' ) . '</a>' ); ?></p>
	<?php endif; ?>

	<?php do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

	<?php
	if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
		$order_date = $order->order_date;
	} else {
		$order_post = get_post( $booking->order_id );
		$post_date  = strtotime( $order_post->post_date );
		$order_date = date( 'Y-m-d H:i:s', $post_date );
	}
	?>
	<h2><?php echo __( 'Order', 'woocommerce-booking' ) . ': ' . $order->get_order_number(); ?> (<?php printf( '<time datetime="%s">%s</time>', date_i18n( 'c', strtotime( $order_date ) ), date_i18n( wc_date_format(), strtotime( $order_date ) ) ); ?>)</h2>
	<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
		<thead>
			<tr>
				<th scope="col" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Product', 'woocommerce-booking' ); ?></th>
				<th scope="col" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Quantity', 'woocommerce-booking' ); ?></th>
				<th scope="col" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Price', 'woocommerce-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
				$downloadable = $order->is_download_permitted();

			switch ( $order_status ) {
				case 'completed':
					$args = array(
						'show_download_links' => $downloadable,
						'show_sku'            => false,
						'show_purchase_note'  => true,
					);
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
						echo $order->email_order_items_table( $args );
					} else {
						echo wc_get_email_order_items( $order, $args );
					}
					break;
				case 'processing':
					$args = array(
						'show_download_links' => $downloadable,
						'show_sku'            => true,
						'show_purchase_note'  => true,
					);
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
						echo $order->email_order_items_table( $args );
					} else {
						echo wc_get_email_order_items( $order, $args );
					}
					break;
				default:
					$args = array(
						'show_download_links' => $downloadable,
						'show_sku'            => true,
						'show_purchase_note'  => false,
					);
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
						echo $order->email_order_items_table( $args );
					} else {
						echo wc_get_email_order_items( $order, $args );
					}
					break;
			}
			?>
		</tbody>
		<tfoot>
			<?php
			if ( $totals = $order->get_order_item_totals() ) {
				$i = 0;
				foreach ( $totals as $total ) {
					$i++;
					?>
						<tr>
							<th scope="row" colspan="2" style="text-align:left; border: 1px solid #eee; 
							<?php
							if ( $i == 1 ) {
								echo 'border-top-width: 4px;';}
							?>
							"><?php echo $total['label']; ?></th>
							<td style="text-align:left; border: 1px solid #eee; 
							<?php
							if ( $i == 1 ) {
								echo 'border-top-width: 4px;';}
							?>
							"><?php echo $total['value']; ?></td>
						</tr>
						<?php
				}
			}
			?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

	<?php do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email ); ?>

<?php endif; ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer' );
