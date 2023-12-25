<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Multple Dates.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Multiple Dates
 * @category Classes
 * @class    Bkap_Multidates
 */

if ( ! class_exists( 'Bkap_Multidates' ) ) {

	/**
	 * Class Bkap_Multidates.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Multidates {

		/**
		 * Bkap_Plugin_Meta constructor.
		 */
		public function __construct() {
			add_action( 'bkap_before_add_to_cart_button', array( $this, 'bkap_multidates_add_days_button' ), 10, 2 );
			add_action( 'bkap_after_booking_box_form', array( $this, 'bkap_multiidates_selected_bookings_display' ), 9, 2 );
			add_action( 'bkap_after_booking_method', array( $this, 'bkap_multidates_fixed_range_section' ), 10, 2 );

			add_action( 'bkap_before_availability_message', array( $this, 'bkap_multidates_dates_selection_msg' ), 10, 2 );
		}

		/**
		 * Multidates Selection Message on Front end for Fixed or Range of Days
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Booking Settings.
		 *
		 * @since 5.3.0
		 */
		public static function bkap_multidates_dates_selection_msg( $product_id, $booking_settings ) {
			$booking_type        = get_post_meta( $product_id, '_bkap_booking_type', true );
			$total_stock_message = '';

			switch ( $booking_type ) {
				case 'multidates':
				case 'multidates_fixedtime':
					$multidates_type = $booking_settings['multidates_type'];

					switch ( $multidates_type ) {
						case 'fixed':
							$multidates_fixed_dates = $booking_settings['multidates_fixed_number'];
							$fixed_multidates_msg   = __( 'Select FIXED Dates for booking.', 'woocommerce-booking' );
							$total_stock_message    = str_replace(
								array(
									'FIXED',
								),
								array(
									$multidates_fixed_dates,
								),
								$fixed_multidates_msg
							);

							break;
						case 'range':
							$min_max_multidates_msg = __( get_option( 'book_multidates_min_max_selection_msg' ), 'woocommerce-booking' );
							$min                    = $booking_settings['multidates_range_min'];
							$max                    = $booking_settings['multidates_range_max'];
							$total_stock_message    = str_replace(
								array(
									'MIN',
									'MAX',
								),
								array(
									$min,
									$max,
								),
								$min_max_multidates_msg
							);
							break;
					}

					$total_stock_message = apply_filters( 'bkap_multidates_selection_msg', $total_stock_message, $product_id, $booking_settings );
					if ( ( is_product() || is_account_page() ) && ! isset( $_GET['post'] ) ) {
					?>
					<div id="bkap_multidates_msg" name="bkap_multidates_msg" class="bkap_multidates_msg" style="<?php if ( is_account_page() ) { echo 'display: none;'; } ?>">
						<?php echo __( $total_stock_message, 'woocommerce-booking' ); ?>
					</div>
					<?php
					}
					break;
			}
		}

		/**
		 * Adding Multidates options in Booking Meta Box.
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Booking Settings.
		 *
		 * @since 5.3.0
		 */
		public static function bkap_multidates_fixed_range_section( $product_id, $booking_settings ) {

			$multidates = 'display:none';
			if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'multidates' === $booking_settings['booking_enable_multiple_day'] ) {
				$multidates = '';
			}

			$fixed_range = array(
				'fixed' => __( 'Fixed dates', 'woocommerce-booking' ),
				'range' => __( 'Range based', 'woocommerce-booking' ),
			);

			$multidates_type = 'fixed';
			if ( isset( $booking_settings['multidates_type'] ) && '' !== $booking_settings['multidates_type'] ) {
				$multidates_type = $booking_settings['multidates_type'];
			}

			$display_fixed = 'display:none';
			$display_range = 'display:none';
			if ( 'fixed' === $multidates_type ) {
				$display_fixed = '';
			} else {
				$display_range = '';
			}

			$multidates_fixed_number = 2;
			if ( isset( $booking_settings['multidates_fixed_number'] ) && '' !== $booking_settings['multidates_fixed_number'] ) {
				$multidates_fixed_number = ( $booking_settings['multidates_fixed_number'] >= 2 ) ? $booking_settings['multidates_fixed_number'] : 2;
			}

			$multidates_range_min = '';
			if ( isset( $booking_settings['multidates_range_min'] ) && '' !== $booking_settings['multidates_range_min'] ) {
				$multidates_range_min = $booking_settings['multidates_range_min'];
			}

			$multidates_range_max = '';
			if ( isset( $booking_settings['multidates_range_max'] ) && '' !== $booking_settings['multidates_range_max'] ) {
				$multidates_range_max = $booking_settings['multidates_range_max'];
			}

			?>
			<div id="bkap_multidate_options" style="<?php echo esc_attr( $multidates ); ?>">
			<div id="enable_multidate_fixed_range_section" class="booking_options-flex-main" style="margin-top:15px;margin-bottom:15px;">

				<div class="booking_options-flex-child">
					<label for="bkap_multidates_fixed_range"> <?php esc_html_e( 'Type of Selection', 'woocommerce-booking' ); ?> </label>
				</div>
				<div class="booking_options-flex-child">
					<select id="bkap_multidates_fixed_range" name="bkap_multidates_fixed_range">
						<?php foreach ( $fixed_range as $key => $value ) { ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php echo selected( $multidates_type, $key, false ); ?>><?php echo esc_html( $value ); ?></option>
						<?php } ?>
					</select>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Allow customers to choose fixed number of days or variable days based on a range.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<div id="enable_multidate_fixed_section" class="booking_options-flex-main" style="<?php echo esc_attr( $display_fixed ); ?>">

				<div class="booking_options-flex-child">
					<label for="bkap_multidates_fixed_number"> <?php esc_html_e( 'Numbers of dates', 'woocommerce-booking' ); ?> </label>
				</div>
				<div class="booking_options-flex-child">
					<input type="number" style="width:90px;" name="bkap_multidates_fixed_number" id="bkap_multidates_fixed_number" min="2" max="9999" onfocusout="bkap_field_validation( this, 2, 'Numbers of dates' );" value="<?php esc_attr_e( $multidates_fixed_number ); ?>">
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Fixed number of dates the customer has to select while placing a booking. The value to this option should be 2 or higher.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<div id="enable_multidate_range_section" class="booking_options-flex-main" style="<?php echo esc_attr( $display_range ); ?>">

				<div class="booking_options-flex-child">
					<label> <?php esc_html_e( 'Set a range', 'woocommerce-booking' ); ?> </label>
				</div>
				<div class="booking_options-flex-child">
					<table>
						<tr>
							<td>
								<label><?php esc_html_e( 'Min Dates:', 'woocommerce-booking' ); ?></label>
							</td>
							<td>
								<input type="number" style="width:90px;" name="bkap_multidates_range_min" id="bkap_multidates_range_min" min="1" max="9999" value="<?php esc_attr_e( $multidates_range_min ); ?>">
							</td>
						</tr>
						<tr>
							<td>
								<label><?php esc_html_e( 'Max Dates:', 'woocommerce-booking' ); ?></label>
							</td>
							<td>
								<input type="number" style="width:90px;" name="bkap_multidates_range_max" id="bkap_multidates_range_max" min="1" max="9999" value="<?php esc_attr_e( $multidates_range_max ); ?>">
							</td>
						</tr>
					</table>
				</div>

				<div class="booking_options-flex-child bkap_help_class">
					<img class="help_tip" width="16" height="16"  data-tip="<?php esc_attr_e( 'Set minimum and maximum dates the customer can select while placing a booking.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				</div>

			</div>
			<hr>
		</div>
			<?php
		}

		/**
		 * Add Days Button.
		 *
		 * @param array $booking_settings Booking Settings.
		 * @param int   $product_id Product ID.
		 * @since 5.3.0
		 */
		public static function bkap_multidates_add_days_button( $booking_settings, $product_id ) {
			$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );
			if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {

				$show = apply_filters( 'bkap_show_add_day_button', true, $product_id, $booking_settings );
				if ( ! isset( $_GET['post'] ) && $show ) {
					?>
				<div id="bkap-multidates-button-msg-div" style="<?php if ( is_account_page() ) { echo 'display: none;'; } ?>">
					<button type="button" id="bkap-add-days" class="button-primary" onclick='bkap_add_selected_bookings()' disabled>
					<i class="fa fa-plus" aria-hidden="true"></i>&nbsp<?php esc_html_e( 'Add Day', 'woocommerce-booking' ); ?>
					</button>
					<div id="bkap_multidates_error" name="bkap_multidates_error"></div>
				</div>
					<?php
				}
			}
		}

		/**
		 * Add Multidates Selection Information Box on front end product page.
		 *
		 * @param array $booking_settings Booking Settings.
		 * @param int   $booking_details Array of Additonal Information of product.
		 * @since 5.3.0
		 */
		public static function bkap_multiidates_selected_bookings_display( $booking_settings, $booking_details ) {

			$product_id            = $booking_details['product_id'];
			$booking_type          = get_post_meta( $product_id, '_bkap_booking_type', true );
			$summary_heading       = __( 'Booking Summary', 'woocommerce-booking' );
			$total_booking_price   = __( 'Total Booking Price:', 'woocommerce-booking' );
			$booking_parameters    = array(
				'product_id'            => $product_id,
				'booking_settings'      => $booking_settings,
				'booking_type'          => $booking_type,
				'summary_heading'       => $summary_heading,
				'total_booking_price'   => $total_booking_price,
			);

			if ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() ) {
				$booking_parameters['total_remaining_price'] = __( 'Total Remaining Price:', 'woocommerce-booking' );
				$booking_parameters['final_total_price']     = __( 'Final Total Price:', 'woocommerce-booking' );
			}

			if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {

				wc_get_template(
					'bookings/bkap-multidates-box.php',
					$booking_parameters,
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);
				?>
				<?php
			}
		}
	}
	$bkap_multidates = new Bkap_Multidates();
}
