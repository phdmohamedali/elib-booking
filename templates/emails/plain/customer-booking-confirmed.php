<?php
/**
 * Customer booking confirmed email
 */

echo '= ' . $email_heading . " =\n\n";

$order = wc_get_order( $booking->order_id );

if ( $order ) {
	$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
	echo sprintf( __( 'Hello %s', 'woocommerce-booking' ), $billing_first_name ) . "\n\n";
}
echo __( 'Your booking for has been confirmed. The details of your booking are shown below.', 'woocommerce-booking' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Booked: %s', 'woocommerce-booking' ), $booking->product_title ) . "\n";

echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-date' ), $booking->item_booking_date ) . "\n";

if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'checkout_item-meta-date' ), $booking->item_checkout_date ) . "\n";
}

if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-time' ), $booking->item_booking_time ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( $order ) {
	$order_status = $order->get_status();
	if ( $order_status == 'pending' ) {

		echo sprintf( __( 'To pay for this booking please use the following link: %s', 'woocommerce-booking' ), $order->get_checkout_payment_url() ) . "\n\n";
	}

	do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );

	if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
		$order_date = $order->order_date;
	} else {
		$order_post      = wc_get_order( $booking->order_id );
		$order_strtotime = ! is_null( $order_post->get_date_created() ) ? $order_post->get_date_created()->getOffsetTimestamp() : '';
		$order_date      = date( 'Y-m-d H:i:s', $order_strtotime );
	}
	echo sprintf( __( 'Order number: %s', 'woocommerce-booking' ), $order->get_order_number() ) . "\n";
	echo sprintf( __( 'Order date: %s', 'woocommerce-booking' ), date_i18n( wc_date_format(), strtotime( $order_date ) ) ) . "\n";

	do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

	echo "\n";

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

	echo "==========\n\n";

	if ( $totals = $order->get_order_item_totals() ) {
		foreach ( $totals as $total ) {
			echo $total['label'] . "\t " . $total['value'] . "\n";
		}
	}

	echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

	do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
