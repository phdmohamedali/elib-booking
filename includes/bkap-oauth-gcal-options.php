<?php
/**
 * Booking & Appointment for WooCommerce - OAuth Google Calendar Options
 *
 * @version 5.1.0
 * @since   5.1.0
 * @author  Tyche Softwares
 *
 * @package BKAP/Google-Calendar-Sync-Options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bkap_oauth_google_calendar_options' ) ) {
	/**
	 * This function is to adding the options for OAuth Integration.
	 *
	 * @param int $product_id Product ID.
	 * @param int $user_id User ID.
	 * @version 5.1.0
	 * @since   5.1.0
	 */
	function bkap_get_oauth_google_calendar_options( $product_id = 0, $user_id = 1 ) {

		$copy_clipboard_str = __( 'Copied!', 'woocommerce-booking' );
		$copy_clipboard     = '<a href="javascript:void(0)" style="border: 1px solid #eee;padding: 4px;" id="bkap_copy_redirect_uri" data-selector-to-copy="#bkap-auth-redirect-uri" data-tip=' . $copy_clipboard_str . ' class="dashicons dashicons-admin-page bkap-oauth-rurl-copy-to-clipboard"></a><span id="bkap_redirect_uri_copied"></span>';
		$bkap_oauth_gcal    = new BKAP_OAuth_Google_Calendar( $product_id, $user_id );
		$integration        = $bkap_oauth_gcal->bkap_is_integration_active();
		$redirect_uri       = $bkap_oauth_gcal->bkap_get_redirect_uri();
		$google_auth_url    = $bkap_oauth_gcal->bkap_get_google_auth_url();
		$calendar_list      = $bkap_oauth_gcal->bkap_get_calendar_list_options();
		$hide_logout        = 'display:none;';
		$hide_calendar      = 'display:none;';
		$hide_connect       = '';
		$successful_msg     = '';
		$instruction        = __( '<br>To find your Client ID and Client Secret please follow the <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/google-calendar-sync-via-oauth/" target="_blank">instructions.</a><br/>', 'woocommerce-booking' );

		if ( $product_id ) {
			$oauth_settings   = get_post_meta( $product_id, '_bkap_calendar_oauth_integration', true );
			$product_edit_url = get_edit_post_link( $product_id );
			$redirect_args    = array( 'bkap_logout' => $product_id );
			$logout_url       = add_query_arg( $redirect_args, $product_edit_url );
		} else {
			$oauth_settings = get_option( 'bkap_calendar_oauth_integration', null );
			$redirect_args  = array(
				'page'        => 'woocommerce_booking_page',
				'action'      => 'calendar_sync_settings',
				'post_type'   => 'bkap_booking',
				'bkap_logout' => 0,
			);
			$logout_url     = add_query_arg( $redirect_args, admin_url( '/edit.php?' ) );
			$instruction    = '';
		}

		if ( empty( $oauth_settings ) ) {
			$hide_connect = 'display:none;';
		} else {
			if ( isset( $oauth_settings['client_id'] ) && '' !== $oauth_settings['client_id'] && isset( $oauth_settings['client_secret'] ) && '' !== $oauth_settings['client_secret'] ) {
				$hide_connect = ( $integration ) ? 'display:none;' : '';
			} else {
				$hide_connect = 'display:none;';
			}
		}

		$id_secret = array();
		if ( $integration ) {
			$hide_logout    = '';
			$successful_msg = __( 'Successfully authenticated.', 'woocommerce-booking' );
			$hide_calendar  = '';
			$id_secret      = array( 'readonly' => 'readonly' );
		}

		$options = array(
			array(
				'id'                => 'client_id',
				'title'             => __( 'Client ID', 'woocommerce-booking' ),
				'type'              => 'text',
				'default'           => '',
				'css'               => 'width:100%;',
				'custom_attributes' => $id_secret,
			),
			array(
				'id'                => 'client_secret',
				'title'             => __( 'Client Secret', 'woocommerce-booking' ),
				'type'              => 'text',
				'default'           => '',
				'css'               => 'width:100%;',
				'custom_attributes' => $id_secret,
				'desc'              => $instruction,
			),
			array(
				'id'                => 'redirect_uri',
				'title'             => __( 'Redirect URI', 'woocommerce-booking' ),
				'type'              => 'text',
				'default'           => $redirect_uri,
				'css'               => 'width:89%;',
				'desc'              => $copy_clipboard,
				'custom_attributes' => array(
					'readonly' => 'readonly',
					'class'    => 'bkap-auth-redirect-uri',
				),
			),
			array(
				'id'      => 'calendar_id',
				'title'   => __( 'Calendar to be used', 'woocommerce-booking' ),
				'type'    => 'select',
				'default' => 'text',
				'css'     => $hide_calendar,
				'options' => $calendar_list,
			),
			array(
				'title'             => '',
				'value'             => __( 'Connect to Google', 'woocommerce-booking' ),
				'default'           => __( 'Connect to Google', 'woocommerce-booking' ),
				'id'                => 'connect_to_google',
				'type'              => 'button',
				'css'               => $hide_connect,
				'link'              => 'yes',
				'custom_attributes' => array(
					'href'  => $google_auth_url,
					'class' => 'button-primary',
				),
			),
			array(
				'title'             => '',
				'value'             => __( 'Logout', 'woocommerce-booking' ),
				'default'           => __( 'Logout', 'woocommerce-booking' ),
				'id'                => 'logout',
				'type'              => 'button',
				'css'               => $hide_logout,
				'link'              => 'yes',
				'desc'              => $successful_msg,
				'custom_attributes' => array(
					'href'  => $logout_url,
					'class' => 'button-secondary',
				),
			),
		);

		return apply_filters( 'bkap_oauth_google_calendar_options', $options );
	}
}
