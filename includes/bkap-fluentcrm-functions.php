<?php
/**
 * Booking & Appointment for WooCommerce - FluentCRM Functions
 *
 * @since   5.12.0
 * @author  Tyche Softwares
 *
 * @package BKAP/FluentCRM Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks if FluentCRM lite is installed & Active.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_lite_active() {

	if ( ! is_plugin_active( 'fluent-crm/fluent-crm.php' ) ) {
		return false;
	}

	return true;
}

/**
 * Checks if FluentCRM Pro is installed & Active.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_pro_active() {

	if ( ! is_plugin_active( 'fluentcampaign-pro/fluentcampaign-pro.php' ) ) {
		return false;
	}

	return true;
}

/**
 * Error Notice when FluentCRM is not active.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_inactive_notice() {

	$class   = 'notice notice-error';
	$message = __( 'FluentCRM plugin is not active. Please install and activate it.', 'woocommerce-booking'  );
	printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
}

/**
 * Prepare data to check contact.
 *
 * @param int   $booking_id Booking ID.
 * @param array $booking Booking Data.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_get_contact_data( $booking_id, $booking ) {

	$customer_data = array();

	// Defaults.
	$customer_data = array(
		'first_name' => '',
		'last_name'  => '',
		'email'      => '',
		'phone'      => '',
	);
	$order_id      = $booking['parent_id'];

	if ( false !== get_post_status( $order_id ) ) {
		$order                           = wc_get_order( $order_id );
		$customer_data['email']          = $order->get_billing_email();
		$customer_data['first_name']     = $order->get_billing_first_name();
		$customer_data['last_name']      = $order->get_billing_last_name();
		$customer_data['phone']          = $order->get_billing_phone();
		$customer_data['address_line_1'] = $order->get_billing_address_1();
		$customer_data['address_line_2'] = $order->get_billing_address_2();
		$customer_data['city']           = $order->get_billing_city();
		$customer_data['state']          = $order->get_billing_state();
		$customer_data['postal_code']    = $order->get_billing_postcode();
		$customer_data['country']        = $order->get_billing_country();
	}

	return $customer_data;
}

/**
 * FluentCRM Option enable.
 *
 * @param int $product_id Product ID.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_enable( $product_id = 0 ) {

	$check                = false;
	$fluentcrm_connection = bkap_fluentcrm_connection();
	$response             = $fluentcrm_connection->bkap_get_lists();

	if ( is_wp_error( $response ) ) {
		return false;
	}

	if ( ! isset( $response['lists'] ) ) {
		return false;
	}

	if ( $product_id ) {
		$result = bkap_fluentcrm_list_id( $product_id );
		return $result['status'];
	} else {
		$check = true;
	}

	return $check;
}

/**
 * Check active status and which list to take.
 *
 * @param int $product_id Product ID.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_list_id( $product_id ) {

	$result         = array(
		'status' => false,
		'level'  => '',
	);
	$fluentcrm_list = get_post_meta( $product_id, '_bkap_fluentcrm_list', true );
	if ( '' !== $fluentcrm_list ) {
		$check  = true;
		$result = array(
			'status' => (int) $fluentcrm_list,
			'level'  => 'product',
		);
	} else {
		$bkap_fluentcrm_connection = bkap_fluentcrm_global_settings();
		if ( '' !== $bkap_fluentcrm_connection['bkap_fluentcrm_list'] ) {
			$result = array(
				'status' => (int) $bkap_fluentcrm_connection['bkap_fluentcrm_list'],
				'level'  => 'global',
			);
		} else {
			return $result;
		}
	}

	return $result;
}

/**
 * Get Available Tags from FluentCRM and Getting ids of Booking tags.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_get_available_tags( $fluentcrm_connection ) {

	$tags     = array();
	$all_tags = $fluentcrm_connection->bkap_get_tags();
	$events   = $fluentcrm_connection->events;

	if ( isset( $all_tags['tags'] ) && isset( $all_tags['tags']['data'] ) ) {
		foreach ( $all_tags['tags']['data'] as $tag ) {
			foreach ( $events as $event ) {
				if ( $tag['slug'] === $fluentcrm_connection->bkap_get_slug( $event ) ) {
					$tags[ $event ] = $tag['id'];
				}
			}
		}
	}
	return $tags;
}

/**
 * Preparing additional data required for adding/updating the contact.
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_prepare_custom_fields_data( $booking_id, $booking_data ) {

	$product_id     = $booking_data['product_id'];
	$start_date     = date( 'Y-m-d', strtotime( $booking_data['start'] ) );
	$end_date       = date( 'Y-m-d', strtotime( $booking_data['end'] ) );
	$start_time     = date( 'Y-m-d H:i:s', strtotime( $booking_data['start'] ) );
	$end_time       = date( 'Y-m-d H:i:s', strtotime( $booking_data['end'] ) );
	$resource_title = ( $booking_data['resource_id'] ) ? get_the_title( $booking_data['resource_id'] ) : '';
	$persons        = bkap_persons_info( $booking_data['persons'], $product_id );
	$zoom_meeting   = get_post_meta( $booking_id, '_bkap_zoom_meeting_link', true );
	$zoom_data      = get_post_meta( $booking_id, '_bkap_zoom_meeting_data', true );
	$note_title     = sprintf( __( 'Booking #%s', 'woocommerce-booking' ), $booking_id );
	$post           = get_post( $product_id );
	$vendor_id      = $post->post_author;

	$labels            = bkap_booking_fields_label();
	$note_description  = $labels['start_date'] . ':' . $start_date . '<br>';
	$note_description .= $labels['end_date'] . ':' . $end_date . '<br>';
	$note_description .= $labels['time_slot'] . ':' . $start_time . ' - ' . $end_time . '<br>';
	$note_description .= __( 'Resource', 'woocommerce-booking' ) . ' : ' . $resource_title . '<br>';
	$note_description .= __( 'Persons', 'woocommerce-booking' ) . ' : ' . $persons . '<br>';
	$note_description = apply_filters( 'bkap_fluentcrm_note_description', $note_description );

	$data = array(
		'custom_values' => array(
			'bkap_booking_id'        => $booking_id,
			'bkap_product_id'        => $booking_data['product_id'],
			'bkap_start_date'        => $start_date,
			'bkap_end_date'          => $end_date,
			'bkap_start_time'        => $start_time,
			'bkap_end_time'          => $end_time,
			'bkap_resource_id'       => $resource_title,
			'bkap_persons'           => $persons,
			'bkap_timezone'          => $booking_data['timezone_name'],
			'bkap_vendor_id'         => $vendor_id,
			'bkap_duration'          => $booking_data['duration'],
			'bkap_order_id'          => $booking_data['parent_id'],
			'bkap_price'             => $booking_data['cost'],
			'bkap_qty'               => $booking_data['qty'],
			'bkap_order_item_id'     => $booking_data['order_item_id'],
			'bkap_variation_id'      => $booking_data['variation_id'],
			'bkap_gcal_event_uid'    => $booking_data['gcal_event_uid'],
			'bkap_zoom_meeting_link' => $zoom_meeting,
			'bkap_zoom_meeting_data' => $zoom_data,
		),
		'note'          => array(
			'title'       => $note_title,
			'description' => $note_description,
			'type'        => 'note',
			'created_by'  => get_current_user_id(),
		),
	);

	return $data;
}

/**
 * FluentCRM Global Settings
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_global_settings() {

	return get_option( 'bkap_fluentcrm_connection', array( 'bkap_fluentcrm_api_name' => '', 'bkap_fluentcrm_api_key' => '', 'bkap_fluentcrm_list' => '' ) );
}

/**
 * FluentCRM Custom Fields
 *
 * @since 5.12.0
 */
function bkap_fluentcrm_custom_fields() {

	return apply_filters(
		'bkap_fluentcrm_custom_fields',
		array(
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Booking ID',
				'slug'      => 'bkap_booking_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Product ID',
				'slug'      => 'bkap_product_id',
			),
			array(
				'field_key' => 'date',
				'type'      => 'date',
				'label'     => 'Start Date',
				'slug'      => 'bkap_start_date',
			),
			array(
				'field_key' => 'date',
				'type'      => 'date',
				'label'     => 'End Date',
				'slug'      => 'bkap_end_date',
			),
			array(
				'field_key' => 'date_time',
				'type'      => 'date_time',
				'label'     => 'Start Time',
				'slug'      => 'bkap_start_time',
			),
			array(
				'field_key' => 'date_time',
				'type'      => 'date_time',
				'label'     => 'End Time',
				'slug'      => 'bkap_end_time',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Duration',
				'slug'      => 'bkap_duration',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Resource',
				'slug'      => 'bkap_resource_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Persons',
				'slug'      => 'bkap_persons',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Timezone',
				'slug'      => 'bkap_timezone',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Variation ID',
				'slug'      => 'bkap_variation_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Order ID',
				'slug'      => 'bkap_order_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Price',
				'slug'      => 'bkap_price',
			),
			array(
				'field_key' => 'number',
				'type'      => 'number',
				'label'     => 'Quantity',
				'slug'      => 'bkap_qty',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Order Item ID',
				'slug'      => 'bkap_order_item_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Vendor ID',
				'slug'      => 'bkap_vendor_id',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Zoom Meeting Link',
				'slug'      => 'bkap_zoom_meeting_link',
			),
			array(
				'field_key' => 'text',
				'type'      => 'text',
				'label'     => 'Zoom Meeting Data',
				'slug'      => 'bkap_zoom_meeting_data',
			),
		)
	);
}
