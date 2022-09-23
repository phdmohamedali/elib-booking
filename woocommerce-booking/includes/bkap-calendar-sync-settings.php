<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Booking->Settings->Google Calendar Sync settings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @category Classes
 * @class    bkap_gcal_sync_settings
 */
class bkap_gcal_sync_settings {

	/**
	 * Callback for the gcal general settings section
	 */
	public static function bkap_gcal_sync_general_settings_callback() {}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Event Location
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_calendar_event_location_callback( $args ) {
		$google_calendar_location = get_option( 'bkap_calendar_event_location' );
		echo '<input type="text" name="bkap_calendar_event_location" id="bkap_calendar_event_location" value="' . esc_attr( $google_calendar_location ) . '" />';
		$html = '<label for="bkap_calendar_event_location"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Event Summary
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_calendar_event_summary_callback( $args ) {
		$gcal_summary = get_option( 'bkap_calendar_event_summary' );
		echo '<input id="bkap_calendar_event_summary" name="bkap_calendar_event_summary" value="' . esc_attr( $gcal_summary ) . '" size="90" name="gcal_summary" type="text"/>';
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Event Description
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_calendar_event_description_callback( $args ) {
		$gcal_description = get_option( 'bkap_calendar_event_description' );
		echo '<textarea id="bkap_calendar_event_description" name="bkap_calendar_event_description" cols="90" rows="4" name="gcal_description">' . esc_textarea( $gcal_description ) . '</textarea>';
		$html = '<label for="bkap_calendar_event_description"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Customer Add to Calendar button Settings section
	 *
	 * @since 2.6
	 */

	public static function bkap_calendar_sync_customer_settings_callback() {}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Show Add to Calendar button on Order received page
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_add_to_calendar_order_received_page_callback( $args ) {
		$add_to_calendar_order_received = '';
		if ( get_option( 'bkap_add_to_calendar_order_received_page' ) == 'on' ) {
			$add_to_calendar_order_received = 'checked';
		}

		echo '<input type="checkbox" name="bkap_add_to_calendar_order_received_page" id="bkap_add_to_calendar_order_received_page" class="day-checkbox" value="on" ' . $add_to_calendar_order_received . ' />';
		$html = '<label for="bkap_add_to_calendar_order_received_page"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Show Add to Calendar button in the Customer notification email
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_add_to_calendar_customer_email_callback( $args ) {
		$add_to_calendar_customer_email = '';
		if ( get_option( 'bkap_add_to_calendar_customer_email' ) == 'on' ) {
			$add_to_calendar_customer_email = 'checked';
		}

		echo '<input type="checkbox" name="bkap_add_to_calendar_customer_email" id="bkap_add_to_calendar_customer_email" class="day-checkbox" value="on" ' . $add_to_calendar_customer_email . ' />';
		$html = '<label for="bkap_add_to_calendar_customer_email"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Show Add to Calendar button on My account
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_add_to_calendar_my_account_page_callback( $args ) {
		$bkap_add_to_calendar_my_account_page = '';
		if ( get_option( 'bkap_add_to_calendar_my_account_page' ) == 'on' ) {
			$bkap_add_to_calendar_my_account_page = 'checked';
		}

		echo '<input type="checkbox" name="bkap_add_to_calendar_my_account_page" id="bkap_add_to_calendar_my_account_page" class="day-checkbox" value="on" ' . $bkap_add_to_calendar_my_account_page . ' />';
		$html = '<label for="bkap_add_to_calendar_my_account_page"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Open Calendar in Same Window
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_calendar_in_same_window_callback( $args ) {
		$google_calendar_same_window = '';
		if ( get_option( 'bkap_calendar_in_same_window' ) == 'on' ) {
			$google_calendar_same_window = 'checked';
		}

		echo '<input type="checkbox" name="bkap_calendar_in_same_window" id="bkap_calendar_in_same_window" class="day-checkbox" value="on" ' . $google_calendar_same_window . ' />';
		$html = '<label for="bkap_calendar_in_same_window"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Export Bookings header
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_calendar_sync_admin_settings_section_callback() {
		?>
		<h3><?php _e( 'Export Bookings to Google Calendar', 'woocommerce-booking' ); ?></h3>
		<?php
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Product Level Sync Notice
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_notice_for_use_product_gcalsync_callback() {

		$class   = 'notice notice-info bkap-notice-info';
		$link    = 'https://www.tychesoftwares.com/synchronize-booking-dates-andor-time-google-calendar/';
		$message = __( 'Product level Google Sync is more beneficial & fully automated then the Global Level Google Sync. Click <a href="' . $link . '" target="_blank">here</a> for detailed information.', 'woocommerce-booking' );

		printf( '<br/><div class="%1$s"><p style="font-size:medium;"><b>%2$s</b></p></div>', esc_attr( $class ), $message );

	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Allow Tour Operators for Google Calendar API Integration
	 *
	 * @param array $args - Setting Label Array
	 * @since 2.6
	 */
	public static function bkap_allow_tour_operator_gcal_api_callback( $args ) {
		$tour_operator_gcal_api_yes = '';
		$tour_operator_gcal_api_no  = 'selected';

		if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
			$tour_operator_gcal_api_yes = 'selected';
			$tour_operator_gcal_api_no  = '';
		} elseif ( 'no' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
			$tour_operator_gcal_api_no = 'selected';
		}

		echo '<select id="bkap_allow_tour_operator_gcal_api" name="bkap_allow_tour_operator_gcal_api" >
                <option value="yes" ' . $tour_operator_gcal_api_yes . '>YES</option>
                <option value="no" ' . $tour_operator_gcal_api_no . '>NO</option>
            </select>';

		$html = '<label for="bkap_allow_tour_operator_gcal_api"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Integration Mode
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_calendar_sync_integration_mode_callback( $args ) {

		$sync_oauth    = '';
		$sync_directly = '';
		$sync_manually = '';
		$sync_disable  = '';

		$mode = get_option( 'bkap_calendar_sync_integration_mode' );

		switch ( $mode ) {
			case 'oauth':
				$sync_oauth = 'checked';
				break;
			case 'directly':
				$sync_directly = 'checked';
				break;
			case 'manually':
				$sync_manually = 'checked';
				break;
			default:
				$sync_disable = 'checked';
				break;
		}

		echo '<input type="radio" name="bkap_calendar_sync_integration_mode" id="bkap_calendar_sync_integration_mode" value="oauth" ' . $sync_oauth . '/>' . __( 'OAuth Sync (Recommended)', 'woocommerce-booking' ) . '&nbsp;&nbsp;
			<input type="radio" name="bkap_calendar_sync_integration_mode" id="bkap_calendar_sync_integration_mode" value="directly" ' . $sync_directly . '/>' . __( 'Service Account Sync', 'woocommerce-booking' ) . '&nbsp;&nbsp;
            <input type="radio" name="bkap_calendar_sync_integration_mode" id="bkap_calendar_sync_integration_mode" value="manually" ' . $sync_manually . '/>' . __( 'Sync Manually', 'woocommerce-booking' ) . '&nbsp;&nbsp;
            <input type="radio" name="bkap_calendar_sync_integration_mode" id="bkap_calendar_sync_integration_mode" value="disabled" ' . $sync_disable . '/>' . __( 'Disabled', 'woocommerce-booking' );

		$html = '<label for="bkap_calendar_sync_integration_mode"> ' . $args[0] . '</label>';

		echo $html;
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				var isChecked = jQuery( "#bkap_calendar_sync_integration_mode:checked" ).val();				
				bkap_hide_sync_settings( isChecked );

				jQuery( "input[type=radio][id=bkap_calendar_sync_integration_mode]" ).change( function() {
					var isChecked = jQuery( this ).val();
					bkap_hide_sync_settings( isChecked );					
				});
			});

			function bkap_hide_sync_settings( isChecked ) {
				switch ( isChecked ) {
					case 'oauth':
						var hide_sync_rows = [ 'bkap_manual_sync', 'bkap_direct_sync' ];
						var show_sync_rows = [ 'bkap_oauth_sync', 'bkap_add_to_calendar_view_booking', 'bkap_sync_instructions' ];
						document.getElementById('bkap_sync_service_account_steps').style.display = 'none';
						document.getElementById('bkap_sync_oauth_steps').style.display = 'block';
						break;
					case 'directly':
						var hide_sync_rows = [ 'bkap_manual_sync', 'bkap_oauth_sync' ];
						var show_sync_rows = [ 'bkap_direct_sync', 'bkap_add_to_calendar_view_booking', 'bkap_sync_instructions' ];
						document.getElementById('bkap_sync_service_account_steps').style.display = 'block';
						document.getElementById('bkap_sync_oauth_steps').style.display = 'none';
						break;
					case 'manually':
						var hide_sync_rows = [ 'bkap_direct_sync', 'bkap_oauth_sync', 'bkap_add_to_calendar_view_booking', 'bkap_sync_instructions' ];
						var show_sync_rows = [ 'bkap_manual_sync' ];
						break;
					default:
						var hide_sync_rows = [ 'bkap_direct_sync', 'bkap_manual_sync', 'bkap_oauth_sync', 'bkap_add_to_calendar_view_booking', 'bkap_sync_instructions' ];
						var show_sync_rows = [];
						break;
				}
				hide_sync_rows.forEach( function( classnames ) {
					document.querySelectorAll( '.' + classnames ).forEach( function( el ) {
						el.style.display = 'none';
					});
				});
				if ( show_sync_rows.length > 0 ){
					show_sync_rows.forEach( function( classnames ) {
						document.querySelectorAll( '.' + classnames ).forEach( function( el ) {
							el.style.display = 'table-row';
						});
					});
				}

				let hide_fields = [ 'calendar_id', 'logout', 'connect_to_google' ];
				hide_fields.forEach( function( classnames ) {
					let hide_field_id = document.getElementById("bkap_calendar_oauth_integration["+ classnames +"]").style.display;
					if ( 'none' == hide_field_id  ) {						
						document.getElementById( classnames + "_row" ).style.display = 'none';
					}	
				});				
			}
		</script>
		<?php
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->OAuth Sync->All Fields
	 *
	 * @param array $args - Array contains class for row.
	 * @since 5.1.0
	 */
	public static function bkap_calendar_oauth_integration_callback( $args ) {

		$oauth_options   = bkap_get_oauth_google_calendar_options();
		$oauth_html_data = array();
		$product_id      = 0;
		$oauth_setting   = get_option( 'bkap_calendar_oauth_integration' );

		foreach ( $oauth_options as $key => $option ) {

			$option_id = 'bkap_calendar_oauth_integration[' . $option['id'] . ']';
			if ( isset( $oauth_setting[ $option['id'] ] ) ) {
				$option_value = $oauth_setting[ $option['id'] ];
			} else {
				$option_value = $option['default'];
			}
			$select_options_html = '';
			if ( 'select' === $option['type'] ) {
				foreach ( $option['options'] as $select_option_id => $select_option_label ) {
					$select_options_html .= '<option value="' . $select_option_id . '"' . selected( $option_value, $select_option_id, false ) . '>' . $select_option_label . '</option>';
				}
			}
			$style             = isset( $option['css'] ) ? ' style="' . $option['css'] . '"' : '';
			$css               = ( strpos( $style, 'display:none;' ) !== false ) ? $style : '';
			$custom_attributes = '';
			if ( isset( $option['custom_attributes'] ) ) {
				foreach ( $option['custom_attributes'] as $custom_attribute_key => $custom_attribute_value ) {
					$custom_attributes .= ' ' . $custom_attribute_key . '="' . $custom_attribute_value . '"';
				}
			}
			switch ( $option['type'] ) {
				case 'number':
				case 'text':
					$the_field = '<input' . $custom_attributes . $style .
						' type="' . $option['type'] .
						'" id="' . $option_id .
						'" name="' . $option_id .
						'" value="' . $option_value . '">' .
						( isset( $option['desc'] ) ? ' <em>' . $option['desc'] . '</em>' : '' );
					break;
				case 'select':
					$the_field = '<select' . $style . ' id="' . $option_id . '" name="' . $option_id . '">' . $select_options_html . '</select>';
					break;
				case 'button':
					if ( isset( $option['link'] ) ) {
						$the_field  = '<a' . $style . $custom_attributes . ' id="' . $option_id . '" name="' . $option_id . '">' . $option_value . '</a>';
						$the_field .= ( isset( $option['desc'] ) ? ' <em>' . $option['desc'] . '</em>' : '' );
					} else {
						$the_field = '<input' . $custom_attributes . $style .
							' type="' . $option['type'] .
							'" id="' . $option_id .
							'" name="' . $option_id .
							'" value="' . $option_value . '">' .
							( isset( $option['desc'] ) ? ' <em>' . $option['desc'] . '</em>' : '' );
					}

					break;
				case 'submit':
					$the_field = '<input type="hidden" name="bkap-google-calendar-action" value="' . $option_value . '"><input' . $custom_attributes . $style .
							' type="' . $option['type'] .
							'" id="' . $option_id .
							'" name="' . $option_id .
							'" value="' . $option_value . '">' .
							( isset( $option['desc'] ) ? ' <em>' . $option['desc'] . '</em>' : '' );

			}
			$data[] = array( $option['title'] . ( isset( $option['desc_tip'] ) ? wc_help_tip( $option['desc_tip'] ) : '' ), $the_field, $css, $option['id'] . '_row' );
		}

		foreach ( $data as $oauth_field ) {
			?>
			<tr class="bkap_oauth_sync" <?php esc_attr_e( $oauth_field[2] ); ?> id="<?php esc_attr_e( $oauth_field[3] ); ?>">
				<th ><?php echo $oauth_field[0]; // phpcs:ignore ?></th>
				<td><?php echo $oauth_field[1]; // phpcs:ignore ?></td>
			</tr>
			<?php
		}
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Instructions.
	 *
	 * @since 2.6
	 */
	public static function bkap_sync_calendar_instructions_callback() {

		?>
		<div id="bkap_sync_service_account_steps"><?php esc_html_e( 'To set up Key file name, Service account email address, and Calendar to be used, please click on "Show me how" link and carefully follow these steps:', 'woocommerce-booking' ); ?>
			<span class="description">
				<a href="#bkap-instructions" id="show_instructions" data-target="api-instructions" class="bkap-info_trigger" title="<?php esc_attr_e( 'Click to toggle instructions', 'woocommerce-booking' ); ?>"><?php esc_html_e( 'Show me how', 'woocommerce-booking' ); ?></a>
			</span>
			<div class="description bkap-info_target api-instructions" style="display: none;">
				<ul style="list-style-type:decimal;">
					<li><?php _e( 'Google Calendar API requires php V5.3+ and some php extensions.', 'woocommerce-booking' ); ?> </li>
					<li><?php printf( __( 'Go to Google APIs console by clicking %s. Login to your Google account if you are not already logged in.', 'woocommerce-booking' ), '<a href="https://code.google.com/apis/console/" target="_blank">https://code.google.com/apis/console/</a>' ); ?></li>
					<li><?php _e( 'Create a new project. Click on \'Create Project\' button. Name the project "Bookings" (or use your chosen name instead).', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click on API Manager from left side pane.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click "Calendar API" under Google Apps APIs and click on Enable button.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Go to "Credentials" menu in the left side pane and click on "Create Credentials" dropdown.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click on "OAuth client ID" option. Then click on Configure consent screen.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Enter a Product Name, e.g. Bookings and Appointments, inside the opening pop-up. Click Save.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Select "Web Application" option, enter the Web client name and create the client ID.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click on New Credentials dropdown and select "Service account key".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click "Service account" and select "New service account" and enter the name. Set the Role to "Owner".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Now select key type as "P12" and create the service account.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'A file with extension .p12 will be downloaded.', 'woocommerce-booking' ); ?></li>
					<li><?php printf( __( 'Using your FTP client program, copy this key file to folder: %s . This file is required as you will grant access to your Google Calendar account even if you are not online. So this file serves as a proof of your consent to access to your Google calendar account. Note: This file cannot be uploaded in any other way. If you do not have FTP access, ask the website admin to do it for you.', 'woocommerce-booking' ), plugin_dir_path( __FILE__ ) . 'gcal/key/' ); ?></li>
					<li><?php _e( 'Enter the name of the key file to "Key file name" field of Booking Settings. Exclude the extention .p12.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Copy "Email address" setting from Manage service account of Google apis console and paste it to "Service account email address" setting of Booking.', 'woocommerce-booking' ); ?></li>
					<li><?php printf( __( 'Open your Google Calendar by clicking this link: %s', 'woocommerce-booking' ), '<a href="https://www.google.com/calendar/render" target="_blank">https://www.google.com/calendar/render</a>' ); ?></li>
					<li><?php _e( 'Create a new Calendar by selecting "my Calendars > Create new calendar" on left side pane. <b>Try NOT to use your primary calendar.</b>', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Give a name to the new calendar, e.g. Bookings calendar. <b>Check that Calendar Time Zone setting matches with time zone setting of your WordPress website.</b> Otherwise there will be a time shift.', 'woocommerce-booking' ); ?></li>		
					<li><?php _e( 'Paste already copied "Email address" setting from Manage service account of Google apis console to "Person" field under "Share with specific person".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Set "Permission Settings" of this person as "Make changes to events".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click "Add Person".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click "Create Calendar".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Select the created calendar and click "Calendar settings".', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Copy "Calendar ID" value on Calendar Address row.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Paste this value to "Calendar to be used" field of Booking Settings.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Select the desired Integration mode: Sync Automatically or Sync Manually.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Click "Save Settings" on Booking settings.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'After these stages, you have set up Google Calendar API. To test the connection, click the "Test Connection" link.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'If you get a success message, you should see a test event inserted to the Google Calendar and you are ready to go. If you get an error message, double check your settings.', 'woocommerce-booking' ); ?></li>
				</ul>
			</div>
		</div>

		<div id="bkap_sync_oauth_steps"><?php _e( 'To set up <b>Client ID</b> and <b>Client Secret</b> please click on "Show me how" link and carefully follow these steps:', 'woocommerce-booking' ); // phpcs:ignore ?>
			<span class="description" >
				<a href="#bkap-instructions" id="show_instructions" data-target="auth-instructions" class="bkap-info_trigger" title="<?php esc_attr_e( 'Click to toggle instructions', 'woocommerce-booking' ); ?>"><?php esc_html_e( 'Show me how', 'woocommerce-booking' ); ?></a>
			</span>
			<div class="description bkap-info_target auth-instructions" style="display: none;">
				<ul style="list-style-type:decimal;">
					<li><?php printf( __( 'Go to the <b>%s</b> and select a project, or create a new one. Login to your Google account if you are not already logged in.', 'woocommerce-booking' ), '<a href="https://code.google.com/apis/console/" target="_blank">Google Developers Console</a>' ); // phpcs:ignore?></li>
					<li><?php esc_html_e( 'If creating a new project, give the Project name e.g \'My Booking Project\' and click on the Create button.', 'woocommerce-booking' ); ?></li>
					<li><?php _e( 'Once the project is created, the Calendar API needs to be enabled. To do so, click on <b>ENABLE API AND SERVICES</b> link and search for <b>Google Calendar API</b>, and enable it by clicking the ENABLE button.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'On the left, click <b>Credentials</b>. If this is your first time creating a client ID, you\'ll be prompted to configure the consent screen. Click on <b>Configure Consent Screen</b>.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Go to the <b>OAuth consent screen</b>. Select User Type as <b>Internal</b> and click on the CREATE button. After that, set the <b>Application name</b> and click on the Create button.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Go back to the <b>Credentials</b> tab, click <b>Create credentials</b>, then select <b>OAuth client ID</b>.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Select <b>Web application</b> under Application type and provide the necessary information to create your project\'s credentials.', 'woocommerce-booking' ); // phpcs:ignore ?></li>
					<li><?php _e( 'For <b>Authorized redirect URIs</b> enter the Redirect URI (Can be found in Booking menu > Settings > Google Calendar Sync). Then click Create button.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'On the dialog that appears, you\'ll see your <b>Client ID</b> and <b>Client Secret</b>. Fill the details in below fields and click on Save Settings.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Once the Successful Connection to Google, <b>Calendar to be used</b> option will appear. Here select the calendar to which the event should get created for the booking.', 'woocommerce-booking' ); // phpcs:ignore ?></li>
				</ul>
			</div>
		</div>
		<script type="text/javascript">
			function toggle_target (e) {
				if ( e && e.preventDefault ) { 
					e.preventDefault();
				}
				if ( e && e.stopPropagation ) {
					e.stopPropagation();
				}

				var sync_instruction_target = jQuery( this ).data( 'target' );
				var target = jQuery( ".bkap-info_target." + sync_instruction_target );
				if ( !target.length ) {
					return false;
				}

				if ( target.is( ":visible" ) ) {
					target.hide( "fast" );
				} else {
					target.show( "fast" );
				}

				return false;
			}
			jQuery( function() {
				jQuery( document ).on( 'click', '.bkap-info_trigger', toggle_target );
			});
		</script>
		<?php
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Key File Name.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_calendar_key_file_name_callback( $args ) {
		$gcal_key_file_arr = get_option( 'bkap_calendar_details_1' );
		if ( isset( $gcal_key_file_arr['bkap_calendar_key_file_name'] ) ) {
			$gcal_key_file = $gcal_key_file_arr['bkap_calendar_key_file_name'];
		} else {
			$gcal_key_file = '';
		}

		$label = __( '<br>Enter key file name here without extention, e.g. ab12345678901234567890-privatekey.', 'woocommerce-booking' );
		echo '<input id="bkap_calendar_details_1[bkap_calendar_key_file_name]" name= "bkap_calendar_details_1[bkap_calendar_key_file_name]" value="' . esc_attr( $gcal_key_file ) . '" size="90" name="gcal_key_file" type="text" />';
		$html = '<label for="bkap_calendar_key_file_name">' . $label . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Service Account Email Address.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_calendar_service_acc_email_address_callback( $args ) {
		$gcal_service_account_arr = get_option( 'bkap_calendar_details_1' );
		if ( isset( $gcal_service_account_arr['bkap_calendar_service_acc_email_address'] ) ) {
			$gcal_service_account = $gcal_service_account_arr['bkap_calendar_service_acc_email_address'];
		} else {
			$gcal_service_account = '';
		}

		$label = __( '<br>Enter Service account email address here, e.g. 1234567890@developer.gserviceaccount.com.', 'woocommerce-booking' );

		echo '<input id="bkap_calendar_details_1[bkap_calendar_service_acc_email_address]" name="bkap_calendar_details_1[bkap_calendar_service_acc_email_address]" value="' . esc_attr( $gcal_service_account ) . '" size="90" name="gcal_service_account" type="text"/>';
		$html = '<label for="bkap_calendar_service_acc_email_address"> ' . $label . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Calendar to be used.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_calendar_id_callback( $args ) {
		$gcal_selected_calendar_arr = get_option( 'bkap_calendar_details_1' );
		if ( isset( $gcal_selected_calendar_arr['bkap_calendar_id'] ) ) {
			$gcal_selected_calendar = $gcal_selected_calendar_arr['bkap_calendar_id'];
		} else {
			$gcal_selected_calendar = '';
		}

		$label = __( '<br>Enter the ID of the calendar in which your bookings will be saved, e.g. abcdefg1234567890@group.calendar.google.com.', 'woocommerce-booking' );
		echo '<input id="bkap_calendar_details_1[bkap_calendar_id]" name="bkap_calendar_details_1[bkap_calendar_id]" value="' . esc_attr( $gcal_selected_calendar ) . '" size="90" name="gcal_selected_calendar" type="text" />';
		$html = '<label for="bkap_calendar_id"> ' . $label . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Test Connection.
	 *
	 * @since 2.6
	 */
	public static function bkap_calendar_test_connection_callback() {

		$user_id = get_current_user_id();

		echo "<script type='text/javascript'>
                jQuery( document ).on( 'click', '#test_connection', function( e ) {
                    e.preventDefault();
                    var data = {
                        gcal_api_test_result: '',
                        gcal_api_pre_test: '',
                        gcal_api_test: 1,
                        user_id: " . $user_id . ",
                        product_id: 0,
                        action: 'display_nag'
                    };
                    jQuery( '#test_connection_ajax_loader' ).show();
                    jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
                        jQuery( '#test_connection_message' ).html( response );
                        jQuery( '#test_connection_ajax_loader' ).hide();
                    });
            
            
            });
            </script>";
		print "<a href='edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings' id='test_connection'>" . __( 'Test Connection', 'woocommerce-booking' ) . "</a>
                <img src='" . plugins_url() . "/woocommerce-booking/assets/images/ajax-loader.gif' id='test_connection_ajax_loader'>";
		print "<div id='test_connection_message'></div>";
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Directly->Show Add to Calendar button on View Bookings page.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_admin_add_to_calendar_view_booking_callback( $args ) {
		$bkap_admin_add_to_calendar_view_bookings = '';
		if ( get_option( 'bkap_admin_add_to_calendar_view_booking' ) == 'on' ) {
			$bkap_admin_add_to_calendar_view_bookings = 'checked';
		}
		$label = __( 'Show "Add to Calendar" button on the Booking -> View Bookings page.<br><i>Note: This button can be used to export the already placed orders with future bookings from the current date to the calendar used above.</i>', 'woocommerce-booking' );
		echo '<input type="checkbox" name="bkap_admin_add_to_calendar_view_booking" id="bkap_admin_add_to_calendar_view_booking" value="on" ' . $bkap_admin_add_to_calendar_view_bookings . ' />';
		$html = '<label for="bkap_admin_add_to_calendar_view_booking"> ' . $label . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Sync Manually->Show Add to Calendar button in New Order email notification.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_admin_add_to_calendar_email_notification_callback( $args ) {
		$bkap_admin_add_to_calendar_email_notification = '';
		if ( get_option( 'bkap_admin_add_to_calendar_email_notification' ) == 'on' || false === get_option( 'bkap_admin_add_to_calendar_email_notification' ) ) {
			$bkap_admin_add_to_calendar_email_notification = 'checked';
		}

		$label = __( 'Show "Add to Calendar" button in the New Order email notification.', 'woocommerce-booking' );
		echo '<input type="checkbox" name="bkap_admin_add_to_calendar_email_notification" id="bkap_admin_add_to_calendar_email_notification" value="on" ' . $bkap_admin_add_to_calendar_email_notification . ' />';
		$html = '<label for="bkap_admin_add_to_calendar_email_notification"> ' . $label . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Run Automated Cron after X minutes.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_cron_time_duration_callback( $args ) {
		$bkap_cron_time_duration = '';
		if ( get_option( 'bkap_cron_time_duration' ) == '' ) {
			$bkap_cron_time_duration = '1440';
		} else {
			$bkap_cron_time_duration = get_option( 'bkap_cron_time_duration' );
		}
		echo '<input type="number" name="bkap_cron_time_duration" id="bkap_cron_time_duration" value="' . esc_attr( $bkap_cron_time_duration ) . '" />';
		$html = '<label for="bkap_cron_time_duration"> ' . $args[0] . '</label>';
		echo $html;
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Import Events Note.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_calendar_import_ics_feeds_section_callback() {
		echo 'Events will be imported using the ICS Feed URL. Each event will create a new WooCommerce Order once that event gets mapped to the product successfully. The event\'s date & time will be set as the item\'s Booking Date & Time. <br>Lockout will be updated for the product for the set Booking Date & Time.';
	}

		/**
		 * Callback function for Booking->Settings->Google Calendar Sync->Import Events->Instructions.
		 *
		 * @param array $args - Setting Label Array.
		 * @since 2.6
		 */
	public static function bkap_ics_feed_url_instructions_callback() {
		echo 'To set up Import events using ICS feed URLs, please click on "Show me how" link and carefully follow these steps:
                <span class="ics-feed-description" ><a href="#bkap-ics-feed-instructions" id="show_instructions" data-target="api-instructions" class="bkap_ics_feed-info_trigger" title="' . __( 'Click to toggle instructions', 'woocommerce-booking' ) . '">' . __( 'Show me how', 'woocommerce-booking' ) . '</a></span>';
		?>

		<div class="ics-feed-description bkap_ics_feed-info_target api-instructions" style="display: none;">
			<ul style="list-style-type:decimal;">
				<li><?php printf( __( 'Open your Google Calendar by clicking this link: %s', 'woocommerce-booking' ), '<a href="https://www.google.com/calendar/render" target="_blank">https://www.google.com/calendar/render</a>' ); ?></li>
				<li><?php _e( 'Select the calendar to be imported and click "Calendar settings".', 'woocommerce-booking' ); ?></li>
				<li><?php _e( 'Click on "ICAL" button in Calendar Address option. Please note that you need to select the Private Calendar Address "ICAL" if your calendar is not public.', 'woocommerce-booking' ); ?></li>		
				<li><?php _e( 'Copy the basic.ics file URL.', 'woocommerce-booking' ); ?></li>
				<li><?php _e( 'Paste this link in the text box under Google Calendar Sync tab->Import Events->iCalendar/.ics Feed URL.', 'woocommerce-booking' ); ?></li>
				<li><?php _e( 'Save the URL.', 'woocommerce-booking' ); ?></li>
				<li><?php _e( 'Click on "Import Events" button to import the events from the calendar.', 'woocommerce-booking' ); ?></li>
				<li><?php _e( 'You can import multiple calendars by using ICS feeds. Add them using the Add New ICS Feed URL button.', 'woocommerce-booking' ); ?></li>
			</ul>
		</div>
		<script type="text/javascript">
			function bkap_ics_feed_toggle_target (e) {
				if ( e && e.preventDefault ) { 
					e.preventDefault();
				}
				if ( e && e.stopPropagation ) {
					e.stopPropagation();
				}
				var target = jQuery( ".bkap_ics_feed-info_target.api-instructions" );
				if ( !target.length ) {
					return false;
				}

				if ( target.is( ":visible" ) ) {
					target.hide( "fast" );
				} else {
					target.show( "fast" );
				}

				return false;
			}
			jQuery( function () { 
				jQuery(document).on( "click", ".bkap_ics_feed-info_trigger", bkap_ics_feed_toggle_target );
			});
		</script>
		<?php
	}

	/**
	 * Callback function for Booking->Settings->Google Calendar Sync->Import Events->iCalendar/.ics Feed URL.
	 *
	 * @param array $args - Setting Label Array.
	 * @since 2.6
	 */
	public static function bkap_ics_feed_url_callback( $args ) {
		echo '<table id="bkap_ics_url_list">';
		$ics_feed_urls = get_option( 'bkap_ics_feed_urls' );
		if ( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
			$ics_feed_urls = array();
		}

		if ( count( $ics_feed_urls ) > 0 ) {
			foreach ( $ics_feed_urls as $key => $value ) {
				echo "<tr id='$key'>
                            <td class='ics_feed_url'>
                                <input type='text' id='bkap_ics_fee_url_$key' size='60' value='" . esc_attr( $value ) . "'>
                            </td>
                            <td class='ics_feed_url'>
                                <input type='button' value='Save' id='save_ics_url' class='save_button' name='$key' disabled='disabled'>
                            </td>
                            <td class='ics_feed_url'>
                                <input type='button' class='save_button' id='$key' name='import_ics' value='Import Events'>
                            </td>
                            <td class='ics_feed_url'>
                                <input type='button' class='save_button' id='$key' value='Delete' name='delete_ics_feed'>
                            </td>
                            <td class='ics_feed_url'>
                                <div id='import_event_message' style='display:none;'>
                                    <img src='" . plugins_url() . "/woocommerce-booking/assets/images/ajax-loader.gif'>
                                </div>
                                <div id='success_message' ></div>
                            </td>
                        </tr>";
			}
		} else {
			echo "<tr id='0' >
                        <td class='ics_feed_url'>
                            <input type='text' id='bkap_ics_fee_url_0' size='60' >
                        </td>
                        <td class='ics_feed_url'>
                            <input type='button' value='Save' id='save_ics_url' class='save_button' name='0' >
                        </td>
                        <td class='ics_feed_url'>
                            <input type='button' class='save_button' id='0' name='import_ics' value='Import Events' disabled='disabled'>
                        </td>
                        <td class='ics_feed_url'>
                            <input type='button' class='save_button' id='0' name='delete_ics_feed' value='Delete' disabled='disabled'>
                        </td>
                        <td class='ics_feed_url'>
                            <div id='import_event_message' style='display:none;'>
                                <img src='" . plugins_url() . "/woocommerce-booking/assets/images/ajax-loader.gif'>
                            </div>
                            <div id='success_message' ></div>
                        </td>
                    </tr>";
		}
		echo '</table>';

		echo "<input type='button' class='save_button' id='add_new_ics_feed' name='add_new_ics_feed' value='Add New ICS feed URL'>";
		echo "<script type='text/javascript'>
			jQuery( document ).ready( function() {
				
				jQuery( '#add_new_ics_feed' ).on( 'click', function() {
					var rowCount = parseInt( jQuery( '#bkap_ics_url_list tr:last' ).attr( 'id' ) )  + 1;
					jQuery( '#bkap_ics_url_list' ).append( '<tr id=\'' + rowCount + '\'><td class=\'ics_feed_url\'><input type=\'text\' id=\'bkap_ics_fee_url_' + rowCount + '\' size=\'60\' ></td><td class=\'ics_feed_url\'><input type=\'button\' value=\'Save\' id=\'save_ics_url\' class=\'save_button\' name=\'' + rowCount + '\'></td><td class=\'ics_feed_url\'><input type=\'button\' class=\'save_button\' id=\'' + rowCount + '\' name=\'import_ics\' value=\'Import Events\' disabled=\'disabled\'></td><td class=\'ics_feed_url\'><input type=\'button\' class=\'save_button\' id=\'' + rowCount + '\' value=\'Delete\' disabled=\'disabled\'  name=\'delete_ics_feed\' ></td><td class=\'ics_feed_url\'><div id=\'import_event_message\' style=\'display:none;\'><img src=\'" . plugins_url() . "/woocommerce-booking/assets/images/ajax-loader.gif\'></div><div id=\'success_message\' ></div></td></tr>' );
				});
			
				jQuery( document ).on( 'click', '#save_ics_url', function() {
					var key = jQuery( this ).attr( 'name' );
					var data = {
						ics_url: jQuery( '#bkap_ics_fee_url_' + key ).val(),
						action: 'bkap_save_ics_url_feed'
					};
					jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
						if( response == 'yes' ) {
							jQuery( 'input[name=\'' + key + '\']' ).attr( 'disabled','disabled' );
							jQuery( 'input[id=\'' + key + '\']' ).removeAttr( 'disabled' );
						} 
					});
				});
				
				jQuery( document ).on( 'click', 'input[type=\'button\'][name=\'delete_ics_feed\']', function() {
					var key = jQuery( this ).attr( 'id' );
					var data = {
						ics_feed_key: key,
						action: 'bkap_delete_ics_url_feed'
					};
					jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
						if( response == 'yes' ) {
							jQuery( 'table#bkap_ics_url_list tr#' + key ).remove();
						} 
					});
				});
				
				jQuery( document ).on( 'click', 'input[type=\'button\'][name=\'import_ics\']', function() {
					jQuery( '#import_event_message' ).show();
					var key = jQuery( this ).attr( 'id' );
					var data = {
						ics_feed_key: key,
						action: 'bkap_import_events'
					};
					jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
						jQuery( '#import_event_message' ).hide();
						jQuery( '#success_message' ).html( response );  
						jQuery( '#success_message' ).fadeIn();
						setTimeout( function() {
							jQuery( '#success_message' ).fadeOut();
						},3000 );
					});
				});
			});
		</script>";
	}

	/**
	 * Validation callback function for Booking->Settings->Google Calendar Sync->Event Summary.
	 *
	 * @param string $input - Event Summary.
	 * @since 2.6.3.2
	 */
	public static function bkap_event_summary_validate_callback( $input ) {
		$new_input = $input;

		if ( ! isset( $input ) || ( isset( $input ) && '' == $input ) ) {
			$message = __( 'Leaving the Event Summary (name) blank will result in a blank event summary in your Google Calendar event, thereby making it difficult for you to identify your booking in the Calendar.', 'woocommerce-booking' );
			add_settings_error( 'bkap_calendar_event_summary', 'page_url_error', $message, 'updated' );
		}

		return $new_input;
	}

	/**
	 * Validation callback function for Booking->Settings->Google Calendar Sync->Event Description.
	 *
	 * @param string $input - Event Description.
	 * @since 2.6.3.2
	 */
	public static function bkap_event_description_validate_callback( $input ) {
		$new_input = $input;

		if ( ! isset( $input ) || ( isset( $input ) && '' == $input ) ) {
			$message = __( 'Leaving the Event Description blank will result in a blank event description in your Google Calendar event, thereby making it difficult for you to identify your booking in the Calendar.', 'woocommerce-booking' );
			add_settings_error( 'bkap_calendar_event_description', 'page_url_error', $message, 'updated' );
		}

		return $new_input;
	}

}
?>
