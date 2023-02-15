<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Handling languages of Labels in the Booking and Appointment Plugin for WooCommerce
 *
 * @author      Tyche Softwares
 * @package     BKAP/Labels
 * @since       1.7
 */

global $book_translations, $book_lang;

/**
 * This function is used to call the strings defined for translation
 *
 * @since 1.7
 * @global array $book_translations Array of all the labels
 * @global string $book_lang language set to english
 * @return array $book_translations returns array of all the labels
 */

function bkap_get_book_t( $str ) {

	global $book_translations, $book_lang;

		$book_lang         = 'en';
		$book_translations = array(
			'en' => array(

				// Labels for Booking Date & Booking Time on the product page
				'book_date-label'              => __( 'Start Date', 'woocommerce-booking' ),
				'checkout_date-label'          => ( '<br>' . __( 'End Date', 'woocommerce-booking' ) ),
				'book_time-label'              => __( 'Booking Time', 'woocommerce-booking' ),
				'book.item-comments'           => __( 'Comments', 'woocommerce-booking' ),

				// Labels for Booking Date & Booking Time on the "Order Received" page on the web and in the notification email to customer & admin
				'book_item-meta-date'          => __( 'Start Date', 'woocommerce-booking' ),
				'checkout_item-meta-date'      => __( 'End Date', 'woocommerce-booking' ),
				'book_item-meta-time'          => __( 'Booking Time', 'woocommerce-booking' ),

				// Labels for Booking Date & Booking Time on the Cart Page and the Checkout page
				'book_item-cart-date'          => __( 'Start Date', 'woocommerce-booking' ),
				'checkout_item-cart-date'      => __( 'End Date', 'woocommerce-booking' ),
				'book_item-cart-time'          => __( 'Booking Time', 'woocommerce-booking' ),

				// Labels for partial payment in partial payment addon
				'book.item-partial-total'      => __( 'Total ', 'woocommerce-booking' ),
				'book.item-partial-deposit'    => __( 'Partial Deposit ', 'woocommerce-booking' ),
				'book.item-partial-remaining'  => __( 'Amount Remaining', 'woocommerce-booking' ),
				'book.partial-payment-heading' => __( 'Partial Payment', 'woocommerce-booking' ),

				// Labels for full payment in partial payment addon
				'book.item-total-total'        => __( 'Total ', 'woocommerce-booking' ),
				'book.item-total-deposit'      => __( 'Total Deposit ', 'woocommerce-booking' ),
				'book.item-total-remaining'    => __( 'Amount Remaining', 'woocommerce-booking' ),
				'book.total-payment-heading'   => __( 'Total Payment', 'woocommerce-booking' ),

				// Labels for security deposits payment in partial payment addon
				'book.item-security-total'     => __( 'Total ', 'woocommerce-booking' ),
				'book.item-security-deposit'   => __( 'Security Deposit ', 'woocommerce-booking' ),
				'book.item-security-remaining' => __( 'Product Price ', 'woocommerce-booking' ),
				'book.total-security-heading'  => __( 'Security Deposit', 'woocommerce-booking' ),

				// Message to be displayed on the Product page when conflicting products are added.
				'book.conflicting-products'    => __( 'You cannot add products requiring Booking confirmation along with other products that do not need a confirmation. The existing products have been removed from your cart.', 'woocommerce-booking' ),
			),
		);

	return $book_translations[ $book_lang ][ $str ];
}

