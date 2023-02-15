<?php
	if ( ! isset( $availability['type'] ) ) {
		$availability['type'] = 'custom';
	}
	if ( ! isset( $availability['priority'] ) ) {
		$availability['priority'] = 10;
	}
	?>
<tr>
	<td>
		<div class="select bkap_availability_type">
			<select name="bkap_availability_type[]">
				<?php foreach ( $bkap_intervals['type'] as $key => $label ) : ?>
					<?php

					if ( is_array( $label ) ) {
						?>
						<optgroup label="Time Ranges">
						<?php
						foreach ( $label as $label_key => $label_value ) {
							?>
								<option value="<?php echo $label_key; ?>" <?php selected( isset( $availability['type'] ) && $availability['type'] == $label_key, true ); ?>><?php echo $label_value; ?></option>
							<?php
						}
						?>
						</optgroup>
						<?php
					} else {
						?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['type'] ) && $availability['type'] == $key, true ); ?>><?php echo $label; ?></option>
						<?php
					}
					?>
				<?php endforeach; ?>				
			</select>
		</div>
	</td>

	<td style="border-right:0;">
		<div class="bookings-datetime-select-from">
			<div class="select from_day_of_week">
				<select name="bkap_availability_from_day_of_week[]">
					<?php foreach ( $bkap_intervals['days'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="select from_month">
				<select name="bkap_availability_from_month[]">
					<?php foreach ( $bkap_intervals['months'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="select from_week">
				<select name="bkap_availability_from_week[]">
					<?php foreach ( $bkap_intervals['weeks'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="from_date fake-input">
				<?php
				$from_date = '';
				if ( 'custom' === $availability['type'] && ! empty( $availability['from'] ) ) {
					$from_date = $availability['from'];
				} elseif ( 'time:range' === $availability['type'] && ! empty( $availability['from_date'] ) ) {
					$from_date = $availability['from_date'];
				}
				?>
				<input type="text" class="date-picker" name="bkap_availability_from_date[]" value="<?php echo esc_attr( $from_date ); ?>" />
			</div>
			<div class="from_time">
				<input type="time" class="time-picker" name="bkap_availability_from_time[]"
				<?php
				$from = '';
				if ( strrpos( $availability['type'], 'time' ) === 0 && ! empty( $availability['from'] ) ) {
					$from = $availability['from'];
				}
				?>
				value="<?php echo $from; ?>" placeholder="HH:MM" />
			</div>
		</div>
	</td>

	<td style="border-right:0;" class="bookings-to-label-row">
		<p><?php _e( 'to', 'woocommerce-booking' ); ?></p>
		<p class="bookings-datetimerange-second-label"><?php _e( 'to', 'woocommerce-booking' ); ?></p>
	</td>

	<td>
		<div class='bookings-datetime-select-to'>
			<div class="select to_day_of_week">
				<select name="bkap_availability_to_day_of_week[]">
					<?php foreach ( $bkap_intervals['days'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="select to_month">
				<select name="bkap_availability_to_month[]">
					<?php foreach ( $bkap_intervals['months'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="select to_week">
				<select name="bkap_availability_to_week[]">
					<?php foreach ( $bkap_intervals['weeks'] as $key => $label ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="to_date fake-input">
				<?php
				$to_date = '';
				if ( 'custom' === $availability['type'] && ! empty( $availability['to'] ) ) {
					$to_date = $availability['to'];
				} elseif ( 'time:range' === $availability['type'] && ! empty( $availability['to_date'] ) ) {
					$to_date = $availability['to_date'];
				}
				?>
				<input type="text" class="date-picker" name="bkap_availability_to_date[]" value="<?php echo esc_attr( $to_date ); ?>" />
			</div>

			<div class="to_time">
				<input type="time" class="time-picker" name="bkap_availability_to_time[]"
				<?php
				$to = '';
				if ( strrpos( $availability['type'], 'time' ) === 0 && ! empty( $availability['to'] ) ) {
					$to = $availability['to'];}
				?>
				value="<?php echo $to; ?>" placeholder="HH:MM" />
			</div>
		</div>
	</td>
	<td>
		<label class="bkap_switch">
			<input type="checkbox" class="bkap_checkbox" name="bkap_availability_bookable[]" value="1" <?php checked( ( isset( $availability['bookable'] ) ? $availability['bookable'] : 0 ), true ); ?> />

			<div class="bkap_slider round"></div>
			<?php
			if ( isset( $availability['bookable'] ) && $availability['bookable'] == '1' ) {
				$test = 1;
			} else {
				$test = 0;
			}

			?>
			<input type="hidden" class="bkap_hidden_checkbox" name="bkap_availability_bookable_hidden[]" value="<?php echo $test; ?>" />
		</label>
	</td>
	<td>
	<div class="priority">
		<input type="number" name="bkap_availability_priority[]" value="<?php echo esc_attr( $availability['priority'] ); ?>" placeholder="10" />
	</div>
	</td>
	<td id="bkap_close_resource" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
</tr>
