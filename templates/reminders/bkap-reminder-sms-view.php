<?php
/**
 * Price by User Role for WooCommerce - Section Settings
 *
 * @package PriceByUserRole
 * @version 1.2.0
 * @since   1.0.0
 * @author  Tyche Softwares
 */

?>
<hr>

<section class="bkap-manual">
	<div class="wrap">
		<h2><?php esc_html_e( 'SMS Reminders', 'woocommerce-booking' ); ?></h2>
		<h3><?php esc_html_e( 'Twilio', 'woocommerce-booking' ); ?></h3>
		<p><?php esc_html_e( 'Configure your Twilio account settings below. Please note that due to some restrictions from Twilio, customers may sometimes receive delayed messages', 'woocommerce-booking' ); ?></p>
		<form method="POST">
			<table class="form-table">
				<tbody>
					<tr valign="top" >
						<th scope="row">
							<label for='bkap_sms_enable'><?php esc_html_e( 'Enable SMS', 'woocommerce-booking' ); ?></label>
						</th>
						<td class="forminp">
							<?php
								$checked = isset( $options['enable_sms'] ) ? $options['enable_sms'] : '';
							?>
							<input type='checkbox' id='bkap_sms_enable' name='bkap_sms_settings[enable_sms]' <?php checked( $checked, 'on' ); ?>><i><?php esc_html_e( 'Enable the ability to send reminder SMS for bookings.', 'woocommerce-booking' ); ?></i>
						</td>
					</tr>

					<tr valign="top" id="reminder_order_ids">
						<th scope="row">
							<label for="bkap_sms_from"><?php esc_html_e( 'From', 'woocommerce-booking' ); ?></label>
						</th>
						<td class="forminp">
							<?php
								$from = isset( $options['from'] ) ? $options['from'] : '';
							?>
							<input type='text' id='bkap_sms_from' name='bkap_sms_settings[from]' value='<?php echo wp_kses_post( $from ); ?>'>
							<i><?php esc_html_e( 'Must be a Twilio phone number (in E.164 format) or alphanumeric sender ID.', 'woocommerce-booking' ); ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="bkap_sms_account_sid"><?php esc_html_e( 'Account SID', 'woocommerce-booking' ); ?></label>
						</th>
						<td>
							<?php
								$account_sid = isset( $options['account_sid'] ) ? $options['account_sid'] : '';
							?>
							<input type='text' id="bkap_sms_account_sid" name='bkap_sms_settings[account_sid]' value='<?php echo wp_kses_post( $account_sid ); ?>'>  
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="bkap_sms_auth_token"><?php esc_html_e( 'Auth Token', 'woocommerce-booking' ); ?></label>
						</th>
						<td>
							<?php
								$auth_token = isset( $options['auth_token'] ) ? $options['auth_token'] : '';
							?>
							<input type='text' id='bkap_sms_auth_token' name='bkap_sms_settings[auth_token]' value='<?php echo wp_kses_post( $auth_token ); ?>'> 
						</td>
					</tr>
				
					<tr valign="top">
						<td>
							<input type="submit" name="bkap_sms_reminder" class="button-primary" value="<?php esc_html_e( 'Save Settings', 'woocommerce-booking' ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	<div>

	<div id="test_fields">
		<h2><?php esc_html_e( 'Send Test SMS', 'woocommerce-booking' ); ?></h2>
		<div id="status_msg" style="background: white;border-left: #6389DA 4px solid;padding: 10px;display: none;width: 90%;"></div>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Recipient', 'woocommerce-booking' ); ?></th>
				<td>
					<input id="bkap_test_number" name="bkap_test_number" type=text />
					<i><?php esc_html_e( 'Must be a valid phone number in E.164 format.', 'woocommerce-booking' ); ?></i>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Message', 'woocommerce-booking' ); ?></th>
				<td><textarea id="bkap_test_msg" rows="4" cols="70"><?php esc_html_e( 'Hello World!', 'woocommerce-booking' ); ?></textarea></td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="button" id="bkap_test_sms" class="button-primary" value="<?php esc_html_e( 'Send', 'woocommerce-booking' ); ?>" />
					<img id="send_ajax_img" style="display:none;" class="ajax_img" src="<?php echo esc_url( plugins_url() ) . '/woocommerce-booking/assets/images/ajax-loader.gif'; ?>">
				</td>
			</tr>
		</table>
	</div>

</section>

<?php do_action( 'bkap_after_email_reminder_settings' ); ?>
