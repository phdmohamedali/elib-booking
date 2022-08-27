<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $bkap_weekdays;

$number             = 0;
$specific_dates     = array();
$recurring_weekdays = array();

if ( isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) { // bookable product
	$recurring_weekdays = $booking_settings['booking_recurring'];
}

if ( isset( $booking_settings['booking_time_settings'] ) && is_array( $booking_settings['booking_time_settings'] ) ) {
	$number = count( $booking_settings['booking_time_settings'] );
}

if ( isset( $booking_settings['booking_specific_date'] ) && count( $booking_settings['booking_specific_date'] ) > 0 ) {
	$specific_dates = $booking_settings['booking_specific_date'];
}

$bkap_day_date = $bkap_from_time = $bkap_to_time = $bkap_lockout = $bkap_price = $bkap_global = $bkap_note = '';


if ( $number == 0 ) {

} else {
	$number = 1;
	/**
	 * This tr is a identifier, when we recive the response from ajax we will remove this tr and replace
	 * our genrated data.
	 */
	?>
	<tr class="bkap_replace_response_data">
	</tr>
	<?php
	foreach ( $booking_settings['booking_time_settings'] as $bkap_weekday_key => $bkap_weekday_value ) {

		// usort( $bkap_weekday_value, 'bkap_sort_by_from_time');

		foreach ( $bkap_weekday_value as $day_key => $time_data ) {

			$bkap_from_hr   = ( isset( $time_data['from_slot_hrs'] ) && ! is_null( $time_data['from_slot_hrs'] ) ) ? $time_data['from_slot_hrs'] : '';
			$bkap_from_min  = ( isset( $time_data['from_slot_min'] ) && ! is_null( $time_data['from_slot_min'] ) ) ? $time_data['from_slot_min'] : '';
			$bkap_from_time = $bkap_from_hr . ':' . $bkap_from_min;

			$bkap_to_hr   = ( isset( $time_data['to_slot_hrs'] ) && ! is_null( $time_data['to_slot_hrs'] ) ) ? $time_data['to_slot_hrs'] : '';
			$bkap_to_min  = ( isset( $time_data['to_slot_min'] ) && ! is_null( $time_data['to_slot_min'] ) ) ? $time_data['to_slot_min'] : '';
			$bkap_to_time = ( $bkap_to_hr === '0' && $bkap_to_min === '00' ) ? '' : "$bkap_to_hr:$bkap_to_min";

			$bkap_lockout = ( isset( $time_data['lockout_slot'] ) && ! is_null( $time_data['lockout_slot'] ) ) ? $time_data['lockout_slot'] : '';
			$bkap_price   = ( isset( $time_data['slot_price'] ) && ! is_null( $time_data['slot_price'] ) ) ? $time_data['slot_price'] : '';

			$bkap_global = ( isset( $time_data['global_time_check'] ) && ! is_null( $time_data['global_time_check'] ) ) ? $time_data['global_time_check'] : '';
			$bkap_note   = ( isset( $time_data['booking_notes'] ) && ! is_null( $time_data['booking_notes'] ) ) ? $time_data['booking_notes'] : '';

			$bkap_global_checked          = '';
			$bkap_time_row_toggle         = '';
			$bkap_time_row_toggle_display = '';

			if ( $bkap_global == 'on' ) {
				$bkap_global_checked = 'checked';
			}

			if ( $number >= $bkap_start_record_from && $number <= $bkap_end_record_on ) {
				?>
				
				<tr id="bkap_date_time_row_<?php echo $number; ?>" class="bkap_dateday_td">
					<td width="20%">
						<select id="bkap_dateday_selector_<?php echo $number; ?>" class="bkap_dateday_selector" multiple="multiple" disabled >
						
							<?php
							// Recurring Weekdays
							foreach ( $bkap_weekdays as $w_value => $w_name ) {
								$bkap_selected = '';

								if ( $w_value == $bkap_weekday_key ) {
									$bkap_selected = 'selected';
									printf( "<option value='%s' %s>%s</option>\n", $w_value, $bkap_selected, $w_name );
								} elseif ( isset( $recurring_weekdays[ $w_value ] ) && 'on' == $recurring_weekdays[ $w_value ] ) {
									// add the option value only if the weekday is enabled
									printf( "<option value='%s' %s>%s</option>\n", $w_value, $bkap_selected, $w_name );
								}
							}
							// Specific Dates
							foreach ( $specific_dates as $dates => $lockout ) {
								$bkap_selected = '';

								if ( trim( $dates ) == trim( $bkap_weekday_key ) ) {
									$bkap_selected = 'selected';
								}
								printf( "<option value='%s' %s>%s</option>\n", $dates, $bkap_selected, $dates );

							}
							?>
						
						   <option name="all" value="all"><?php _e( 'All', 'woocommerce-booking' ); ?></option>
						</select>
					</td>
					<td width="10%" class="bkap_from_time_td"><input id="bkap_from_time_<?php echo $number; ?>" type="text" name="quantity" style="width:100%;" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" minlength="5" maxlength="5" onkeypress="return bkap_isNumberKey(event)" value="<?php echo $bkap_from_time; ?>" readonly></td>
					<td width="10%" class="bkap_to_time_td"><input id="bkap_to_time_<?php echo $number; ?>" type="text" name="quantity" style="width:100%;" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" minlength="5" maxlength="5" onkeypress="return bkap_isNumberKey(event)" value="<?php echo $bkap_to_time; ?>" readonly></td>
					<td width="10%" class="bkap_lockout_time_td"><input id="bkap_lockout_time_<?php echo $number; ?>" onkeypress="return bkap_only_number( event )" type="number" name="quantity" style="width:100%;" min="0" placeholder="Max bookings" value="<?php echo $bkap_lockout; ?>" class = "bkap_default" ></td>
					<td width="10%" class="bkap_price_time_td"><input id="bkap_price_time_<?php echo $number; ?>" type="text" name="quantity" style="width:100%;" placeholder="Price" value="<?php echo $bkap_price; ?>" class = "bkap_default wc_input_price"></td>
					<td width="10%" style="text-align:center;" class="bkap_global_time_td">
						<label class="bkap_switch">
						  <input id="bkap_global_time_<?php echo $number; ?>" type="checkbox" name="bkap_global_timeslot" style="margin-left: 35%;" <?php echo $bkap_global_checked; ?> class = "bkap_default">
						  <div class="bkap_slider round"></div>
						</label>
					</td>
					<td width="23%" class="bkap_note_time_td"><textarea id="bkap_note_time_<?php echo $number; ?>" rows="1" cols="2" style="width:100%;" class = "bkap_default" ><?php echo $bkap_note; ?></textarea></td>
					<td width="4%"  class="bkap_actions" id="bkap_actions_<?php echo $number; ?>" style="text-align: center;cursor:pointer;">
						<a href="#" id="bkap_edit_<?php echo $number;?>" class="bkap_edit_row" ><i class="fa fa-edit" aria-hidden="true"></i></a>
						<a href="#" id="bkap_close_<?php echo $number;?>" class="bkap_delete_row" ><i class="fa fa-trash" aria-hidden="true"></i></a>
					</td>
				</tr>
				
				<?php

			}

			$number++;
		}
	}
}
