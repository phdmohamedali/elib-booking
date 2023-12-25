<?php
/**
 * This file is contains the two information of the availability table.
 *
 * @package BulkBookingSetting
 */

	$bkap_intervals = array();

	$bkap_intervals['daysdate'] = array(
		'day'  => __( 'Day', 'woocommerce-booking' ),
		'date' => __( 'Date', 'woocommerce-booking' ),
	);

	$bkap_intervals['days'] = array(
		'all' => __( 'All', 'woocommerce-booking' ),
		'0'   => __( 'Sunday', 'woocommerce-booking' ),
		'1'   => __( 'Monday', 'woocommerce-booking' ),
		'2'   => __( 'Tuesday', 'woocommerce-booking' ),
		'3'   => __( 'Wednesday', 'woocommerce-booking' ),
		'4'   => __( 'Thursday', 'woocommerce-booking' ),
		'5'   => __( 'Friday', 'woocommerce-booking' ),
		'6'   => __( 'Saturday', 'woocommerce-booking' ),
	);

	$bkap_intervals['actions'] = array(
		'add'    => __( 'Add', 'woocommerce-booking' ),
		'update' => __( 'Update', 'woocommerce-booking' ),
		'delete' => __( 'Delete', 'woocommerce-booking' ),
	);
	?>
<tr class="bkap_bulk_booking_setting_row">
	<td>
		<div class="bkap_bulk_day_date_div">
			<select id="bkap_bulk_day_date" name="bkap_bulk_day_date">
				<option value="day"><?php esc_html_e( 'Day', 'woocommerce-booking' ); ?></option>
				<option value="date"><?php esc_html_e( 'Date', 'woocommerce-booking' ); ?></option>
			</select>
		</div>
	</td> <!-- Day/Date -->
	<td>
		<div class="bkap_bulk_day_div">
			<select class="bkap_bulk_day_class" id="bkap_bulk_weekday" name="bkap_bulk_weekday[]" multiple="multiple">
				<?php
				foreach ( $bkap_intervals['days'] as $key => $value ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="bkap_bulk_date fake-input"  style="display: none;">
			<textarea id="bkap_bulk_date_field" name="bkap_bulk_date_field" class="date-picker" rows="1" col="30" style="width:100%;height:auto;"></textarea>
			<!-- <input type="text" class="date-picker" id="bkap_bulk_date_field" name="bkap_bulk_date_field"> -->
			<img src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce-booking/assets/images/cal.gif" id="custom_checkin_cal" width="15" height="15" />
		</div>
	</td> <!-- Day/Date field -->
	<td>
		<div class="bkap_bulk_action_div">
			<select id="bkap_bulk_action" name="bkap_bulk_action">
				<?php
				foreach ( $bkap_intervals['actions'] as $key => $value ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
				<?php } ?>
			</select>    
		</div>		
	</td> <!-- Actions -->
	<td>
		<div class="bkap_bulk_fromto_div">
			<input type="text" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" maxlength="5" onkeypress="return bkap_isNumberKey(event)" id="bkap_bulk_from_time" name="bkap_bulk_from_time">
			<input type="text" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" maxlength="5" onkeypress="return bkap_isNumberKey(event)" id="bkap_bulk_to_time" name="bkap_bulk_to_time">    
		</div>
	</td> <!-- From & To -->
	<td>
		<div class="bkap_bulk_maxbooking_div">
			<input type="number" id="bkap_bulk_max_booking" name="bkap_bulk_max_booking" placeholder="Max Booking">
		</div>
	</td> <!-- Max Booking -->
	<td>
		<div class="bkap_bulk_price_div">
			<input type="text" id="bkap_bulk_price" name="bkap_bulk_price" placeholder="Price">
		</div>
	</td> <!-- Price -->
	<td>
		<div class="bkap_bulk_note_div"><textarea rows="2" id="bkap_bulk_note" cols="20" placeholder="Add note here.."></textarea></div>
	</td> <!-- Note -->
	<td id="bkap_close_bulk_row" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
</tr>
