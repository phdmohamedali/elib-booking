<?php
$units         = array(
	'hours'  => __( 'Hour(s)', 'woocommerce-booking' ),
	'days'   => __( 'Day(s)', 'woocommerce-booking' ),
	'months' => __( 'Month(s)', 'woocommerce-booking' ),
	'years'  => __( 'Year(s)', 'woocommerce-booking' ),
);
$sending_delay = $reminder->get_sending_delay();
$delay_unit    = $sending_delay['delay_unit'];
$delay_value   = $sending_delay['delay_value'];

$triggers   = array(
	'before_booking_date' => __( 'Before Booking Date', 'woocommerce-booking' ),
	'after_booking_date'  => __( 'After Booking Date', 'woocommerce-booking' ),
);
$trigger  = $reminder->get_trigger(); // fetch the trigger data from the key.

$args = array(
	'post_type'      => array( 'product' ),
	'posts_per_page' => -1,
	'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ),
);

$vendor_id = get_current_user_id();
$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
if ( $is_vendor ) {
	$args['author'] = $vendor_id;	
}
$products = get_posts( $args );

$selected_products = $reminder->get_products(); // fetch the products data from the key.

$sms_body   = $reminder->get_sms_body();
$enable_sms = $reminder->get_enable_sms();
if ( 'on' === $enable_sms ) {
	$enable_sms = 'checked';
}

bkap_load_scripts_class::bkap_load_products_css( $bkap_version );
bkap_load_scripts_class::bkap_load_bkap_tab_css( $bkap_version );

?>
<div id='bkap-tabbed-nav' class="tstab-shadows tstab-tabs vertical top-left silver">
	<ul class="tstab-tabs-nav" style="">
		<li class="bkap_general tstab-tab tstab-first tstab-active" data-link="triggers">
			<a id="addnew" class="bkap_tab"><i class="fa fa-cog" aria-hidden="true"></i><?php esc_html_e( 'Triggers', 'woocommerce-booking' ); ?> </a>
		</li>
		<li class="bkap_sms_reminder tstab-tab" data-link='sms_reminder'>
			<a id="settings" class="bkap_tab"><i class="fas fa-comment-alt"></i><?php esc_html_e( 'SMS Reminder', 'woocommerce-booking' ); ?></a>
		</li>
		<?php do_action( 'bkap_reminder_add_tab', $post, $reminder ); ?>
	</ul>
	<div class="tstab-container">

		<!-- Triggers tab starts here -->  

		<div id="triggers" class="tstab-content tstab-active" style="position: relative;display:block;">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="<?php echo $prefix; ?>_sending_delay[delay_value]"><?php esc_html_e( 'Sending delay :', 'woocommerce-booking' ); ?></label>
						</th>
						<td>
							<input type="number" style="width: 80px;" name="<?php echo $prefix; ?>_sending_delay[delay_value]" value="<?php echo esc_attr( $delay_value ); ?>" >

							<select name="<?php echo $prefix; ?>_sending_delay[delay_unit]">
								<?php foreach ( $units as $key => $value ) { ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php echo ( $key == $delay_unit ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $value ); ?></option>
								<?php } ?>
							</select>
							<?php echo bkap_help_tip_html( __( 'Reminder will be sent according to the selected delay value and unit.', 'woocommerce-booking' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo $prefix; ?>_trigger"><?php esc_html_e( 'Trigger :', 'woocommerce-booking' ); ?></label>
						</th>
						<td>
							<select name="<?php echo $prefix; ?>_trigger">
								<?php foreach ( $triggers as $key => $value ) { ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php echo ( $key == $trigger ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $value ); ?></option>
								<?php } ?>
							</select>
							<?php echo bkap_help_tip_html( __( 'Reminder can be send before and after the booking date. Selected value in this field decide whether the reminder should be sent before the booking date or after booking date.', 'woocommerce-booking' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo $prefix; ?>_products"><?php esc_html_e( 'Products :', 'woocommerce-booking' ); ?></label>
						</th>
						<td>
							<select id="<?php echo $prefix; ?>_products"
								name="<?php echo $prefix; ?>_products[]"
								placehoder=<?php echo __( 'Select Products', 'woocommerce-booking' ); ?>
								class=""
								style="width: 300px"
								multiple="multiple">
								<option value="all" <?php echo ( in_array( 'all', $selected_products ) ) ? 'selected="selected"' : ''; ?>><?php esc_html_e( 'All Products', 'woocommerce-booking' ); ?></option>
								<?php
								$productss = '';
								foreach ( $products as $bkey => $bval ) {
									$selected = ( in_array( $bval->ID, $selected_products ) ) ? 'selected="selected"' : '';
									printf( '<option value="%s" %s>%s</option>', esc_attr( $bval->ID ), $selected, esc_html( $bval->post_title ) );
								}
								?>
							</select>
							<?php echo bkap_help_tip_html( __( 'Reminder will be send for the selected products in this field.', 'woocommerce-booking' ) ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Triggers tab starts here -->  

		<div id="sms_reminder" class="tstab-content" style="position: relative;display:block;display:none;">
		<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="<?php echo $prefix; ?>_enable_sms"><?php esc_html_e( 'Send SMS :', 'woocommerce-booking' ); ?></label></th>
						<td>
							<input type="checkbox" name="<?php echo $prefix; ?>_enable_sms" <?php echo esc_attr( $enable_sms ); ?>>
							<?php echo bkap_help_tip_html( __( 'Enable this to start sending the SMS Reminders. You can setup the Twilio SMS at Booking-> Settings-> Integrations page.', 'woocommerce-booking' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo $prefix; ?>_sms_body"><?php esc_html_e( 'SMS Body :', 'woocommerce-booking' ); ?></label></th>
						<td>
						<?php
						if ( '' === $sms_body ) {
							$sms_body = 'Hi {customer_first_name},

You have a booking of {product_title} on {start_date}. 

Your Order # : {order_number}
Order Date : {order_date}
Your booking id is: {booking_id}';
							}
							?>
							<textarea id="<?php echo $prefix; ?>_sms_body" name='<?php echo $prefix; ?>_sms_body' rows="8" cols="40"><?php echo esc_textarea( $sms_body ); ?></textarea>
							<p><span class="description"><?php esc_html_e( 'You can insert the following tags. They will be replaced dynamically', 'woocommerce-booking' ); ?>: <code>{product_title} {order_date} {order_number} {customer_name} {customer_first_name} {customer_last_name} {start_date} {end_date} {booking_time} {booking_id} {booking_resource} {booking_persons} {zoom_link}</code></span></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<!-- Hook to add additional Content -->
		<?php do_action( 'bkap_reminder_add_content' ); ?>
	</div>
</div>

<?php
$ajax_url = get_admin_url() . 'admin-ajax.php';
bkap_load_scripts_class::bkap_common_admin_scripts_js( $bkap_version );
bkap_load_scripts_class::bkap_load_product_scripts_js( $bkap_version, $ajax_url, 'bulk' );

do_action( 'bkap_after_reminder_settings' );
