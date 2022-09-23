<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Manual Bookings using Bookings->Create Booking
 *
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @category Classes
 * @class product_gcal_settings
 */

class product_gcal_settings {

	/**
	 * Default constructor
	 *
	 * @since 4.0.0
	 */

	public function __construct() {

		// add the GCal tab in the Booking meta box
		add_action( 'bkap_add_tabs', array( &$this, 'gcal_tab' ), 11, 1 );
		// add collapse menus tab
		add_action( 'bkap_add_tabs', array( &$this, 'collapse_tab' ), 50, 1 );
		// add fields in the GCal tab in the Booking meta box
		add_action( 'bkap_after_listing_enabled', array( &$this, 'bkap_gcal_show_field_settings' ), 11, 2 );
		// Save the product settings for variable blocks
		// add_filter( 'bkap_save_product_settings', array( &$this, 'bkap_gcal_product_settings_save' ), 11, 2 );
	}

	/**
	 * This function adds the Google Calender Sync Settings menu
	 * to the Booking meta box on the Add/Edit Product Page.
	 *
	 * @param integer $product_id - Product ID
	 *
	 * @hook bkap_add_tabs
	 * @since 4.0.0
	 */
	function gcal_tab( $product_id ) {
		?>
		<li class="tstab-tab" data-link="gcal_tab">
			<a id="integrations_settings" class="bkap_tab"><i class="fa fa-sync-alt" aria-hidden="true"></i><?php _e( 'Integrations', 'woocommerce-booking' ); ?></a>
		</li>
		<?php
	}

	/**
	 * This function adds a Collapse tabs menu
	 * to the Booking menu on the Add/Edit Product Page.
	 *
	 * @param integer $product_id - Product ID
	 *
	 * @hook bkap_add_tabs
	 * @since 4.0.0
	 */

	function collapse_tab( $product_id ) {
		?>
		<span id="bkap_collapse"><span class="dashicons dashicons-admin-collapse" style="margin-right: 5px;"></span><?php _e( 'Collapse Tabs', 'woocommerce-booking' ); ?></span>
		<?php
	}

	/**
	 * Displays the settings for Google Calendar Sync
	 * in the Booking meta box.
	 *
	 * @param integer $product_id - Product ID.
	 *
	 * @hook bkap_after_listing_enabled
	 * @since 2.6.3
	 */
	public function bkap_gcal_show_field_settings( $product_id, $booking_settings ) {

		$user_id  = get_current_user_id();
		$_product = wc_get_product( $product_id );
		if ( $_product == false ) {
			$product_type = '';
		} else {
			$product_type = $_product->get_type();
		}

		$gcal_disabled = '';
		$gcal_msg      = 'none';
		if ( 'grouped' === $product_type ) {
			$gcal_disabled = 'disabled';
			$gcal_msg      = 'block';
		}
		$post_type = get_post_type( $product_id );
		?>
		<div id="gcal_tab" class="tstab-content" style="position: relative; display: none;">
			<button type="button" class="bkap-integrations-accordion"><b><?php esc_html_e( 'Google Calendar Sync', 'woocommerce-booking' ); ?></b></button>
			<div class="bkap_google_sync_settings_content bkap_integrations_panel">
				
				<div id="bkap_gcal_msg" class="bkap-gcal-info" style="display:<?php echo $gcal_msg; ?>;" >
					<?php esc_html_e( 'Google Calendar Sync cannot be set up for a Grouped Product. Please set up the sync settings for individual child products.', 'woocommerce-booking' ); ?>
				</div>
				<fieldset id="bkap_gcal_fields" <?php echo $gcal_disabled; ?> >
					<div id="bkap_gcal_export_section">
						<h2>
							<strong><?php esc_html_e( 'Export Bookings to Google Calendar', 'woocommerce-booking' ); ?></strong>
						</h2>
						<table class='form-table bkap-form-table'>
							<tr style="max-width:20%;">
								<?php
								$sync_directly = '';
								$sync_oauth    = '';
								$sync_disable  = 'checked';
								$oauth_options = bkap_get_oauth_google_calendar_options( $product_id, $user_id );					

								if ( isset( $booking_settings['product_sync_integration_mode'] ) ) {
									$sync_mode = $booking_settings['product_sync_integration_mode'];
									switch ( $sync_mode ) {
										case 'oauth':
											$sync_oauth    = 'checked';
											$sync_disable  = '';
											break;
										case 'directly':
											$sync_directly = 'checked';
											$sync_disable  = '';
											break;
									}
								}
								?>
								<th>
									<label for="product_sync_integration_mode"><?php esc_html_e( 'Integration Mode:', 'woocommerce-booking' ); ?></label>
								</th>
								<td>
									<div class="product_sync_integration_mode_div">
										<input type="radio" name="product_sync_integration_mode" id="product_sync_integration_mode" value="oauth" <?php echo esc_attr( $sync_oauth ); ?> /> <?php esc_html_e( 'OAuth Sync (Recommended)', 'woocommerce-booking' ); ?><br>
										<input type="radio" name="product_sync_integration_mode" id="product_sync_integration_mode" value="directly" <?php echo esc_attr( $sync_directly ); ?> /> <?php esc_html_e( 'Service Account Sync', 'woocommerce-booking' ); ?><br>
										<input type="radio" name="product_sync_integration_mode" id="product_sync_integration_mode" value="disabled" <?php echo esc_attr( $sync_disable ); ?> /> <?php esc_html_e( 'Disabled', 'woocommerce-booking' ); ?>
									</div>
								</td>
								<td>
									<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( '<b>OAuth Sync</b> will add booking event to Google Calendar Automatically using the OAuth method. This is the most recommended method to sync events with minimal steps. <br><br><b>Service Account Sync</b> will add the booking events to the Google calendar, which is set in the Calendar to be used field, automatically when a customer places an order.<br><br><b>Disabled</b> will disable the integration with Google Calendar. Note: Import of the events will work manually using .ics link.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); // phpcs:ignore ?>/woocommerce/assets/images/help.png"/>
								</td>
							</tr>

							<?php
							$oauth_setting = get_post_meta( $product_id, '_bkap_calendar_oauth_integration', true );

							foreach ( $oauth_options as $key => $option ) {
								$option_id    = 'product_auth' . $option['id'];
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
								$style = isset( $option['css'] ) ? ' style="' . $option['css'] . '"' : '';
								$css   = ( strpos( $style, 'display:none;' ) !== false ) ? $style : '';

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
								}
								$data[] = array( $option['title'] . ( isset( $option['desc_tip'] ) ? wc_help_tip( $option['desc_tip'] ) : '' ), $the_field, $css );
							}

							foreach ( $data as $oauth_field ) {
								?>
								<tr class="bkap_oauth_mode">
									<th <?php echo $oauth_field[2]; // phpcs:ignore ?>><?php echo $oauth_field[0]; ?></th>
									<td><?php echo $oauth_field[1]; // phpcs:ignore ?></td>
								</tr>
								<?php
							}
							?>

							<tr class="bkap_directly_mode">
								<?php
								$gcal_key_file = '';
								if ( isset( $booking_settings['product_sync_key_file_name'] ) && '' !== $booking_settings['product_sync_key_file_name'] ) {
									$gcal_key_file = $booking_settings['product_sync_key_file_name'];
								}
								?>
								<th >
									<label for="product_sync_key_file_name"><?php _e( 'Key File Name:', 'woocommerce-booking' ); ?></label>
								</th>
								<td>
									<input id="product_sync_key_file_name" name= "product_sync_key_file_name" value="<?php echo $gcal_key_file; ?>" size="40" type="text" />
								</td>
								<td>
									<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Enter key file name here without extention, e.g. ab12345678901234567890-privatekey.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
								</td>
							</tr>

							<tr class="bkap_directly_mode">
								<?php
								$gcal_service_acc_email_addr = '';
								if ( isset( $booking_settings['product_sync_service_acc_email_addr'] ) && $booking_settings['product_sync_service_acc_email_addr'] ) {
									$gcal_service_acc_email_addr = $booking_settings['product_sync_service_acc_email_addr'];
								}
								?>
								<th>
									<label for="product_sync_service_acc_email_addr"><?php _e( 'Service Account Email Address:', 'woocommerce-booking' ); ?></label>
								</th>
								<td>
									<input id="product_sync_service_acc_email_addr" name= "product_sync_service_acc_email_addr" value="<?php echo $gcal_service_acc_email_addr; ?>" size="40" type="text" />
								</td>
								<td>
									<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Enter Service account email address here, e.g. 1234567890@developer.gserviceaccount.com.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
								</td>
							</tr>

							<tr class="bkap_directly_mode">
								<?php
								$gcal_service_calendar_addr = '';
								if ( isset( $booking_settings['product_sync_calendar_id'] ) && '' != $booking_settings['product_sync_calendar_id'] ) {
									$gcal_service_calendar_addr = $booking_settings['product_sync_calendar_id'];
								}
								?>
								<th>
									<label for="product_sync_calendar_id"><?php _e( 'Calendar to be used:', 'woocommerce-booking' ); ?></label>
								</th>
								<td>
									<input id="product_sync_calendar_id" name= "product_sync_calendar_id" value="<?php echo $gcal_service_calendar_addr; ?>" size="40" type="text" />
								</td>
								<td>
									<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Enter the ID of the calendar in which your bookings will be saved, e.g. abcdefg1234567890@group.calendar.google.com.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
								</td>
							</tr>

							<tr class="bkap_directly_mode">
								<th></th>
								<td>
								<a href='post.php?post=<?php echo $product_id; ?>&action=edit' id='test_connection'><?php _e( 'Test Connection', 'woocommerce-booking' ); ?></a>
								<img src='<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/ajax-loader.gif' id='test_connection_ajax_loader'>
								<div id='test_connection_message'></div>
								</td>				  
							</tr>

							<tr class="bkap_directly_mode">
								<th></th>
								<td>
									<?php _e( "You can follow the instructions given in <a href ='https://www.tychesoftwares.com/how-to-send-woocommerce-bookings-to-different-google-calendars-for-each-bookable-product/' target ='_blank'>this</a> post to setup the Google Calendar Sync settings for your product.", 'woocommerce-booking' ); ?>
								</td>
							</tr>
						</table>

						<hr>
					</div>
					<div id="bkap_gcal_import_section">
						<h2> <strong><?php _e( 'Import and Mapping of Events', 'woocommerce-booking' ); ?>  </strong> </h2>

						<table class='form-table bkap-form-table'>
							<tr>
								<?php
								$enable_mapping = '';
								if ( isset( $booking_settings['enable_automated_mapping'] ) && 'on' == $booking_settings['enable_automated_mapping'] ) {
									$enable_mapping = 'checked';
								}
								?>
								<th>
								<?php _e( 'Enable Automated Mapping for Imported Events:', 'woocommerce-booking' ); ?>
								</th>
								<td>
									<label class="bkap_switch">
										<input id="enable_automated_mapping" name= "enable_automated_mapping" type="checkbox" <?php echo $enable_mapping; ?>/>
									<div class="bkap_slider round"></div> 
								</td>
								<td>
									<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Enable if you wish to allow for imported events to be automatically mapped to the product.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
								</td>
							</tr>

							<?php
							$_product = wc_get_product( $product_id );
							if ( $_product == false ) {
								$product_type = '';
							} else {
								$product_type = $_product->get_type();
							}

							if ( isset( $product_type ) && 'variable' == $product_type ) {
								?>
								<tr>
								<?php
								$available_variations = $_product->get_available_variations();
								?>
									<th>
									<?php _e( 'Default Variation to which Events should be mapped:', 'woocommerce-booking' ); ?>
									</th>
									<td>
									<?php
									if ( isset( $available_variations ) && count( $available_variations ) > 0 ) {
										?>
										<select id="gcal_default_variation" name= "gcal_default_variation" style="max-width:70%;">
										<?php

										foreach ( $available_variations as $key => $value ) {
											$selected_variation = '';
											$variation_id       = $value['variation_id'];
											if ( isset( $booking_settings['gcal_default_variation'] ) && '' != $booking_settings['gcal_default_variation'] && $variation_id == $booking_settings['gcal_default_variation'] ) {
												$selected_variation = 'selected';
											}
											$variation_product = wc_get_product( $variation_id );
											$variation_name    = $variation_product->get_formatted_name();
											?>
												<option value='<?php echo $variation_id; ?>' <?php echo $selected_variation; ?> ><?php echo $variation_name; ?></option>
												<?php
										}
										?>
										</select><br>

										<?php } ?>
									</td>
									<td>
										<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Select the default variation to which the product should be mapped. If left blanks, then the first variation shall be chosen.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>

									</td>

								</tr>
								<?php
							}
							?>

						</table>
						<table class='form-table bkap-form-table' id="product_ics_feed_list">
								<?php

								$label = '<label for="product_ics_fee_url_0">' . __( '.ics/ICAL Feed URL', 'woocommerce-booking' ) . '</label>';
								if ( isset( $booking_settings['ics_feed_url'] ) && count( $booking_settings['ics_feed_url'] ) > 0 ) {
									foreach ( $booking_settings['ics_feed_url'] as $key => $value ) {
										echo ( "
											<tr id='$key'>
												<th>$label</th>
												<td class='ics_feed_url'>
													<input type='text' id='product_ics_fee_url_$key' name='product_ics_fee_url_$key' size='40' value='" . $value . "'><br><br>
													<input type='button' class='save_button' id='$key' name='import_ics' value='Import Events'>
													<input type='button' class='save_button' id='$key' value='Delete' name='delete_ics_feed'>
													<div id='import_event_message' style='display:none;'>
														<img src='" . plugins_url() . "/woocommerce-booking/assets/images/ajax-loader.gif'>
													</div>
													<div id='success_message' ></div>
												</td>
											</tr>
											" );
									}
								} else {
									echo ( "
										<tr id='0'>
											<th>$label</th>
											<td class='ics_feed_url'>
												<input type='text' id='product_ics_fee_url_0' name='product_ics_fee_url_0' size='40' value=''>
											</td>
										</tr>
									" );
								}
								?>
						</table>

						<table class='form-table bkap-form-table'>
							<tr>
								<th></th>
								<td>
									<input type='button' class='save_button' id='add_new_ics_feed' name='add_new_ics_feed' value='Add New Ics feed url'>
								</td>
							</tr>				
						</table>
					</div>
					<br>
					<br>					
				</fieldset>
			</div> <!-- Google Calendar Sync -->

			<?php
			if ( BKAP_License::enterprise_license() ) {
				?>

				<!-- Zoom Meetings -->
				<button type="button" class="bkap-integrations-accordion bkap_integration_zoom_button"><b><?php esc_html_e( 'Zoom Meetings', 'woocommerce-booking' ); ?></b></button>
				<div class="bkap_google_sync_settings_content bkap_integrations_panel bkap_integration_zoom_panel">
					<table class='form-table bkap-form-table'>
						<tr>
							<?php
							$enable_zoom_meeting = '';
							if ( isset( $booking_settings['zoom_meeting'] ) && 'on' === $booking_settings['zoom_meeting'] ) {
								$enable_zoom_meeting = 'checked';
							}
							?>
							<th>
								<?php esc_html_e( 'Enable Zoom Meetings', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="enable_zoom_meeting" name= "enable_zoom_meeting" type="checkbox" <?php esc_attr_e( $enable_zoom_meeting ); ?>/>
								<div class="bkap_slider round"></div> 
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable Zoom Meetings.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<?php

						$zoom_api_key    = get_option( 'bkap_zoom_api_key', '' );
						$zoom_api_secret = get_option( 'bkap_zoom_api_secret', '' );
						$response        = new stdClass();

						if ( '' !== $zoom_api_key && '' !== $zoom_api_secret ) {
							$zoom_connection = bkap_zoom_connection();
							$response        = json_decode( $zoom_connection->bkap_list_users() );
						}

						if ( isset( $response->users ) ) {
							$zoom_host_id = '';
							if ( isset( $booking_settings['zoom_meeting_host'] ) && '' !== $booking_settings['zoom_meeting_host'] ) {
								$zoom_host_id = $booking_settings['zoom_meeting_host'];
							}

							$zoom_meeting_auth = '';
							if ( isset( $booking_settings['zoom_meeting_auth'] ) && 'on' === $booking_settings['zoom_meeting_auth'] ) {
								$zoom_meeting_auth = 'checked';
							}

							$zoom_meeting_jbh = '';
							if ( isset( $booking_settings['zoom_meeting_join_before_host'] ) && 'on' === $booking_settings['zoom_meeting_join_before_host'] ) {
								$zoom_meeting_jbh = 'checked';
							}

							$zoom_meeting_host_video = '';
							if ( isset( $booking_settings['zoom_meeting_host_video'] ) && 'on' === $booking_settings['zoom_meeting_host_video'] ) {
								$zoom_meeting_host_video = 'checked';
							}

							$zoom_meeting_pv = '';
							if ( isset( $booking_settings['zoom_meeting_participant_video'] ) && 'on' === $booking_settings['zoom_meeting_participant_video'] ) {
								$zoom_meeting_pv = 'checked';
							}

							$zoom_meeting_mua = '';
							if ( isset( $booking_settings['zoom_meeting_mute_upon_entry'] ) && 'on' === $booking_settings['zoom_meeting_mute_upon_entry'] ) {
								$zoom_meeting_mua = 'checked';
							}

							$zoom_meeting_ar = '';
							if ( isset( $booking_settings['zoom_meeting_auto_recording'] ) && '' !== $booking_settings['zoom_meeting_auto_recording'] ) {
								$zoom_meeting_ar = $booking_settings['zoom_meeting_auto_recording'];
							}

							$zoom_alternative_host_ids = array();
							if ( isset( $booking_settings['zoom_meeting_alternative_host'] ) && '' !== $booking_settings['zoom_meeting_alternative_host'] ) {

								if ( isset( $booking_settings['zoom_meeting_alternative_host'] ) ) {

									if ( is_array( $booking_settings['zoom_meeting_alternative_host'] ) ) {
										$zoom_alternative_host_ids = $zoom_alternative_host_ids;
									} elseif ( '' !== $zoom_alternative_host_ids ) {
										array_push( $zoom_alternative_host_ids, $booking_settings['zoom_meeting_alternative_host'] );
									}
								}

								$zoom_alternative_host_ids = $booking_settings['zoom_meeting_alternative_host'];
							}

							?>

						<tr>
							<th>
								<?php esc_html_e( 'Host', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<select name="bkap_zoom_meeting_host" id="bkap_zoom_meeting_host">
								<option value=''><?php esc_html_e( 'Select Host', 'woocommerce-booking' ); ?></option>
								<?php
								foreach ( $response->users as $user ) {
									$zoom_host_selected = ( $user->id === $zoom_host_id ) ? 'selected' : '';
									$zoom_first_name    = $user->first_name;
									$zoom_last_name     = $user->last_name;
									$zoom_email         = $user->email;
									$zoom_display       = $zoom_first_name . ' ' . $zoom_last_name . ' - ' . $zoom_email;
									printf( "<option value='%s' %s>%s</option>", esc_attr( $user->id ), esc_attr( $zoom_host_selected ), esc_html( $zoom_display ) );
								}
								?>
								</select>							
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Selected user will be assgined as host for created meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Meeting Authentication -->

						<tr>
							<th>
								<?php esc_html_e( 'Meeting Authentication', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="bkap_zoom_meeting_authentication" name= "bkap_zoom_meeting_authentication" type="checkbox" <?php esc_attr_e( $zoom_meeting_auth ); ?>/>
								<div class="bkap_slider round"></div>					
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enabling this option will allow only authenticated users to join the meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Join Before Host -->

						<tr>
							<th>
								<?php esc_html_e( 'Join Before Host', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="bkap_zoom_meeting_join_before_host" name= "bkap_zoom_meeting_join_before_host" type="checkbox" <?php esc_attr_e( $zoom_meeting_jbh ); ?>/>
								<div class="bkap_slider round"></div>						
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enabling this option will allow participants to join the meeting before the host starts the meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Host Video -->

						<tr>
							<th>
								<?php esc_html_e( 'Host Video', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="bkap_zoom_meeting_host_video" name= "bkap_zoom_meeting_host_video" type="checkbox" <?php esc_attr_e( $zoom_meeting_host_video ); ?>/>
								<div class="bkap_slider round"></div>							
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enaling this option will start the video when the host joins the meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Participant Video -->

						<tr>
							<th>
								<?php esc_html_e( 'Participant Video', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="bkap_zoom_meeting_participant_video" name= "bkap_zoom_meeting_participant_video" type="checkbox" <?php esc_attr_e( $zoom_meeting_pv ); ?>/>
								<div class="bkap_slider round"></div>							
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enaling this option will start the video when the participants join the meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Mute Upon Entry -->

						<tr>
							<th>
								<?php esc_html_e( 'Mute Upon Entry', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<label class="bkap_switch">
									<input id="bkap_zoom_meeting_mute_upon_entry" name= "bkap_zoom_meeting_mute_upon_entry" type="checkbox" <?php esc_attr_e( $zoom_meeting_mua ); ?>/>
								<div class="bkap_slider round"></div>						
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enabling this option will mute the participants upon entry.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Auto Recording -->

						<tr>
							<th>
								<?php esc_html_e( 'Auto Recording', 'woocommerce-booking' ); ?>
							</th>
							<td>
								<select name="bkap_zoom_meeting_auto_recording" id="bkap_zoom_meeting_auto_recording">
									<option value='none' <?php selected( $zoom_meeting_ar, 'none' ); ?>><?php esc_html_e( 'None', 'woocommerce-booking' ); ?></option>
									<option value='local' <?php selected( $zoom_meeting_ar, 'local' ); ?>><?php esc_html_e( 'Local', 'woocommerce-booking' ); ?></option>
									<option value='cloud' <?php selected( $zoom_meeting_ar, 'cloud' ); ?>><?php esc_html_e( 'Cloud', 'woocommerce-booking' ); ?></option>
								</select>							
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable this option for automatic recording of meeting.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>

						<!-- Alternative Host -->
						<tr>
							<th>
								<?php esc_html_e( 'Alternative Host', 'woocommerce-booking' ); ?>
							</th>
							<td>
							<select name="bkap_zoom_meeting_alternative_host" id="bkap_zoom_meeting_alternative_host" multiple="multiple">
								<option value=''><?php esc_html_e( 'Select Host', 'woocommerce-booking' ); ?></option>
								<?php
								foreach ( $response->users as $user ) {
									$zoom_host_selected = ( in_array( $user->id, $zoom_alternative_host_ids, true ) ) ? 'selected' : '';
									$zoom_first_name    = $user->first_name;
									$zoom_last_name     = $user->last_name;
									$zoom_email         = $user->email;
									$zoom_display       = $zoom_first_name . ' ' . $zoom_last_name . ' - ' . $zoom_email;
									printf( "<option value='%s' %s>%s</option>", esc_attr( $user->id ), esc_attr( $zoom_host_selected ), esc_html( $zoom_display ) );
								}
								?>
								</select>							
							</td>
							<td>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Here you can select the alternative host\'s emails.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
							</td>
						</tr>
							<?php
						}
						?>

						<tr>
							<th></th>
							<td colspan="2">
								<p><i>
									<?php
									$apikey        = get_option( 'bkap_zoom_api_key', '' );
									$apisecret     = get_option( 'bkap_zoom_api_secret', '' );
									$redirect_args = array(
										'page'    => 'woocommerce_booking_page',
										'action'  => 'calendar_sync_settings',
										'section' => 'zoom_meeting',
									);
									$url           = add_query_arg( $redirect_args, admin_url( '/admin.php?' ) );
									if ( '' === $apikey || '' === $apisecret ) {
										/* translators: %s: Zoom Meeting Settings page link */
										$api_msg = sprintf( __( 'Set App API Key and API Secret for Zoom connection <a href="%s" target="_blank">here.</a>', 'woocommerce-booking' ), $url );
										echo $api_msg; // phpcs:ignore
									}
									?>
									</i>
								</p> 
							</td>
						</tr>
					</table>
					<br>
				</div>

				<?php
				do_action( 'bkap_after_zoom_meeting_settings_product', $product_id, $booking_settings );
			}
			?>

			<hr />
			<?php
			if ( isset( $post_type ) && 'product' === $post_type ) {
				bkap_booking_box_class::bkap_save_button( 'bkap_save_gcal_settings' );
			}
			?>
			<div id='gcal_update_notification' style='display:none;'></div>

		</div>
		<?php

	}

	/**
	 * Saves the settings in the Google Calender Sync Settings to the DB
	 * in the Booking meta box in the Add/Edit Product page.
	 *
	 * @param array   $booking_settings - Booking Settings
	 * @param integer $product_id - Product ID
	 * @return array $booking_settings - Updated Booking Settings
	 *
	 * @since 2.6.3
	 */

	function bkap_gcal_product_settings_save( $booking_settings, $product_id ) {

		// get existing settings to ensure calendar details are retained even when sync is disabled
		$bkap_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

		if ( isset( $_POST['product_sync_integration_mode'] ) ) {
			$booking_settings['product_sync_integration_mode'] = $_POST['product_sync_integration_mode'];
		}

		$file_name = '';
		if ( isset( $_POST['product_sync_key_file_name'] ) ) {
			$file_name = $_POST['product_sync_key_file_name'];
		} elseif ( isset( $bkap_settings['product_sync_key_file_name'] ) && '' != $bkap_settings['product_sync_key_file_name'] ) {
			$file_name = $bkap_settings['product_sync_key_file_name'];
		}
		$booking_settings['product_sync_key_file_name'] = $file_name;

		$acc_email = '';
		if ( isset( $_POST['product_sync_service_acc_email_addr'] ) ) {
			$acc_email = $_POST['product_sync_service_acc_email_addr'];
		} elseif ( isset( $bkap_settings['product_sync_service_acc_email_addr'] ) && '' != $bkap_settings['product_sync_service_acc_email_addr'] ) {
			$acc_email = $bkap_settings['product_sync_service_acc_email_addr'];
		}
		$booking_settings['product_sync_service_acc_email_addr'] = $acc_email;

		$calendar_id = '';
		if ( isset( $_POST['product_sync_calendar_id'] ) ) {
			$calendar_id = $_POST['product_sync_calendar_id'];
		} elseif ( isset( $bkap_settings['product_sync_calendar_id'] ) && '' != $bkap_settings['product_sync_calendar_id'] ) {
			$calendar_id = $bkap_settings['product_sync_calendar_id'];
		}
		$booking_settings['product_sync_calendar_id'] = $calendar_id;

		if ( isset( $_POST['enable_automated_mapping'] ) ) {
			$booking_settings['enable_automated_mapping'] = $_POST['enable_automated_mapping'];
		}
		if ( isset( $_POST['gcal_default_variation'] ) ) {
			$booking_settings['gcal_default_variation'] = $_POST['gcal_default_variation'];
		}

		for ( $key = 0; ;$key++ ) {
			$field_name = 'product_ics_fee_url_' . $key;
			if ( isset( $_POST[ $field_name ] ) ) {
				$booking_settings['ics_feed_url'][ $key ] = $_POST[ $field_name ];
			} else {
				break;
			}
		}

		return $booking_settings;
	}
}
$product_gcal_settings = new product_gcal_settings();
?>
