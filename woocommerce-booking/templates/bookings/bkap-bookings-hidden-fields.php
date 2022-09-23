<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Template for Hidden Fields for Bookings Box. This template shall be resued on Cart, Checkout and My Account Pages
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

do_action( 'bkap_print_hidden_fields', $product_id, $booking_settings );

$product_price = '';

$product_type = $product_obj->get_type();

if ( 'bundle' === $product_type ) {
	$bundled_items = $product_obj->get_bundled_items( 'view' );
	foreach ( $bundled_items as $bundle_key => $bundle_value ) {
		do_action( 'bkap_print_hidden_fields', $bundle_value->product_id, bkap_common::bkap_product_setting( $bundle_value->product_id ) );
	}
}

if ( isset( $product_type ) && 'simple' === $product_type ) {
	if ( $booking_settings != '' && ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] == 'on' ) && ( isset( $booking_settings['booking_purchase_without_date'] ) && $booking_settings['booking_purchase_without_date'] == 'on' ) ) {
		$variation_id  = 0;
		$product_price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type );
	}
}

if ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' == $booking_settings['booking_enable_time'] ) {
	print( '<input type="hidden" id="duration_time_slot" name="duration_time_slot" value="">' );
}

?>

	<input 
		type='hidden' 
		id='total_price_calculated' 
		name='total_price_calculated' 
		value='<?php echo $product_price; ?>' 
	>
	
	<input 
	   type='hidden' 
	   id='bkap_price_charged' 
	   name='bkap_price_charged' 
	   value='<?php echo $product_price; ?>' 
   >
   
	<input 
		type='hidden' 
		id='bkap_gf_options_total' 
		name='bkap_gf_options_total' 
		value='0' 
	>

	<input 
		type='hidden' 
		id='date_time_call' 
		name='date_time_call' 
		value='' 
	>

	<input 
		type="hidden" 
		id="wapbk_widget_search" 
		name="wapbk_widget_search" 
		value="<?php echo $hidden_dates['widget_search']; ?>"
	>
	
	<input type="hidden" 
		   id="wapbk_hidden_date" 
		   name="wapbk_hidden_date" 
		   value="<?php echo $hidden_dates['hidden_date']; ?>"
	>
   

	<input type="hidden" 
			id="wapbk_hidden_date_checkout" 
			name="wapbk_hidden_date_checkout" 
			value="<?php echo $hidden_dates['hidden_checkout']; ?>"
	>

	<input type="hidden" 
			id="wapbk_minimum_seach_date" 
			name="wapbk_minimum_seach_date" 
			value=" <?php echo $hidden_dates['min_search_checkout']; ?>"
	>    

	<input type="hidden" 
		   id="wapbk_diff_days" 
		   name="wapbk_diff_days"
	>
	
	<!-- <div id="ajax_img" name="ajax_img"> 
	   <img src="<?php echo plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif'; ?>"> 
	</div> -->
