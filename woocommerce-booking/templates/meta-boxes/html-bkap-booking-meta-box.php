<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'bkap_add_resource_section', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );

?>
<div id='bkap-tabbed-nav' class="tstab-shadows tstab-tabs vertical top-left silver">
	<ul class="tstab-tabs-nav" style="">
		<li class="tstab-tab tstab-first tstab-active" class="bkap_general" data-link="booking_options">
			<a id="addnew" class="bkap_tab"><i class="fa fa-cog" aria-hidden="true"></i><?php esc_html_e( 'General', 'woocommerce-booking' ); ?> </a>
		</li>
		<li class="bkap_availability tstab-tab" data-link='booking_settings'>
			<a id="settings" class="bkap_tab"><i class="fa fa-calendar" aria-hidden="true"></i><?php esc_html_e( 'Availability', 'woocommerce-booking' ); ?></a>
		</li>
		<?php
			do_action( 'bkap_add_tabs', $duplicate_of, $booking_settings ); 
		?>
	</ul>
	<div class="tstab-container">
		<!-- General tab starts here -->  

		<div id="booking_options" class="tstab-content tstab-active" style="position: relative;display:block;">

			<?php do_action( 'bkap_before_enable_bookingoption', $duplicate_of, $booking_settings ); ?>

			<!-- Enable Booking div starts here -->           

			<div id="enable_booking_options_section" class="booking_options-flex-main">

				<?php do_action( 'bkap_before_enable_booking', $duplicate_of, $booking_settings ); ?>    

				<div class="booking_options-flex-child">
					<label for="booking_enable_date"> <?php esc_html_e( 'Enable Booking', 'woocommerce-booking' ); ?> </label>
				</div>

				<?php

				$booking_type             = bkap_get_post_meta_data( $duplicate_of, '_bkap_booking_type', $individual_booking_settings, $has_defaults );
				$enable_date              = apply_filters( 'bkap_enable_booking_default_value', '' );
				$only_day                 = ''; // the only days radio button.
				$single_days              = ''; // single day radio button.
				$date_time                = ''; // date & time radio button.
				$multiple_days            = ''; // multiple days radio button.
				$display_only_day         = ''; // display only days div.
				$multiple_days_setup      = 'style="display:none;"'; // fields in the settings tab for multiple days.
				$purchase_without_date    = '';
				$fixed_time               = '';
				$duration_time            = '';
				$display_date_time        = 'display:none;'; // display date time div.
				$multiple_lockout_disable = 'disabled="disabled"';

				if ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) {

					$enable_date         = 'checked';
					$only_day            = 'checked';
					$single_days         = 'checked';
					$date_time           = '';
					$multiple_days       = '';
					$display_only_day    = '';
					$specific_date_table = 'display:none;';

					if ( isset( $booking_settings['booking_specific_booking'] ) && 'on' === $booking_settings['booking_specific_booking'] ) {
						$specific_date_table = '';
					}
				}

				switch ( $booking_type ) {
					case 'only_day':
						break;
					case 'multiple_days':
						$only_day                 = 'checked'; // the only days radio button.
						$multiple_days            = 'checked'; // multiple days radio button.
						$single_days              = ''; // single day radio button.
						$date_time                = ''; // date & time radio button.
						$multiple_days_setup      = 'display="block"';
						$multiple_lockout_disable = '';
						break;
					case 'date_time':
						$only_day          = '';
						$date_time         = 'checked';
						$display_only_day  = 'display:none;';
						$display_date_time = '';
						$fixed_time        = 'checked';
						break;
					case 'duration_time':
						$only_day          = '';
						$date_time         = 'checked';
						$display_only_day  = 'display:none;';
						$display_date_time = '';
						$duration_time     = 'checked';
						break;
				}
				?>

				<div class="booking_options-flex-child">
					<label class="bkap_switch">
						<input type="checkbox" id="booking_enable_date" name="booking_enable_date" <?php echo $enable_date;?> >
						<div class="bkap_slider round"></div>
					</label>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Enable Booking Date on Products Page', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>
			<hr/>                    
			<!-- Booking Type div starts here -->
			<?php do_action( 'bkap_before_booking_method_select', $duplicate_of, $booking_settings ); ?>    
			<div id="enable_booking_types_section" class="booking_types-flex-main">

				<div class="booking_types-flex-child">
					<label for="booking_enable_type"> <?php esc_html_e( 'Booking Type', 'woocommerce-booking' ); ?> </label>
				</div>

				<!-- Booking Type Dropdown Start -->
				<div class="booking_types-flex-child">
					<label for="bkap-booking-type">
						<select id="bkap-booking-type" name="bkap-booking-type">

						<?php
						$bkap_get_booking_type_groups_dropdown = bkap_get_booking_type_groups_dropdown();

						foreach ( $bkap_get_booking_type_groups_dropdown as $booking_dropdown_group => $booking_dropdown_items ) :

							if ( 'n-g' !== $booking_dropdown_group ) :
								?>

								<optgroup label="<?php echo esc_attr( $booking_dropdown_group ); ?>">

								<?php
								foreach ( $booking_dropdown_items as $booking_dropdown_item ) :
									?>
									<option value="<?php echo esc_attr( $booking_dropdown_item['key'] ); ?>" <?php echo selected( $booking_type, $booking_dropdown_item['key'], false ); ?>><?php echo esc_html( $booking_dropdown_item['label'] ); ?></option>
								<?php endforeach; ?>

								</optgroup>
							<?php else : // phpcs:ignore
								foreach ( $booking_dropdown_items as $booking_dropdown_item ) :
									?>
									<option value="<?php echo esc_attr( $booking_dropdown_item['key'] ); ?>" <?php echo selected( $booking_type, $booking_dropdown_item['key'], false ); ?>><?php echo esc_html( $booking_dropdown_item['label'] ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						<?php endforeach; ?>
						</select>
					</label>
				</div>

				<!-- Booking Type Dropdown Ends -->

				<!-- <div class="booking_types-flex-child"> 
					<div class="booking_types-flex-child-day">
						<input type="radio" id="enable_booking_day_type" name="booking_enable_type" class="enable_booking_type" value="booking_enable_only_day" <?php echo $only_day;?>></input>
						<label for="enable_booking_day_type"> <?php //_e( 'Only Day', 'woocommerce-booking' );?> </label>
					</div>

					<div class="booking_types-flex-child-day">
						<input type="radio" id="enable_booking_day_and_time_type" name="booking_enable_type" class="enable_booking_type" value="booking_enable_date_and_time" <?php echo $date_time;?>></input>
						<label for="enable_booking_day_and_time_type"> <?php //_e( 'Date & Time', 'woocommerce-booking' );?> </label>
					</div>
				</div> -->

				<div class="booking_types-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Choose booking type for your business', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<!-- Div for Single Day and Multiple Days starts here -->
			<!-- <div id="enable_only_day_booking_section" style="margin-top:20px;<?php //echo $display_only_day; ?>" class="only_day_booking_section_flex_main" >
				<div class="only_day_booking_section_flex_child1"></div>
				<div class="only_day_booking_section_flex_child2">
					<div class="only_day_booking_section_flex_child21">
						<input type="radio" id="enable_booking_single" name="booking_enable_only_day" class="enable_only_day" value="booking_enable_single_day" <?php echo $single_days;?>></input>
						<label for="enable_booking_single"> <?php //_e( 'Single Day', 'woocommerce-booking' );?> </label>
					</div>
					<div class="only_day_booking_section_flex_child22">
						<input type="radio" id="enable_booking_multiple_days" name="booking_enable_only_day" class="enable_only_day" value="booking_enable_multiple_days" <?php echo $multiple_days;?>></input>
						<label for="enable_booking_multiple_days"> <?php //_e( 'Multiple Nights', 'woocommerce-booking' );?> </label>
					</div>
				</div>
				<div class="only_day_booking_section_flex_child3 bkap_help_class"></div>

			</div> -->

			<!-- Div for Fixed Time or Duration Bassed Time starts here -->

			<!-- <div id="enable_date_time_booking_section" style="margin-top:20px;<?php //echo $display_date_time; ?>" class="date_time_booking_section_flex_main" >

				<div class="date_time_booking_section_flex_child1"></div>

				<div class="date_time_booking_section_flex_child2">

					<div class="date_time_booking_section_flex_child21">
						<input type="radio" id="enable_fixed_time" name="booking_enable_date_time" class="enable_only_day" value="booking_enable_fixed_time" <?php echo $fixed_time;?>></input>
						<label for="enable_fixed_time"> <?php //_e( 'Fixed Time', 'woocommerce-booking' );?> </label>
					</div>

					<div class="date_time_booking_section_flex_child22">
						<input type="radio" id="enable_duration_time" name="booking_enable_date_time" class="enable_only_day" value="booking_enable_duration_time" <?php echo $duration_time;?>></input>
						<label for="enable_duration_time"> <?php //_e( 'Duration Based Time', 'woocommerce-booking' );?> </label>
					</div>

				</div>

				<div class="date_time_booking_section_flex_child3 bkap_help_class"></div>

			</div> -->

			<!-- Descrpition of the selected booking method will be display -->
			<p class="show-booking-day-description"></p>

			<hr/>

			<?php do_action( 'bkap_after_booking_method', $duplicate_of, $booking_settings ); ?>

			<div id="enable_inline_calendar_section" class="booking_options-flex-main" style="margin-top:15px;margin-bottom:15px;">

				<div class="booking_options-flex-child">
					<label for="enable_inline_calendar"> <?php esc_html_e( 'Enable Inline Calendar', 'woocommerce-booking' ); ?> </label>
				</div>

				<?php 
				$enable_inline_calendar = '';
				if ( isset( $booking_settings['enable_inline_calendar'] ) && 'on' === $booking_settings['enable_inline_calendar'] ) {
					$enable_inline_calendar = 'checked';
				}
				?>

				<div class="booking_options-flex-child">
					<label class="bkap_switch">
						<input type="checkbox" id="enable_inline_calendar" name="enable_inline_calendar" <?php echo $enable_inline_calendar;?> >
						<div class="bkap_slider round"></div>
					</label>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Enable Inline Calendar on Products Page', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<?php do_action( 'bkap_before_purchase_without_date', $duplicate_of, $booking_settings ); ?>

			<div id="purchase_wo_date_section" class="booking_options-flex-main" style="<?php echo $purchase_without_date;?>">

				<div class="booking_options-flex-child">
					<label for="booking_purchase_without_date"> <?php esc_html_e( 'Purchase without choosing a date', 'woocommerce-booking' ); ?> </label>
				</div>

				<?php
				$date_show = '';
				if ( isset( $booking_settings['booking_purchase_without_date'] ) && $booking_settings['booking_purchase_without_date'] == 'on' ) {
					$without_date = 'checked';
				} else {
					$without_date = '';
				}
				?>

				<div class="booking_options-flex-child">
					<label class="bkap_switch">
						<input type="checkbox" id="booking_purchase_without_date" name="booking_purchase_without_date" <?php echo $without_date;?> >
						<div class="bkap_slider round"></div>
					</label>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Enables your customers to purchase without choosing a date. Select this option if you want the ADD TO CART button always visible on the product page. Cannot be applied to products that require confirmation.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<?php 
				do_action( 'bkap_after_purchase_wo_date', $duplicate_of, $booking_settings );
				do_action( 'bkap_before_product_holidays', $duplicate_of, $booking_settings );
				do_action( 'bkap_after_product_holidays', $duplicate_of, $booking_settings );
			?>

			<hr style="margin-top:20px;" />

			<?php
			if ( isset( $post_type ) && $post_type === 'product' ) {
				bkap_booking_box_class::bkap_save_button( 'bkap_save_booking_options' );
			}
			?>

			<div id='general_update_notification' style='display:none;'></div>          
		</div>
		<!-- Booking Options tab ends here -->

		<div id="booking_settings" class="tstab-content" style="position: relative; display: none;">
					  
			<table class="form-table bkap-form-table">

				<?php do_action( 'bkap_before_minimum_days', $duplicate_of, $booking_settings ); ?>

				<tr id="booking_minimum_number_days_row">
					<th style="width:50%;">
						<label for="booking_minimum_number_days"><?php _e( 'Advance Booking Period (in hours)', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<?php 
						$min_days = 0;
						if ( isset( $booking_settings['booking_minimum_number_days'] ) && $booking_settings['booking_minimum_number_days'] != "" ) {
							$min_days = $booking_settings['booking_minimum_number_days'];
						}
						?>
						<input type="number" style="width:90px;" name="booking_minimum_number_days" id="booking_minimum_number_days" min="0" max="9999" value="<?php echo sanitize_text_field( $min_days, true );?>" >
					</td>
					<td>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Enable Booking after X number of hours from the current time. The customer can select a booking date/time slot that is available only after the minimum hours that are entered here. For example, if you need 12 hours advance notice for a booking, enter 12 here.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>

				<?php do_action( 'bkap_before_number_of_dates', $duplicate_of, $booking_settings ); ?>

				<tr id="booking_maximum_number_days_row">
					<th style="width:50%;">
						<label for="booking_maximum_number_days"><?php _e( 'Number of dates to choose', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<?php 
						$max_date                       = apply_filters( 'bkap_number_of_dates_to_choose', '30', $duplicate_of, $booking_settings );
						$readonly_no_of_dates_to_choose = "";

						// if custom range is added then readonly number of dates to choose field. 
						if( isset( $booking_settings[ 'booking_date_range' ] ) && $booking_settings[ 'booking_date_range' ] != "" && count( $booking_settings[ 'booking_date_range' ]) > 0 ){
							$readonly_no_of_dates_to_choose = "readonly";
						}

						if ( isset( $booking_settings[ 'booking_maximum_number_days' ] ) && $booking_settings[ 'booking_maximum_number_days' ] != "" ) {
							$max_date = $booking_settings[ 'booking_maximum_number_days' ];
						}
						?>
						<input type="number" style="width:90px;" name="booking_maximum_number_days" id="booking_maximum_number_days" min="0" max="9999" value="<?php echo sanitize_text_field( $max_date, true );?>" <?php echo $readonly_no_of_dates_to_choose; ?> >
					</td>
					<td>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'The maximum number of booking dates you want to be available for your customers to choose from. For example, if you take only 2 months booking in advance, enter 60 here.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php 
				do_action( 'bkap_after_number_of_dates', $duplicate_of, $booking_settings );
				?>
				<tr id="booking_lockout_date_row" class="multiple_days_setup" <?php echo $multiple_days_setup; ?>>
					<th style="width:50%;">
						<label for="booking_lockout_date"><?php _e( 'Maximum Bookings On Any Date', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<?php 
						$lockout_date = "";
						if ( isset( $booking_settings['booking_date_lockout'] ) ) {
							$lockout_date = $booking_settings['booking_date_lockout'];
							// sanitize_text_field( $lockout_date, true )
						} else {
							$lockout_date = "60";
						}
						?>
						<input type="number" style="width:90px;" name="booking_lockout_date" id="booking_lockout_date" min="0" max="9999" value="<?php echo sanitize_text_field( $lockout_date, true );?>" >
					</td>
					<td>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'Set this field if you want to place a limit on maximum bookings on any given date. If you can manage up to 15 bookings in a day, set this value to 15. Once 15 orders have been booked, then that date will not be available for further bookings.', 'woocommerce-booking' );?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>

				<?php do_action( 'bkap_after_lockout_date', $duplicate_of, $booking_settings ); ?>

				<tr id="booking_minimum_number_days_multiple_row" class="multiple_days_setup" <?php echo $multiple_days_setup; ?>>
					<th style="width:50%;">
						<label for="booking_minimum_number_days_multiple"><?php _e( 'Minimum number of nights to book', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<?php 
						$minimum_day_multiple = "";
						if ( isset( $booking_settings[ 'booking_minimum_number_days_multiple' ] ) && $booking_settings[ 'booking_minimum_number_days_multiple' ] != "" ) {
							$minimum_day_multiple = $booking_settings[ 'booking_minimum_number_days_multiple' ];
						} else {
							$minimum_day_multiple = "0";
						}   
						?>
						<input type="number" style="width:90px;" name="booking_minimum_number_days_multiple" id="booking_minimum_number_days_multiple" min="0" max="9999" value="<?php echo $minimum_day_multiple;?>" >
					</td>
					<td>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'The minimum number of booking days you want to book for multiple days booking. For example, if you take minimum 2 days of booking, add 2 in the field here.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>

				<?php do_action( 'bkap_after_minimum_days_multiple', $duplicate_of, $booking_settings ); ?>

				<tr id="booking_maximum_number_days_multiple_row" class="multiple_days_setup" <?php echo $multiple_days_setup; ?>>
					<th style="width:50%;">
						<label for="booking_maximum_number_days_multiple"><?php _e( 'Maximum number of nights to book', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<?php 
						$maximum_day_multiple = '';
						if ( isset( $booking_settings['booking_maximum_number_days_multiple'] ) && $booking_settings[ 'booking_maximum_number_days_multiple' ] != "" ) {
							$maximum_day_multiple = $booking_settings['booking_maximum_number_days_multiple'];
						} else {
							$maximum_day_multiple = "365";
						}
						?>
						<input type="number" style="width:90px;" name="booking_maximum_number_days_multiple" id="booking_maximum_number_days_multiple" min="0" max="9999" value="<?php echo $maximum_day_multiple;?>" >
					</td>
					<td>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e( 'The maximum number of booking days you want to book for multiple days booking. For example, if you take maximum 60 days of booking, add 60 in the field here.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>

				<?php do_action( 'bkap_after_maximum_days_multiple', $duplicate_of, $booking_settings ); ?>

			</table>

			<hr/>

			<?php
			// call function to display the weekdays and availablility setup.
			if ( 'checked' == $multiple_days || $booking_type == "duration_time" ) {
				bkap_booking_box_class::bkap_get_weekdays_html( $duplicate_of, false, true, $booking_settings, $individual_booking_settings, $has_defaults );
			} else {
				bkap_booking_box_class::bkap_get_weekdays_html( $duplicate_of, true, true, $booking_settings, $individual_booking_settings, $has_defaults );
			}
			?>

			<!-- Descrpition of the per night price for multiple days -->
			<p class="show-multiple-day-per-night-price-description"></p>

			<hr style="clear:both;" />

			<?php
			// add specific setup.
			bkap_booking_box_class::bkap_get_specific_html( $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults ); 
			?>

			<div style="clear: both;"></div>

			<?php
			// add date and time setup.
			bkap_booking_box_class::bkap_get_date_time_html( $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults ); 
			?>

			<?php
			// These hooks have been moved here to ensure no existing functionality for any client is broken.
			// in case if they hv added custom fields.
			do_action( 'bkap_before_enable_multiple_days', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_after_lockout_time', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_before_range_selection_radio', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_before_booking_start_date_range', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_before_booking_end_date_range', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_before_recurring_date_range', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_after_recurring_date_range', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			do_action( 'bkap_after_recurring_years_range', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults );
			?>

			<hr style="margin-top:20px"/>

			<?php
			if ( isset( $post_type ) && 'product' === $post_type ) {
				bkap_booking_box_class::bkap_save_button( 'bkap_save_settings' );
			}
			?>

			<div id='availability_update_notification' style='display:none;'></div>                    
		</div>

		<?php do_action( 'bkap_after_listing_enabled', $duplicate_of, $booking_settings, $individual_booking_settings, $has_defaults ); ?>

	</div>
</div>
