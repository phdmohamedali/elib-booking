<?php
/**
 * Booking & Appointment for WooCommerce - Zoom Meeting Functions
 *
 * @since   5.2.0
 * @author  Tyche Softwares
 *
 * @package BKAP/Zoom Meetings Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zoom Option enable.
 *
 * @param int $product_id Product ID.
 *
 * @since 5.2.0
 */
function bkap_zoom_meeting_enable( $product_id = 0, $resource_id = 0 ) {

	if ( ! BKAP_License::enterprise_license() ) {
		return false;
	}

	/* Check if the product having the zoom meeting option enabled. */
	$check = false;
	if ( $product_id ) {
		$zoom_enable = get_post_meta( $product_id, '_bkap_zoom_meeting', true );
		if ( 'on' === $zoom_enable ) {

			if ( $resource_id > 0 ) {
				$zoom_host = get_post_meta( $resource_id, '_bkap_resource_meeting_host', true );
			} else {
				$zoom_host = get_post_meta( $product_id, '_bkap_zoom_meeting_host', true );
			}

			if ( '' !== $zoom_host ) {
				$check = true;
			}
		}
	} else {
		$check = true;
	}

	if ( ! $check ) {
		return false;
	}

	$zoom_type = bkap_zoom_connection_type();

	if ( '' === $zoom_type ) {
		return false;
	}

	$zoom_connection = bkap_zoom_connection();
	$response        = json_decode( $zoom_connection->bkap_list_users() );
	if ( ! empty( $response ) ) {
		if ( ! empty( $response->code ) ) {
			return false;
		}

		if ( http_response_code() === 200 ) {
			return true;
		}
	}

	return true;
}

/**
 * Zoom Meeting Label.
 *
 * @param int $product_id Product ID.
 *
 * @since 5.2.0
 */
function bkap_zoom_join_meeting_label( $product_id ) {
	return apply_filters( 'bkap_zoom_join_meeting_label', __( 'Zoom Meeting', 'woocommerce-booking' ), $product_id );
}

/**
 * Zoom Meeting Link Text.
 *
 * @param int $product_id Product ID.
 *
 * @since 5.2.0
 */
function bkap_zoom_join_meeting_text( $product_id ) {
	return apply_filters( 'bkap_zoom_join_meeting_text', __( 'Join Meeting', 'woocommerce-booking' ), $product_id );
}

/**
 * Zoom Meeting Link Text.
 *
 * @param array $product_ids Array of Product IDs.
 * @since 5.2.0
 */
function bkap_get_bookings_to_assign_zoom_meeting( $product_ids = array() ) {

	$zoom_booking_id = 0;
	$start_date      = date( 'YmdHis', current_time( 'timestamp' ) ); // phpcs:ignore
	$args            = array(
		'post_type'      => 'bkap_booking',
		'post_status'    => array( 'paid', 'pending-confirmation', 'confirmed' ),
		'posts_per_page' => -1,
		'meta_query'     => array( // phpcs:ignore
			'relation' => 'AND',
			array(
				'key'     => '_bkap_start',
				'value'   => $start_date,
				'compare' => '>=',
			),
			array(
				'key'     => '_bkap_product_id',
				'value'   => $product_ids,
				'compare' => 'IN',
			),
			array(
				'key'     => '_bkap_zoom_meeting_link',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$posts = get_posts( $args );

	return $posts;
}

/**
 * Zoom Redirect URL.
 *
 * @since 5.23.0
 */
function bkap_zoom_redirect_url() {

	$query_args     = array(
		'post_type' => 'bkap_booking',
		'page'      => 'woocommerce_booking_page',
		'action'    => 'calendar_sync_settings',
		'section'   => 'zoom_meeting',
	);
	$zoom_page_link = add_query_arg( $query_args, admin_url( 'edit.php' ) );

	return $zoom_page_link;
}

/**
 * Zoom Connection Type.
 *
 * @since 5.23.0
 */
function bkap_zoom_connection_type() {

	$type               = '';
	$zoom_api_key       = get_option( 'bkap_zoom_api_key' );
	$zoom_api_secret    = get_option( 'bkap_zoom_api_secret' );
	$zoom_client_id     = get_option( 'bkap_zoom_client_id', '' );
	$zoom_client_secret = get_option( 'bkap_zoom_client_secret', '' );

	if ( '' !== $zoom_client_id && '' !== $zoom_client_secret ) {
		$bkap_zoom_access_token = get_option( 'bkap_zoom_access_token', '' );
		if ( '' === $bkap_zoom_access_token ) {
			if ( empty( $zoom_api_key ) || empty( $zoom_api_secret ) ) {
				return '';
			} else {
				$type = 'jwt';
			}
		} else {
			$type = 'oauth';
		}
	} elseif ( empty( $zoom_api_key ) || empty( $zoom_api_secret ) ) {
		$type = '';
	} else {
		$type = 'jwt';
	}

	return $type;
}
