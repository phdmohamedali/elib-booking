<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Template for Bookings Only Date Setting. This template shall be resued on Cart, Checkout and My Account Pages
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ( isset( $booking_settings['booking_fixed_block_enable'] ) && 'yes' !== $booking_settings['booking_fixed_block_enable'] ) || ! isset( $booking_settings['booking_fixed_block_enable'] ) ) {
	?>
	<div id="bkap-booking-form" class="bkap-booking-form">
	<?php
}

do_action( 'bkap_before_booking_form', $product_id, $booking_settings, $hidden_dates );
$method_to_show = 'bkap_check_for_time_slot';
$get_method     = bkap_common::bkap_ajax_on_select_date( $product_id );

if ( isset( $get_method ) && 'multiple_time' === $get_method ) {
	$method_to_show = apply_filters( 'bkap_function_slot', '' );
}

// fetch specific booking dates.
$booking_dates_arr = array();
if ( isset( $booking_settings['booking_specific_date'] ) ) {
	$booking_dates_arr = $booking_settings['booking_specific_date'];
}

$booking_dates_str = '';
if ( isset( $booking_settings['booking_specific_booking'] ) && 'on' === $booking_settings['booking_specific_booking'] ) {
	if ( ! empty( $booking_dates_arr ) ) {
		foreach ( $booking_dates_arr as $k => $v ) {
			$booking_dates_str .= '"' . $k . '",';
		}
	}
	$booking_dates_str = substr( $booking_dates_str, 0, strlen( $booking_dates_str ) - 1 );
}

?>
	<input 
		type="hidden" 
		name="wapbk_booking_dates" 
		id="wapbk_booking_dates" 
		value='<?php echo $booking_dates_str; ?>'
	>
<?php
// Display the stock div above the dates.
$availability_display = false;
if ( isset( $global_settings->booking_availability_display ) && 'on' === $global_settings->booking_availability_display ) {
	$total_stock_message  = bkap_total_stock_message( $booking_settings, $product_id, $booking_type );
	$availability_display = true;
}

$calendar_icon_file = get_option( 'bkap_calendar_icon_file' );
$calendar_src       = '';
if ( '' !== $calendar_icon_file && 'none' !== $calendar_icon_file ) {
	$calendar_src = plugins_url() . '/woocommerce-booking/assets/images/' . $calendar_icon_file;
} elseif ( 'none' !== $calendar_icon_file ) {
	$calendar_src = plugins_url() . '/woocommerce-booking/assets/images/calendar1.gif';
}

$calendar_src = apply_filters( 'bkap_calendar_icon_file', $calendar_src, $product_id, $booking_settings );

$disabled        = '';
$disabled_status = apply_filters( 'bkap_disable_booking_fields', false, $product_id );
if ( $disabled_status && '' != bkap_common::bkap_date_from_session_cookie( 'start_date' ) ) {
	$disabled     = 'disabled="disabled"';
	$calendar_src = '';
}

$bkap_inline = '';
if ( 'bkap_post' !== bkap_get_page() && isset( $booking_settings['enable_inline_calendar'] ) && 'on' === $booking_settings['enable_inline_calendar'] ) {
	$bkap_inline = 'on';
}

do_action( 'bkap_before_availability_message', $product_id, $booking_settings, $booking_type );

if ( true === $availability_display ) {
	?>
	<div id="bkap_show_stock_status" name="show_stock_status" class="bkap_show_stock_status" >
		<?php echo __( $total_stock_message, 'woocommerce-booking' ); ?>
	</div>
	<?php
}

do_action( 'bkap_after_availability_message', $product_id, $booking_settings, $booking_type );

$display_start = apply_filters( 'bkap_check_to_show_start_date_field', true, $product_id, $booking_settings, $hidden_dates, $global_settings );

if ( $display_start ) {
	?>
	<div class="bkap_start_date" id="bkap_start_date">
		<label class="book_start_date_label" style="margin-top:1em;">
			<?php
				$bkap_start_date_label = get_option( 'book_date-label', __( 'Start Date', 'woocommerce-booking' ) );
				$bkap_start_date_label = apply_filters( 'bkap_change_start_date_label', $bkap_start_date_label, $booking_settings, $product_id );
				echo __( $bkap_start_date_label, 'woocommerce-booking' );
			?>
		</label>

		<input 
			type="text" 
			id="booking_calender" 
			name="booking_calender" 
			class="booking_calender" 
			style="cursor: text!important;" 
			readonly
			<?php echo $disabled; ?>
		/>

		<?php
		if ( '' === $bkap_inline ) :
			if ( '' !== $calendar_src ) :
				?>
			<img 
				src="<?php echo $calendar_src; ?>"
				style="cursor:pointer!important;width: 20px;height: 20px;"
				id ="checkin_cal"
			/>
				<?php
			endif;
		endif;
		?>
		<div id="inline_calendar"></div>
	</div>

	<?php

}

if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' === $booking_settings['booking_enable_multiple_day'] ) {

	?>
		<div class="bkap_end_date" id="bkap_end_date">
			<label class="book_end_date_label">
				<?php
				$bkap_end_date_label = get_option( 'checkout_date-label', __( 'End Date', 'woocommerce-booking' ) );
				$bkap_end_date_label = apply_filters( 'bkap_change_end_date_label', $bkap_end_date_label, $booking_settings, $product_id );
				echo __( $bkap_end_date_label, 'woocommerce-booking' );
				?>
				 
			</label>
			<input 
				type="text" 
				id="booking_calender_checkout" 
				name="booking_calender_checkout" 
				class="booking_calender" 
				style="cursor: text!important;" 
				readonly
				<?php echo $disabled; ?>
			/>
			<?php
			if ( '' === $bkap_inline ) :
				if ( '' !== $calendar_src ) :
					?>
				<img 
					src="<?php echo $calendar_src; ?>"
					style="cursor:pointer!important;width: 20px;height: 20px;" 
					id ="checkout_cal"
				/>
					<?php
				endif;
			endif;
			?>

			<div id="inline_calendar_checkout"></div>
		</div>
	<?php
}

?>
	<div id="show_time_slot" name="show_time_slot" class="show_time_slot">
		<?php
		if ( ( isset( $booking_settings['booking_enable_time'] ) &&
				in_array( $booking_settings['booking_enable_time'], array( 'on', 'dates_time' ), true ) &&
				isset( $booking_settings['booking_time_settings'] ) )
				|| ( isset( $booking_settings['booking_enable_time'] ) &&
				'duration_time' === $booking_settings['booking_enable_time'] &&
				isset( $booking_settings['bkap_duration_settings'] )
				)
			) {
			?>
			<label id="bkap_book_time">
			<?php
				$bkap_book_time_label = get_option( 'book_time-label', __( 'Booking Time', 'woocommerce-booking' ) );
				$bkap_book_time_label = apply_filters( 'bkap_change_book_time_label', $bkap_book_time_label, $booking_settings, $product_id );
				echo __( $bkap_book_time_label, 'woocommerce-booking' );
			?>
				</label><br/>
				<?php
				if ( isset( $booking_settings['enable_inline_calendar'] ) &&
						'on' !== $booking_settings['enable_inline_calendar'] ) :
					?>
					<div id="cadt"><?php echo apply_filters( 'bkap_change_cadt', __( 'Choose a date above to see available times.', 'woocommerce-booking' ), $product_id ); ?>
					</div>
				<?php endif; ?>
		<?php } ?>
	</div>

<?php
if ( ! isset( $booking_settings['booking_enable_multiple_day'] ) || ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' !== $booking_settings['booking_enable_multiple_day'] ) ) {
	do_action( 'bkap_display_price_div', $product_id, $booking_settings );
}
do_action( 'bkap_before_add_to_cart_button', $booking_settings, $product_id );

?>
<div class="bkap-form-error"></div>
</div>
