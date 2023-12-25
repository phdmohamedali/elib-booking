<?php
/**
 * Show bookings on the WordPress Dashboard Widget
 *
 * @since 4.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Bkap_Booking_Dashboard_Widget' ) ) {

	/**
	 * class Bkap_Booking_Dashboard_Widget
	 */
	class Bkap_Booking_Dashboard_Widget {

		/**
		 * Constructor
		 *
		 * @since 4.12.0
		 */
		public function __construct() {
			add_action( 'wp_dashboard_setup', array( &$this, 'bkap_add_booking_dashboard_widgets' ) );
		}

		/**
		 * Function to add the dashboard widget
		 *
		 * @since 4.12.0
		 * @hook wp_dashboard_setup
		 */

		function bkap_add_booking_dashboard_widgets() {
			// we defining a function to hook to the wp_dashboard_setup action
			wp_add_dashboard_widget(
				'bkap_booking_widget_id',
				__( 'Bookings', 'woocommerce-booking' ),
				array( $this, 'bkap_dashboard_widget_function' )
			);
		}

		/**
		 * Function to display widget content.
		 *
		 * @since 4.12.0
		 */

		function bkap_dashboard_widget_function() {

			$args = apply_filters(
				'bkap_booking_dashboard_widget_args',
				array(
					'post_type'      => 'bkap_booking',
					'post_status'    => 'All',
					'posts_per_page' => 10,
				)
			);

			$bookings = get_posts( $args );

			$top_five_booking = array();

			if ( count( $bookings ) > 0 ) {
				$i = 1;

				foreach ( $bookings as $key => $value ) {

					$top_five_booking[] = $value->ID;

					$booking_post = new BKAP_Booking( $value->ID );

					echo '<b>' . $i . '.</b> ';

					printf( '<a href="%s" target="_blank">' . __( 'Booking #%d', 'woocommerce-booking' ) . '</a>', admin_url( 'post.php?post=' . $value->ID . '&action=edit' ), $value->ID );

					echo ' | ';

					$order = $booking_post->get_order();
					if ( $order ) {
						$order_url = bkap_order_url( $order->get_id() );
						echo '<a href="' . $order_url . '" target="_blank">Order #' . $order->get_order_number() . '</a> - ' . esc_html( wc_get_order_status_name( $order->get_status() ) );
					} else {
						echo '-';
					}
					echo '<br><i><b>';
					echo __( 'Booking Starts On: ', 'woocommerce-booking' );
					echo '</b></i>';
					echo $booking_post->get_start_date() . ' - ' . $booking_post->get_start_time();

					if ( $booking_post->get_end_date() != '' ) {
						echo '<br>';
						echo '<i><b>';
						echo __( 'Booking Ends On: ', 'woocommerce-booking' );
						echo '</b></i>';
						echo $booking_post->get_end_date() . ' - ' . $booking_post->get_end_time();
					}

					echo '<br><hr>';
					$i++;
				}

				echo '<div style="text-align:right;margin-top:20px;font-size:large;">
			    		<span class="dashicons dashicons-calendar-alt"></span> ';

				printf( '<a href="%s" target="_blank">' . __( 'View all bookings', 'woocommerce-booking' ) . '</a>', admin_url( 'edit.php?post_type=bkap_booking' ) );

				echo '</div>';
			} else {
				echo __( 'No bookings.' );
			}
		}
	}
	$bkap_booking_dashboard_widget = new Bkap_Booking_Dashboard_Widget();
}

