<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Booking meta box in Add/Edit Product page.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Booking-Box
 * @category Classes
 */

require_once 'bkap-common.php';
require_once 'product-calendar-sync-settings.php';

if ( ! class_exists( 'bkap_booking_box_class' ) ) {

	/**
	 * Class for Booking meta box
	 *
	 * @class bkap_booking_box_class
	 */

	class bkap_booking_box_class {

		/**
		 * Default constructor
		 *
		 * @since 1.0
		 */

		public function __construct() {

			add_action( 'add_meta_boxes', array( $this, 'bkap_booking_box' ), 10 ); // Display Booking Box on Add/Edit Products Page
			add_action( 'woocommerce_process_product_meta', array( $this, 'bkap_process_bookings_box' ), 1, 2 ); // Processing Bookings

			add_action( 'woocommerce_duplicate_product', array( &$this, 'bkap_product_duplicate' ), 10, 2 );

			// custom post type meta boxes
			add_action( 'add_meta_boxes', array( $this, 'bkap_add_meta_boxes' ), 10, 1 );
		}

		/**
		 * Include custom meta boxes templates for create Booking page
		 *
		 * @since 4.1.0
		 */

		public static function bkap_add_meta_boxes() {

			$meta_boxes = array(
				include BKAP_PLUGIN_PATH . '/templates/meta-boxes/class-bkap-customer-meta-box.php',
				include BKAP_PLUGIN_PATH . '/templates/meta-boxes/class-bkap-details-meta-box.php',
				include BKAP_PLUGIN_PATH . '/templates/meta-boxes/class-bkap-save-meta-box.php',
				include BKAP_PLUGIN_PATH . '/templates/meta-boxes/class-bkap-resource-details-meta-box.php',
			);

			foreach ( $meta_boxes as $meta_box ) {
				foreach ( $meta_box->post_types as $post_type ) {
					add_meta_box(
						$meta_box->id,
						$meta_box->title,
						array( $meta_box, 'meta_box_inner' ),
						$post_type,
						$meta_box->context,
						$meta_box->priority
					);
				}
			}
		}

		/**
		 * This function updates the booking settings for each product in the wp_postmeta table in the database.
		 * It will be called when update / publish button clicked on admin side.
		 *
		 * @param integer $post_id - Post ID
		 * @param array   $post - Contains the data
		 *
		 * @hook woocommerce_process_product_meta
		 * @since 1.0
		 **/

		public static function bkap_process_bookings_box( $post_id, $post ) {

			$duplicate_of     = bkap_common::bkap_get_product_id( $post_id );
			$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

			$booking_settings = (array) apply_filters( 'bkap_save_product_settings', $booking_settings, $duplicate_of );
			update_post_meta( $duplicate_of, 'woocommerce_booking_settings', $booking_settings );
		}

		/**
		 * This function adds a meta box for booking settings on product page.
		 *
		 * @hook add_meta_boxes
		 * @since 1.0
		 **/

		public static function bkap_booking_box() {

			add_meta_box(
				'woocommerce-booking',
				__( 'Booking', 'woocommerce-booking' ),
				array( 'bkap_booking_box_class', 'bkap_meta_box' ),
				'product',
				'normal',
				'core'
			);
		}

		/**
		 * Displays the settings for the product in the Booking meta box.
		 *
		 * @global object $post WP_Post
		 * @global object $wpdb Global wpdb object
		 *
		 * @since 1.0
		 **/
		public static function bkap_meta_box() {

			global $post;

			$duplicate_of = bkap_common::bkap_get_product_id( $post->ID );

			$booking_settings            = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
			$post_type                   = get_post_type( $duplicate_of );
			$individual_booking_settings = array();
			$has_defaults                = false;

			if ( ( isset( $post->filter ) && 'raw' == $post->filter ) && '' == $booking_settings ) {
				$booking_settings            = get_option( 'bkap_default_booking_settings', array() );
				$individual_booking_settings = get_option( 'bkap_default_individual_booking_settings', array() );
				$has_defaults                = ( ! empty( $individual_booking_settings ) );
			}

			$product_info['duplicate_of']                = $duplicate_of;
			$product_info['booking_settings']            = $booking_settings;
			$product_info['post_type']                   = $post_type;
			$product_info['individual_booking_settings'] = $individual_booking_settings;
			$product_info['has_defaults']                = $has_defaults;

			self::bkap_meta_box_template( $product_info );
		}

		/**
		 * This function will display the Settings in the Booking Meta Box.
		 *
		 * @param array $product_info Array os Booking Settings information.
		 * @since 4.6.0
		 */
		public static function bkap_meta_box_template( $product_info ) {

			wc_get_template(
				'html-bkap-booking-meta-box.php',
				$product_info,
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/'
			);
		}

		/**
		 * This function will print a save button in each of the tabs.
		 * It needs the callback JS function as the parameter
		 *
		 * @param str $save_fn - Name of the callback JS function.
		 * @since 4.6.0
		 */

		static function bkap_save_button( $save_fn ) {

			$class    = $save_fn;
			$save_fn .= '()';
			?>
			<div style="width:100%;margin-left: 40%;">
				<button type="button" class="button-primary bkap-primary <?php echo $class; ?>" onclick="<?php echo $save_fn; ?>" >
					<div id="ajax_img" class="ajax_img" style="display:none"><img src="<?php echo plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif'; ?>"></div>
					<i class="fas fa-save fa-lg"></i>&nbsp;&nbsp;&nbsp;<?php esc_html_e( 'Save Changes', 'woocommerce-booking' ); ?>
				</button>
			</div>
			<?php
		}

		/**
		 * The function adds the html for the Weekdays UI
		 * which allows the admin to enable/disable weekdays,
		 * set lockout and price for the same.
		 *
		 * @param int                    $product_id
		 * @param boolean                $lockout
		 * @param boolean                $price
		 * @param array booking settings
		 * @global array $bkap_weekdays Array of weekdays
		 * @since 4.0.0
		 */

		static function bkap_get_weekdays_html( $product_id, $lockout = false, $price = true, $booking_settings = array(), $default_booking_settings = array(), $defaults = false ) {

			global $bkap_weekdays;

			if ( isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) { // bookable product
				$display            = '';
				$recurring_weekdays = ( isset( $booking_settings['booking_recurring'] ) ) ? $booking_settings['booking_recurring'] : array();
				$recurring_lockout  = ( isset( $booking_settings['booking_recurring_lockout'] ) ? $booking_settings['booking_recurring_lockout'] : array() );

				$booking_special_prices = bkap_get_post_meta_data( $product_id, '_bkap_special_price', $default_booking_settings, $defaults );
				$special_prices         = array();

				/** Create a list of the special prices as day and price */
				if ( is_array( $booking_special_prices ) && count( $booking_special_prices ) > 0 ) {

					foreach ( $booking_special_prices as $special_key => $special_value ) {
						$weekday_set = $special_value['booking_special_weekday'];

						if ( $weekday_set != '' ) {
							$special_prices[ $weekday_set ] = $special_value['booking_special_price'];
						}
					}
				}
			} else { // non-bookable product.
				$display            = 'display:none;';
				$recurring_weekdays = array();
				$special_prices     = array();
				$recurring_lockout  = array();
			}

			?>

		<div id="set_weekdays" class="weekdays_flex_main" style="margin-bottom:20px;width:100%;float:left; <?php echo $display; ?>" >
			<div class="weekdays_flex_child" >
				<div class="weekdays_flex_child_1 bkap_weekdays_heading" style="max-width:27%;"><b><?php _e( 'Weekday', 'woocommerce-booking' ); ?></b></div>
				<div class="weekdays_flex_child_2 bkap_weekdays_heading" style="max-width:20%;"><b><?php _e( 'Bookable', 'woocommerce-booking' ); ?></b></div>

				<?php
					$mutiple_display = '';

				if ( ! $lockout ) {
					$mutiple_display = 'display:none;';
				}
				?>
				<div class="weekdays_flex_child_3 bkap_weekdays_heading" style="max-width:26%;<?php echo $mutiple_display; ?>"><b><?php _e( 'Maximum bookings', 'woocommerce-booking' ); ?></b></div>


				<?php
				if ( $price ) {
					$currency_symbol = get_woocommerce_currency_symbol();
					?>
				<div class="weekdays_flex_child_4 bkap_weekdays_heading" ><b><?php _e( "Price ($currency_symbol)", 'woocommerce-booking' ); ?> </b></div>
				<?php } ?>
			</div>

				<?php
				$i = 0;
				foreach ( $bkap_weekdays as $w_key => $w_value ) {
					?>
				<div class="weekdays_flex_child">
					<div class="weekdays_flex_child_1" style="padding-top:5px; max-width:27%; float:left;"><?php echo $w_value; ?></div>

					<?php
					$weekday_status = 'checked';
					$fields_status  = '';
					if ( isset( $recurring_weekdays[ $w_key ] ) && '' == $recurring_weekdays[ $w_key ] ) {
						$weekday_status = '';
						$fields_status  = 'disabled="disabled"';
					}

					if ( ! $lockout ) {
						$fields_status = 'disabled="disabled"';
					}

					?>
					<div class="weekdays_flex_child_2" style="padding-top:5px; max-width:20%; float:left;">
						<label class="bkap_switch">
							<input id="<?php echo $w_key; ?>" type="checkbox" name="<?php echo $w_value; ?>" <?php echo $weekday_status; ?> >
							<div class="bkap_slider round"></div>
						</label>

					</div>

					<?php
						$weekday_lockout = isset( $recurring_lockout[ $w_key ] ) ? $recurring_lockout[ $w_key ] : '';
					?>
					<div class="weekdays_flex_child_3" style="padding-top:5px; min-width:26%;<?php echo $mutiple_display; ?>"> <input style="float:left;" type="number" id="weekday_lockout_<?php echo $i; ?>" name="day_lockout" step="1" onkeypress="return bkap_only_number( event )" min="0" max="9999" placeholder="Max bookings" value="<?php echo $weekday_lockout; ?>" <?php echo $fields_status; ?>/></div>


					<?php
					if ( $price ) {
						$special_price = '';
						if ( is_array( $special_prices ) && count( $special_prices ) > 0 && array_key_exists( $w_key, $special_prices ) ) {
							$special_price = $special_prices[ $w_key ];
						}
						$special_price_str = __( 'Special Price', 'woocommerce-booking' );
						?>
					<div class="weekdays_flex_child_4" style="padding-top:5px;"> <input style="width:95px;" type="text" class="wc_input_price" id="weekday_price_<?php echo $i; ?>" name="day_price" min="0" placeholder="<?php echo $special_price_str; ?>" value="<?php echo $special_price; ?>"/> </div>
					<?php } ?>
				</div>

					<?php
					$i++;
				}
				?>
		</div>

			<?php
		}

		/**
		 * Adds the specific dates availability checkbox and
		 * the table for the same.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings
		 *
		 * @since 4.0.0
		 */

		static function bkap_get_specific_html( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$specific_date_checkbox = '';
			$specific_date_table    = '';

			$display                = 'display:block;';
			$specific_date_checkbox = '';
			$specific_date_table    = 'display:none;';

			if ( isset( $booking_settings['booking_specific_booking'] ) && $booking_settings['booking_specific_booking'] == 'on' ) {
				$specific_date_table    = 'display:block;';
				$specific_date_checkbox = 'checked';
			}

			?>
			<div style="clear: both;" ></div>

			<div class="specific_date_title" style="display:flex;width:100%;margin-top:20px;">
				<div>
				  <b><?php _e( 'Set Availability by Dates/Months', 'woocommerce-booking' ); ?></b>
				</div>
				<div style="margin-left: 10px">
					<label class="bkap_switch">
					<input title="Select one of booking type for enable this." type="checkbox" name="specific_date_checkbox" id="specific_date_checkbox"  <?php echo $specific_date_checkbox; ?>>
					<div class="bkap_slider round"></div>
					</label>
				</div>
			</div>

			<div style="clear: both;" ></div>
			<!-- Below is the div to display table for adding specific date range and other ranges -->

			<div class="specific_date" style="<?php echo $specific_date_table; ?>">
				<table class="specific">
					<?php self::bkap_get_specific_heading_html( $product_id ); ?>
					<?php self::bkap_get_specific_default_row_html( $product_id, $booking_settings ); ?>
					<?php self::bkap_get_specific_row_to_display_html( $product_id, $booking_settings, $default_booking_settings, $defaults ); ?>
					<tr style="padding:5px; border-top:2px solid #eee">
					   <td colspan="4" style="border-right: 0px;"><i><small><?php _e( 'Create custom ranges, holidays and more here.', 'woocommerce-booking' ); ?></small><i></td>
					   <td colspan="2" align="right" style="border-left: none;"><button type="button" class="button-primary bkap_add_new_range"><?php _e( 'Add New Range', 'woocommerce-booking' ); ?></button></td>
					</tr>
				</table>
		   </div>
			<?php
		}

		/**
		 * Prints the table headers for the 'Set Availability by Dates/Months'
		 * which allows the admin to enable/disable weekdays, set lockout and
		 * price for the same.
		 *
		 * @param $product_id - Product ID
		 * @since 4.0.0
		 */
		static function bkap_get_specific_heading_html( $product_id ) {

			$heading = apply_filters(
				'bkap_get_specific_heading_html',
				array(
					'range_type'             => array(
						'style' => 'width:20%',
						'data'  => __( 'Range Type', 'woocommerce-booking' ),
					),
					'from'                   => array(
						'style' => 'width:20%',
						'data'  => __( 'From', 'woocommerce-booking' ),
					),
					'to'                     => array(
						'style' => 'width:20%',
						'data'  => __( 'To', 'woocommerce-booking' ),
					),
					'bookable'               => array(
						'style' => 'width:10%',
						'data'  => __( 'Bookable', 'woocommerce-booking' ),
					),
					'max_booking_no_of_year' => array(
						'style' => 'border-right:0px;width:25%',
						'data'  => __( 'Max bookings/<br>No. of Years', 'woocommerce-booking' ),
					),
				)
			);

			?>

			<tr>
				<?php
				foreach ( $heading as $key => $value ) {
					printf( '<th style="%s" id="%s">%s</th>', $value['style'], 'specific_heading_' . $key, $value['data'] );
				}
				?>
			<th style="border-left:0px;"></th>
			</tr>
			<?php
		}


		/**
		 * Prints the default row for the 'Set Availability by Dates/Months'
		 * which allows the admin to enable/disable weekdays, set lockout and
		 * price for the same.
		 * This row is used to add new rows to the table.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Existing Booking Settings
		 *
		 * @global array $bkap_month array of months
		 * @global array $bkap_dates_months_availability array of months
		 * @since 4.0.0
		 */

		static function bkap_get_specific_default_row_html( $product_id, $booking_settings ) {
			global $bkap_months;
			global $bkap_dates_months_availability;

			?>

			<!-- We are fetching below tr when add new range is clicked -->
			<tr class="added_specific_date_range_row" style="display: none;">
			   <td>
					<select style="width:100%;" id="range_dropdown" >
					   <?php
						foreach ( $bkap_dates_months_availability as $d_value => $d_name ) {
							printf( "<option value='%s'>%s</option>\n", $d_value, $d_name );
						}
						?>
					</select>
			   </td>


				   <!-- From Custom-->
				   <td class="date_selection_textbox1" style="width:20%;">
						<div class="fake-input">
							<input type="text" id="datepicker_textbox1" class="datepicker_start_date date_selection_textbox" style="width:100%;" />
							<img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="custom_checkin_cal" width="15" height="15" />
						</div>
				   </td>
				   <!-- To Custom-->
				   <td class="date_selection_textbox2">
						<div class="fake-input" >
							<input type="text" id="datepicker_textbox2" class="datepicker_end_date date_selection_textbox" style="width:100%;" />
							<img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="custom_checkout_cal" width="15" height="15" />
						</div>
				   </td>

				   <!-- Specific Date Textarea -->
				   <td class="date_selection_textbox3" colspan="2" style="display:none;" >
						 <div class="fake-textarea" >
							 <textarea id="textareamultidate_cal1" class="textareamultidate_cal" rows="1" col="30" style="width:100%;height:auto;"></textarea>
							 <img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="specific_date_multidate_cal" class="bkap_multiple_datepicker_cal_image" width="15" height="15" />
						 </div>
				   </td>

				   <!-- From Month-->
				   <td class="date_selection_textbox4" style="display:none;">
						<select id="bkap_availability_from_month" style="width:100%;">
							<?php
							foreach ( $bkap_months as $m_number => $m_name ) {
								printf( "<option value='%d'>%s</option>\n", $m_number, $m_name );
							}
							?>
						</select>
				   </td>
				   <!-- To Month-->
				   <td class="date_selection_textbox5" style="display:none;">
						<select id="bkap_availability_to_month" style="width:100%;">
							<?php
							foreach ( $bkap_months as $m_number => $m_name ) {
								printf( "<option value='%d'>%s</option>\n", $m_number, $m_name );
							}
							?>
						</select>
				   </td>

				   <!-- Holiday Textarea -->
				   <td class="date_selection_textbox6" colspan="2" style="display:none;" >
						 <div class="fake-textarea" >
							 <textarea id="textareamultidate_cal2" class="textareamultidate_cal" rows="1" col="30" style="width:100%;height:auto;"></textarea>
							 <img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="holiday_multidate_cal" class="bkap_multiple_datepicker_cal_image" width="15" height="15" />
						 </div>
				   </td>

				   <td style="padding-left:2%;">
						<div class="bkap_popup">
						<span class="bkap_popuptext" id="bkap_myPopup"></span>
						<label class="bkap_switch">

						  <input id="bkap_bookable_nonbookable" type="checkbox" name="bkap_bookable_nonbookable">
						  <div class="bkap_slider round"></div>
						</label>

						<div>
				   </td>

				   <td class="bkap_lockout_column_data_1" >
					<input id="bkap_number_of_year_to_recur_custom_range" title="Please enter number of years you want to recur this custom range" type="number" min="0" style="width:65%;font-size:11px;margin-left: 15%;" placeholder="No. of Years">
					&nbsp;
					<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
				   </td>
				   <td class="bkap_lockout_column_data_2"  style="display:none;">
						<input id="bkap_number_of_year_to_recur_holiday" title="Please enter number of years you want to recur selected holidays" type="number" min="0" style="width:65%;font-size:11px;margin-left: 15%;" placeholder="No. of Years">
						&nbsp;
						<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
				   </td>
				   <td class="bkap_lockout_column_data_3"  style="display:none;">
						<input id="bkap_number_of_year_to_recur_month" title="Please enter number of years you want to recur selected month" type="number" min="0" style="width:65%;font-size:11px;margin-left: 15%;" placeholder="No. of Years">
						&nbsp;
					<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
				   </td>
				   <td class="bkap_lockout_column_data_4" style="display:none;">
						<input id="bkap_specific_date_lockout" title="This field is for maximum booking for selected specific dates." type="number" min="0" onkeypress="return bkap_only_number( event )" style="width:47%;font-size:11px;" placeholder="Max bookings">
						<input id="bkap_specific_date_price" class="wc_input_price" title="This field is for price of selected specific dates." type="number" min="0" style="width:45%;float:right;font-size:11px;" placeholder="Price">
				   </td>

				   <td id="bkap_close" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
		   </tr>
		   <!-- We are fetching above tr when add new range is clicked -->

			<?php
		}

		/**
		 * Prints the existing data for the 'Set Availability by Dates/Months'
		 * which allows the admin to enable/disable weekdays, set lockout and
		 * price for the same.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings
		 * @global array $bkap_month array of months
		 * @global array $bkap_dates_months_availability array of months
		 *
		 * @since 4.0.0
		 */

		static function bkap_get_specific_row_to_display_html( $product_id, $booking_settings, $default_booking_settings, $defaults ) {
			global $bkap_months;
			global $bkap_dates_months_availability;

			$booking_type = $booking_custom_ranges = $booking_holiday_ranges = $booking_month_ranges = $booking_specific_dates = $booking_special_prices = $booking_product_holiday = array();

			// Fetching data from post meta.
			$booking_type            = bkap_get_post_meta_data( $product_id, '_bkap_booking_type', $default_booking_settings, $defaults );
			$booking_custom_ranges   = bkap_get_post_meta_data( $product_id, '_bkap_custom_ranges', $default_booking_settings, $defaults );
			$booking_holiday_ranges  = bkap_get_post_meta_data( $product_id, '_bkap_holiday_ranges', $default_booking_settings, $defaults );
			$booking_month_ranges    = bkap_get_post_meta_data( $product_id, '_bkap_month_ranges', $default_booking_settings, $defaults );
			$booking_specific_dates  = bkap_get_post_meta_data( $product_id, '_bkap_specific_dates', $default_booking_settings, $defaults );
			$booking_special_prices  = bkap_get_post_meta_data( $product_id, '_bkap_special_price', $default_booking_settings, $defaults );
			$booking_product_holiday = isset( $booking_settings['booking_product_holiday'] ) ? $booking_settings['booking_product_holiday'] : '';

			// sorting holidays in chronological order.
			if ( is_array( $booking_product_holiday ) && count( $booking_product_holiday ) > 0 ) {
				uksort( $booking_product_holiday, 'bkap_orderby_date_key' );
			}

			// Calculating counts for ranges.
			$count_custom_ranges  = $booking_custom_ranges != '' ? count( $booking_custom_ranges ) : 0;
			$count_holiday_ranges = $booking_holiday_ranges != '' ? count( $booking_holiday_ranges ) : 0;
			$count_month_ranges   = $booking_month_ranges != '' ? count( $booking_month_ranges ) : 0;
			$count_specific_dates = $booking_specific_dates != '' ? count( $booking_specific_dates ) : 0;

			$count_special_prices  = $booking_special_prices != '' ? count( $booking_special_prices ) : 0;
			$count_product_holiday = $booking_product_holiday != '' ? count( $booking_product_holiday ) : 0;

			$array_of_all_added_ranges = array();
			$bkap_range_count          = 0;
			$special_prices            = array();

			// Modify the special prices array
			if ( isset( $booking_special_prices ) && $count_special_prices > 0 ) {

				foreach ( $booking_special_prices as $s_key => $s_value ) {

					if ( isset( $s_value['booking_special_date'] ) && $s_value['booking_special_date'] != '' ) {
						$s_date                    = date( 'j-n-Y', strtotime( $s_value['booking_special_date'] ) );
						$special_prices[ $s_date ] = $s_value['booking_special_price'];
					}
				}
			}

			if ( isset( $booking_custom_ranges ) && $count_custom_ranges > 0 ) {

				for ( $bkap_range = 0; $bkap_range < $count_custom_ranges; $bkap_range++ ) {
					$array_of_all_added_ranges[ $bkap_range ]['bkap_type']           = 'custom_range';
					$array_of_all_added_ranges[ $bkap_range ]['bkap_start']          = $booking_custom_ranges[ $bkap_range ]['start'];
					$array_of_all_added_ranges[ $bkap_range ]['bkap_end']            = $booking_custom_ranges[ $bkap_range ]['end'];
					$array_of_all_added_ranges[ $bkap_range ]['bkap_years_to_recur'] = $booking_custom_ranges[ $bkap_range ]['years_to_recur'];
					$bkap_range_count++;
				}
			}

			if ( isset( $booking_product_holiday ) && $count_product_holiday > 0 ) {

				foreach ( $booking_product_holiday as  $booking_product_holiday_keys => $booking_product_holiday_values ) {
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_type']           = 'holidays';
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_holiday_date']   = $booking_product_holiday_keys;
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_years_to_recur'] = $booking_product_holiday_values;
					$bkap_range_count++;
				}
			}

			if ( isset( $booking_month_ranges ) && $count_month_ranges > 0 ) {

				for ( $bkap_range = 0; $bkap_range < $count_month_ranges; $bkap_range++ ) {
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_type']           = 'range_of_months';
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_start']          = $booking_month_ranges[ $bkap_range ]['start'];
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_end']            = $booking_month_ranges[ $bkap_range ]['end'];
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_years_to_recur'] = $booking_month_ranges[ $bkap_range ]['years_to_recur'];
					$bkap_range_count++;
				}
			}

			if ( isset( $booking_specific_dates ) && $count_specific_dates > 0 ) {

				foreach ( $booking_specific_dates as  $booking_specific_dates_keys => $booking_specific_dates_values ) {
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_type']             = 'specific_dates';
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_specific_date']    = $booking_specific_dates_keys;
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_specific_lockout'] = $booking_specific_dates_values;
					// check if that date has a special price set
					$array_of_all_added_ranges[ $bkap_range_count ]['bkap_specific_price'] = ( isset( $special_prices[ $booking_specific_dates_keys ] ) ) ? $special_prices[ $booking_specific_dates_keys ] : '';
					$bkap_range_count++;
				}
			}

			// if the booking type is multiple day, then no data is present in specific dates, so loop through the special prices
			/*
			if ( 'multiple_days' == $booking_type ) {

			   if ( is_array( $special_prices ) && count( $special_prices ) > 0 ) {

				   foreach ( $special_prices as $sp_date => $sp_price ) {
					   $array_of_all_added_ranges[$bkap_range_count]['bkap_type']            = "specific_dates";
					   $array_of_all_added_ranges[$bkap_range_count]['bkap_specific_date']   = $sp_date;
					   $array_of_all_added_ranges[$bkap_range_count]['bkap_specific_lockout']= '';
					   $array_of_all_added_ranges[ $bkap_range_count ][ 'bkap_specific_price' ] = $sp_price;
					   $bkap_range_count++;
				   }
			   }
			}*/

			if ( isset( $booking_holiday_ranges ) && $count_holiday_ranges > 0 ) {
				for ( $bkap_range = 0; $bkap_range < $count_holiday_ranges; $bkap_range++ ) {

					if ( isset( $booking_holiday_ranges[ $bkap_range ] ) ) {

						$bkap_holiday_from_month     = date( 'F', strtotime( $booking_holiday_ranges[ $bkap_range ]['start'] ) );
						$bkap_holiday_to_month       = date( 'F', strtotime( $booking_holiday_ranges[ $bkap_range ]['end'] ) );
						$holiday_start_date_of_month = date( '1-n-Y', strtotime( $booking_holiday_ranges[ $bkap_range ]['start'] ) );
						$holiday_end_date_of_month   = date( 't-n-Y', strtotime( $booking_holiday_ranges[ $bkap_range ]['end'] ) );

						// Check if the start date is the start of the month and end date is the end date of the month then range type should be month range.
						if ( $booking_holiday_ranges[ $bkap_range ]['start'] == $holiday_start_date_of_month && $holiday_end_date_of_month == $booking_holiday_ranges[ $bkap_range ]['end'] ) {
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_type']           = ( isset( $booking_holiday_ranges[ $bkap_range ]['range_type'] ) ) ? $booking_holiday_ranges[ $bkap_range ]['range_type'] : 'range_of_months';
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_start']          = $booking_holiday_ranges[ $bkap_range ]['start'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_end']            = $booking_holiday_ranges[ $bkap_range ]['end'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_years_to_recur'] = $booking_holiday_ranges[ $bkap_range ]['years_to_recur'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_bookable']       = 'off';
						} else {
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_type']           = ( isset( $booking_holiday_ranges[ $bkap_range ]['range_type'] ) ) ? $booking_holiday_ranges[ $bkap_range ]['range_type'] : 'custom_range';
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_start']          = $booking_holiday_ranges[ $bkap_range ]['start'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_end']            = $booking_holiday_ranges[ $bkap_range ]['end'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_years_to_recur'] = $booking_holiday_ranges[ $bkap_range ]['years_to_recur'];
							$array_of_all_added_ranges[ $bkap_range_count ]['bkap_bookable']       = 'off';
						}

						$bkap_range_count++;
					}
				}
			}

			$i = 0;

			while ( $i < count( $array_of_all_added_ranges ) ) {

				  $range_type           = $array_of_all_added_ranges[ $i ]['bkap_type'];
				  $custom_range_disaply = $holidays_disaply = $range_of_months_disaply = $specific_dates_disaply = '';

				  $bkap_start          = ( isset( $array_of_all_added_ranges[ $i ]['bkap_start'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_start'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_start'] : '';
				  $bkap_end            = ( isset( $array_of_all_added_ranges[ $i ]['bkap_end'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_end'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_end'] : '';
				  $bkap_years_to_recur = ( isset( $array_of_all_added_ranges[ $i ]['bkap_years_to_recur'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_years_to_recur'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_years_to_recur'] : '';
				  $bkap_bookable       = 'checked="checked"';
				  $custom_bkap_start   = $custom_bkap_end = $month_bkap_start = $month_bkap_end = $bkap_holiday_date = $custom_bkap_years_to_recur = $holiday_bkap_years_to_recur = $month_bkap_years_to_recur = $bkap_specific_price = $bkap_specific_lockout = $bkap_specific_date = '';

				switch ( $range_type ) {
					case 'custom_range':
						$holidays_disaply           = $range_of_months_disaply = $specific_dates_disaply = 'display:none;';
						$custom_bkap_start          = $bkap_start;
						$custom_bkap_end            = $bkap_end;
						$custom_bkap_years_to_recur = $bkap_years_to_recur;
						if ( isset( $array_of_all_added_ranges[ $i ]['bkap_bookable'] ) && $array_of_all_added_ranges[ $i ]['bkap_bookable'] == 'off' ) {
							$bkap_bookable = '';
						}

						break;

					case 'holidays':
						$custom_range_disaply        = $range_of_months_disaply = $specific_dates_disaply = 'display:none;';
						$bkap_holiday_date           = ( isset( $array_of_all_added_ranges[ $i ]['bkap_holiday_date'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_holiday_date'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_holiday_date'] : '';
						$holiday_bkap_years_to_recur = $bkap_years_to_recur;
						$bkap_bookable               = '';
						break;

					case 'range_of_months':
						$custom_range_disaply      = $holidays_disaply = $specific_dates_disaply = 'display:none;';
						$month_bkap_start          = date( 'F', strtotime( $bkap_start ) );
						$month_bkap_end            = date( 'F', strtotime( $bkap_end ) );
						$month_bkap_years_to_recur = $bkap_years_to_recur;
						if ( isset( $array_of_all_added_ranges[ $i ]['bkap_bookable'] ) && $array_of_all_added_ranges[ $i ]['bkap_bookable'] == 'off' ) {
							$bkap_bookable = '';
						}
						break;

					case 'specific_dates':
						$custom_range_disaply  = $holidays_disaply = $range_of_months_disaply = 'display:none;';
						$bkap_specific_date    = ( isset( $array_of_all_added_ranges[ $i ]['bkap_specific_date'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_specific_date'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_specific_date'] : '';
						$bkap_specific_lockout = ( isset( $array_of_all_added_ranges[ $i ]['bkap_specific_lockout'] ) && ! is_null( $array_of_all_added_ranges[ $i ]['bkap_specific_lockout'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_specific_lockout'] : '';
						$bkap_specific_price   = ( isset( $array_of_all_added_ranges[ $i ]['bkap_specific_price'] ) ) ? $array_of_all_added_ranges[ $i ]['bkap_specific_price'] : '';
						break;

					default:
						break;
				}

				  $bkap_row_toggle         = '';
				  $bkap_row_toggle_display = '';
				if ( $i > 4 ) {
					$bkap_row_toggle         = 'bkap_row_toggle';
					$bkap_row_toggle_display = 'style="display:none;"';
				}
				?>

				  <tr class="added_specific_date_range_row_<?php echo $i; ?> <?php echo $bkap_row_toggle; ?>" <?php echo $bkap_row_toggle_display; ?>>

					  <td style="width:20%;">
						<select style="width:100%;" id="range_dropdown_<?php echo $i; ?>">

						<?php
						foreach ( $bkap_dates_months_availability as $d_value => $d_name ) {
							$bkap_range_selected = '';
							if ( $d_value == $range_type ) {
								$bkap_range_selected = 'selected';
							}
							printf( "<option value='%s' %s>%s</option>\n", $d_value, $bkap_range_selected, $d_name );
						}
						?>

						</select>
					  </td>

					  <td class="date_selection_textbox1" style="width:20%;<?php echo $custom_range_disaply; ?>">
						   <div class="fake-input">
							   <input type="text" id="datepicker_textbox_<?php echo $i; ?>" class="datepicker_start_date date_selection_textbox" style="width:100%;" value="<?php echo $custom_bkap_start; ?>"/>
							   <img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="custom_checkin_cal_<?php echo $i; ?>" width="15" height="15" />
						   </div>
					  </td>

					  <td class="date_selection_textbox2" style="width:20%;<?php echo $custom_range_disaply; ?>">
						   <div class="fake-input" >
							   <input type="text" id="datepicker_textbox__<?php echo $i; ?>" class="datepicker_end_date date_selection_textbox" style="width:100%;" value="<?php echo $custom_bkap_end; ?>" />
							   <img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="custom_checkout_cal_<?php echo $i; ?>" width="15" height="15" />
						   </div>
					  </td>

					  <td class="date_selection_textbox3" colspan="2" style="<?php echo $specific_dates_disaply; ?>width:40%;" >
						   <div class="fake-textarea" >
							   <textarea id="specific_dates_multidatepicker_<?php echo $i; ?>" class="textareamultidate_cal" rows="1" col="30" style="width:100%;height:auto;"><?php echo $bkap_specific_date; ?></textarea>
							   <img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="specific_date_multidate_cal_<?php echo $i; ?>" class="bkap_multiple_datepicker_cal_image" width="15" height="15" />
						   </div>
					  </td>

					  <!-- From Month-->
					  <td class="date_selection_textbox4" style="<?php echo $range_of_months_disaply; ?>width:20%;">
						   <select id="bkap_availability_from_month_<?php echo $i; ?>" style="width:100%;">

							<?php
							foreach ( $bkap_months as $m_number => $m_name ) {
								if ( $m_name == $month_bkap_start ) {
									$month_bkap_start_selected = 'selected';
									printf( "<option value='%d' %s>%s</option>\n", $m_number, $month_bkap_start_selected, $m_name );
								} else {
									printf( "<option value='%d'>%s</option>\n", $m_number, $m_name );
								}
							}
							?>

							</select>
					  </td>
					   <!-- To Month-->
					   <td class="date_selection_textbox5" style="<?php echo $range_of_months_disaply; ?>width:20%;">
							<select id="bkap_availability_to_month_<?php echo $i; ?>" style="width:100%;">

								<?php
								foreach ( $bkap_months as $m_number => $m_name ) {

									if ( $m_name == $month_bkap_end ) {
										$month_bkap_end_selected = 'selected';
										printf( "<option value='%d' %s>%s</option>\n", $m_number, $month_bkap_end_selected, $m_name );
									} else {
										printf( "<option value='%d'>%s</option>\n", $m_number, $m_name );
									}
								}
								?>

							</select>
					   </td>

					   <!-- Holiday Textarea -->
					   <td class="date_selection_textbox6" colspan="2" style="<?php echo $holidays_disaply; ?>width:40%" >
							<div class="fake-textarea" >
								<textarea id="holidays_multidatepicker_<?php echo $i; ?>" class="textareamultidate_cal" rows="1" col="30" style="width:100%;height:auto;" style="overflow:hidden" onkeyup="auto_grow(this)"><?php echo $bkap_holiday_date; ?></textarea>
								<img src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/cal.gif" id="holiday_multidate_cal_<?php echo $i; ?>" class="bkap_multiple_datepicker_cal_image" width="15" height="15" />
							</div>
					   </td>

					   <td style="padding-left:2%;width:10%;">
							<div class="bkap_popup">
							<span class="bkap_popuptext" id="bkap_myPopup_<?php echo $i; ?>"></span>
							<label class="bkap_switch">
								 <input id="bkap_bookable_nonbookable_<?php echo $i; ?>" type="checkbox" name="bkap_bookable_nonbookable" <?php echo $bkap_bookable; ?>>
								 <div class="bkap_slider round"></div>
							</label>
							</div>

					   </td>

					   <td class="bkap_lockout_column_data_1" style="<?php echo $custom_range_disaply; ?>">
							<input id="bkap_number_of_year_to_recur_custom_range_<?php echo $i; ?>" value="<?php echo $custom_bkap_years_to_recur; ?>" title="Please enter number of years you want to recur this custom range" type="number" min="0" style="width:65%;font-size:11px;margin-left: 15%;" placeholder="No. of Years">

							&nbsp;
							<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
					   </td>

					   <td class="bkap_lockout_column_data_2"  style="<?php echo $holidays_disaply; ?>">
							<input id="bkap_number_of_year_to_recur_holiday_<?php echo $i; ?>" value="<?php echo $holiday_bkap_years_to_recur; ?>"  title="Please enter number of years you want to recur selected holidays" type="number" min="0" style="width:65%;font-size:11px;margin-left: 15%;" placeholder="No. of Years">
							&nbsp;
							<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
					   </td>

					   <td class="bkap_lockout_column_data_3"  style="<?php echo $range_of_months_disaply; ?>">
							<input id="bkap_number_of_year_to_recur_month_<?php echo $i; ?>" value="<?php echo $month_bkap_years_to_recur; ?>" title="Please enter number of years you want to recur selected month" type="number" min="0" style="width:65%;font-size:11px;margin-left:15%;" placeholder="No. of Years">
							&nbsp;
							<i id="bkap_recurring" class="fa fa-sync-alt" aria-hidden="true" title="Recurring yearly"></i>
					   </td>

					   <td class="bkap_lockout_column_data_4" style="<?php echo $specific_dates_disaply; ?>">
							<input id="bkap_specific_date_lockout_<?php echo $i; ?>" value="<?php echo $bkap_specific_lockout; ?>" title="This is number of maximum bookings for selected specific dates." type="number" min="0"style="width:47%;font-size:11px;" onkeypress="return bkap_only_number( event )" placeholder="Max bookings">
							<input id="bkap_specific_date_price_<?php echo $i; ?>" value="<?php echo $bkap_specific_price; ?>" title="This is price for selected specific dates." type="number" min="0" style="width:45%;float:right;font-size:11px;" placeholder="Price">
					   </td>

					   <td style="width:4%;" id="bkap_close_<?php echo $i; ?>" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>

				  </tr>
				  <?php
					$i++;
			}

			if ( count( $array_of_all_added_ranges ) > 5 ) {
				?>
				<tr style="">
				   <td colspan="6">
				   <span class="bkap_expand-close">
					   <a href="#" class="bkap_expand_all"><?php echo __( 'Expand', 'woocommerce-booking' ); ?></a> / <a href="#" class="bkap_close_all"><?php echo __( 'Close', 'woocommerce-booking' ); ?></a>
				   </span>
				   </td>
				</tr>
				<?php
			}
		}

		/**
		 * Displays Time Slots present for a product
		 *
		 * @since 4.5.0
		 */

		public static function bkap_load_time_slots() {
			ob_start();

			if ( empty( $_POST['bkap_product_id'] ) ) {
				$default_booking_settings = get_option( 'bkap_default_booking_settings', array() );
				if ( empty( $default_booking_settings ) ) {
					wp_die();
				}
			}

			$bkap_loop       = 0;
			$bkap_product_id = absint( $_POST['bkap_product_id'] );
			$bkap_per_page   = ! empty( $_POST['bkap_per_page'] ) ? absint( $_POST['bkap_per_page'] ) : 15;
			$bkap_page       = ! empty( $_POST['bkap_page'] ) ? absint( $_POST['bkap_page'] ) : 1;

			$booking_settings = array();
			if ( 0 == $bkap_product_id ) {
				$default_booking_settings = get_option( 'bkap_default_booking_settings', array() );
				if ( ! empty( $default_booking_settings ) ) {
					$booking_settings = $default_booking_settings;
				}
			} else {
				$booking_settings = get_post_meta( $bkap_product_id, 'woocommerce_booking_settings', true );
			}

			/**
			 * Set the pagination limits for the  records.
			 */
			$bkap_end_record_on     = $bkap_page * $bkap_per_page;
			$bkap_start_record_from = ( $bkap_page > 1 ) ? ( ( $bkap_page - 1 ) * $bkap_per_page ) + 1 : 1;

			if ( isset( $booking_settings['booking_time_settings'] ) && is_array( $booking_settings['booking_time_settings'] ) ) {

				include BKAP_PLUGIN_PATH . '/templates/meta-boxes/html-bkap-time-slots-meta-box.php';
			}

			wp_die();
		}

		/**
		 * Create pagination links for the Time Slots table.
		 *
		 * @param integer $bkap_per_page_time_slots - Number of Time Slots to be displayed per page.
		 * @param integer $bkap_total_time_slots_number - Number of slots present for the product.
		 * @param integer $bkap_total_pages - Total Number of pages that need to be displayed.
		 * @param string  $bkap_encode_booking_times - JSON Encoded time slots data
		 *
		 * @since 4.5.0
		 */

		public static function bkap_get_pagination_for_time_slots( $bkap_per_page_time_slots, $bkap_total_time_slots_number, $bkap_total_pages, $bkap_encode_booking_times ) {
			?>
			<div class="bkap_toolbar"  data-bkap-total="<?php echo $bkap_total_time_slots_number; ?>" data-total_pages="<?php echo $bkap_total_pages; ?>" data-page="1" data-edited="false" data-time-slots = "<?php echo $bkap_encode_booking_times; ?>" >
				<div class="bkap_time_slots_pagenav">
					<span class="bkap_displaying_num">
						<span class="bkap_display_count_num">
							<?php _e( $bkap_total_time_slots_number, 'woocommerce-booking' ); ?>
						</span>
						<?php print( _n( 'time slot', 'time slots', $bkap_total_time_slots_number, 'woocommerce-booking' ) ); ?>

					</span>
					<span class="bkap_pagination_links">
						<a class="bkap_first_page disabled" title="<?php esc_attr_e( 'Go to the first page', 'woocommerce-booking' ); ?>" href="#">&laquo;</a>
						<a class="bkap_prev_page disabled" title="<?php esc_attr_e( 'Go to the previous page', 'woocommerce-booking' ); ?>" href="#">&lsaquo;</a>
						<span class="bkap_paging_select">
							<label for="bkap_current_page_selector_1" class="bkap_screen_reader_text"><?php _e( 'Select Page', 'woocommerce-booking' ); ?></label>
							<select class="bkap_page_selector" id="bkap_current_page_selector_1" title="<?php esc_attr_e( 'Current page', 'woocommerce-booking' ); ?>">
								<?php for ( $i = 1; $i <= $bkap_total_pages; $i++ ) : ?>
									<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
								<?php endfor; ?>
							</select>
							 <?php _ex( 'of', 'number of pages', 'woocommerce-booking' ); ?> <span class="bkap_total_pages"><?php echo $bkap_total_pages; ?></span>
						</span>
						<a class="bkap_next_page" title="<?php esc_attr_e( 'Go to the next page', 'woocommerce-booking' ); ?>" href="#">&rsaquo;</a>
						<a class="bkap_last_page" title="<?php esc_attr_e( 'Go to the last page', 'woocommerce-booking' ); ?>" href="#">&raquo;</a>
					</span>
				</div>
				<div class="clear"></div>
			</div>
			<?php
		}

		/**
		 * Displays the Time Slots data in the Availability tab.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings
		 * @since 4.0.0
		 */

		static function bkap_get_date_time_html( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$date_time_table          = 'display:none;';
			$duration_time_table      = 'display:none;';
			$manage_time_availability = 'display:none;';

			$booking_times                      = array();
			$bkap_encode_booking_times          = array();
			$bkap_display_time_slots_pagination = 'display:none;';
			$bkap_total_time_slots_number       = 1;
			$bkap_total_pages                   = 0;
			$bkap_per_page_time_slots           = absint( apply_filters( 'bkap_time_slots_per_page', 15 ) );

			// Duration initialization of variable
			$duration_label       = '';
			$duration             = 1;
			$duration_gap         = 0;
			$duration_min         = 1;
			$duration_max         = 1;
			$duration_max_booking = 0;
			$duration_price       = '';
			$first_duration       = '';
			$end_duration         = '';
			$duration_type        = '';
			$duration_gap_type    = '';

			$duration_type_array = bkap_get_duration_types();

			if ( isset( $booking_settings ['booking_enable_time'] ) ) {

				if ( $booking_settings ['booking_enable_time'] == 'on' || 'dates_time' == $booking_settings ['booking_enable_time'] ) {
					$date_time_table          = '';
					$manage_time_availability = '';
				} elseif ( $booking_settings ['booking_enable_time'] == 'duration_time' ) {
					$duration_time_table      = '';
					$manage_time_availability = '';
				}
			}

			if ( isset( $booking_settings['bkap_duration_settings'] ) && count( $booking_settings['bkap_duration_settings'] ) > 0 ) {
				$duration_settings = $booking_settings['bkap_duration_settings'];

				$duration_label       = $duration_settings['duration_label'];
				$duration             = $duration_settings['duration'];
				$duration_gap         = isset( $duration_settings['duration_gap'] ) ? $duration_settings['duration_gap'] : 0;
				$duration_type        = $duration_settings['duration_type'];
				$duration_gap_type    = isset( $duration_settings['duration_gap_type'] ) ? $duration_settings['duration_gap_type'] : 'hours';
				$duration_min         = $duration_settings['duration_min'];
				$duration_max         = $duration_settings['duration_max'];
				$duration_max_booking = $duration_settings['duration_max_booking'];
				$duration_price       = $duration_settings['duration_price'];
				$first_duration       = $duration_settings['first_duration'];
				$end_duration         = $duration_settings['end_duration'];
			}

			if ( isset( $booking_settings['booking_time_settings'] ) && is_array( $booking_settings['booking_time_settings'] ) ) {

				foreach ( $booking_settings['booking_time_settings'] as $bkap_weekday_key => $bkap_weekday_value ) {

					foreach ( $bkap_weekday_value as $day_key => $time_data ) {

						$bkap_from_hr  = ( isset( $time_data['from_slot_hrs'] ) && ! is_null( $time_data['from_slot_hrs'] ) ) ? $time_data['from_slot_hrs'] : '';
						$bkap_from_min = ( isset( $time_data['from_slot_min'] ) && ! is_null( $time_data['from_slot_min'] ) ) ? $time_data['from_slot_min'] : '';

						$bkap_from_time = $bkap_from_hr . ':' . $bkap_from_min;

						$bkap_to_hr  = ( isset( $time_data['to_slot_hrs'] ) && ! is_null( $time_data['to_slot_hrs'] ) ) ? $time_data['to_slot_hrs'] : '';
						$bkap_to_min = ( isset( $time_data['to_slot_min'] ) && ! is_null( $time_data['to_slot_min'] ) ) ? $time_data['to_slot_min'] : '';

						$bkap_to_time = ( $bkap_to_hr === '0' && $bkap_to_min === '00' ) ? '' : "$bkap_to_hr:$bkap_to_min";

						$bkap_lockout = ( isset( $time_data['lockout_slot'] ) && ! is_null( $time_data['lockout_slot'] ) ) ? $time_data['lockout_slot'] : '';
						$bkap_price   = ( isset( $time_data['slot_price'] ) && ! is_null( $time_data['slot_price'] ) ) ? $time_data['slot_price'] : '';

						$bkap_global = ( isset( $time_data['global_time_check'] ) && ! is_null( $time_data['global_time_check'] ) ) ? $time_data['global_time_check'] : '';
						$bkap_note   = ( isset( $time_data['booking_notes'] ) && ! is_null( $time_data['booking_notes'] ) ) ? $time_data['booking_notes'] : '';

						$booking_times[ $bkap_total_time_slots_number ]                       = array();
						$booking_times[ $bkap_total_time_slots_number ] ['day']               = $bkap_weekday_key;
						$booking_times[ $bkap_total_time_slots_number ] ['from_time']         = $bkap_from_time;
						$booking_times[ $bkap_total_time_slots_number ] ['to_time']           = $bkap_to_time;
						$booking_times[ $bkap_total_time_slots_number ] ['lockout_slot']      = $bkap_lockout;
						$booking_times[ $bkap_total_time_slots_number ] ['slot_price']        = $bkap_price;
						$booking_times[ $bkap_total_time_slots_number ] ['global_time_check'] = $bkap_global;
						$booking_times[ $bkap_total_time_slots_number ] ['booking_notes']     = $bkap_note;

						$bkap_total_time_slots_number ++;
					}
				}

				if ( $bkap_total_time_slots_number > 1 ) {
					$bkap_display_time_slots_pagination = '';
					$bkap_total_time_slots_number--;
				}

				$bkap_total_pages          = ceil( $bkap_total_time_slots_number / $bkap_per_page_time_slots );
				$bkap_encode_booking_times = htmlspecialchars( json_encode( $booking_times, JSON_FORCE_OBJECT ) );
			} else {

				/**
				 * When we add a new product we need to pass this array as a string so we are creating a json object string.
				 */

				$bkap_encode_booking_times = htmlspecialchars( json_encode( $booking_times, JSON_FORCE_OBJECT ) );
			}

			?>
		   <!-- Table for adding Date/Day and time table -->
			<div class="bkap_date_timeslot_div" style="<?php echo $date_time_table; ?>">

			   <?php do_action( 'bkap_before_time_enabled', $product_id, $booking_settings ); ?>

				<div>
					<h4><?php _e( 'Set Weekdays/Dates And It\'s Timeslots :', 'woocommerce-booking' ); ?></h4>
				</div>

			   <?php do_action( 'bkap_after_time_enabled', $product_id, $booking_settings ); ?>

				<table id="bkap_date_timeslot_table">
				   <?php
					// add date and time setup.
					self::bkap_get_daydate_and_time_heading( $product_id, $booking_settings, $bkap_display_time_slots_pagination, $bkap_per_page_time_slots, $bkap_total_time_slots_number, $bkap_total_pages, $bkap_encode_booking_times );

					?>
					<?php
					self::bkap_get_daydate_and_time_table_base_data( $product_id, $booking_settings, $default_booking_settings, $defaults );

					if ( $bkap_total_time_slots_number > 0 ) {

						/**
						 * This tr is a identifier, when we recive the response from ajax we will remove this tr and replace
						 * our genrated data.
						 */
						?>
							<tr class="bkap_replace_response_data">

							</tr>

						<?php
					}
					?>

					<tr class="bkap-pagination" style="padding:5px; border-top:2px solid #eee; <?php echo $bkap_display_time_slots_pagination; ?> " >
						<td colspan="8" align="right" style="border-right: 0px;">
						   <?php
							   /**
								* Add the  pagination
								*/
							   self::bkap_get_pagination_for_time_slots( $bkap_per_page_time_slots, $bkap_total_time_slots_number, $bkap_total_pages, $bkap_encode_booking_times );

							?>
						</td>
					</tr>
					<tr style="padding:5px; border-top:2px solid #eee">
					   <td colspan="4" style="border-right: 0px;">
					   <i>
						   <small><?php _e( 'Create timeslots for the days/dates selected above.', 'woocommerce-booking' ); ?>
						   <br><?php _e( 'Enter time in 24 hours format e.g. 14:00.', 'woocommerce-booking' ); ?>
						   <br><?php _e( 'Leave "To time" unchanged if you do not wish to create a fixed time duration slot.', 'woocommerce-booking' ); ?>
						   </small>
					   <i></td>
					   <td colspan="4" align="right" style="border-left: none;"><button type="button" id="bkap_remove_all_timeslots" class="button"><?php _e( 'Delete All Timeslots', 'woocommerce-booking' ); ?></button>&nbsp;<button type="button" class="button-primary bkap_add_new_date_time_range"><?php _e( 'Add New Timeslot', 'woocommerce-booking' ); ?></button></td>
					</tr>
				</table>

			</div>

			<?php do_action( 'bkap_before_duration_based_time_section', $product_id, $booking_settings ); ?>

			<!-- Table for adding Date/Day and time table -->
			<div class="bkap_duration_date_timeslot_div" style="<?php echo $duration_time_table; ?>">

				<div>
					<h4><?php esc_html_e( 'Set Duration Based Bookings', 'woocommerce-booking' ); ?></h4>
				</div>

				<table id="bkap_duration_date_timeslot_table">

					<tr>
						<td><?php esc_html_e( 'Label:', 'woocommerce-booking' ); ?></td>
						<td>
							<input  type="text"
									id="bkap_duration_label"
									name="bkap_duration_label"
									placeholder="Label for duration"
									value="<?php echo sanitize_text_field( $duration_label, true ); ?>"/>
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Set label for the duration field on the front end', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Duration', 'woocommerce-booking' ); ?></td>
						<td>
							<input type="number"
									style="width:90px"
									name=""
									id="bkap_duration"
									min="1"
									value="<?php echo sanitize_text_field( $duration, true ); ?>">

									<select id="bkap_duration_type" name= "bkap_duration_type" style="max-width:70%;">

								   <?php

									foreach ( $duration_type_array as $key => $value ) {
										$selected_duration = '';

										if ( $duration_type == $key ) {
											$selected_duration = 'selected';
										}
										?>
										<option value='<?php echo $key; ?>' <?php echo $selected_duration; ?> ><?php echo $value; ?></option>
										<?php
									}

									?>
									</select>
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Lengh of the time. Set value to 2 hours/minutes if the duration of your service is 2 hours/minutes. All the 2 hours/minutes durations will be created from mindnight till end of the day.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Gap between durations', 'woocommerce-booking' ); ?></td>
						<td>
							<input type="number"
									style="width:90px"
									name="bkap_duration_gap"
									id="bkap_duration_gap"
									min="0"
									value="<?php echo sanitize_text_field( $duration_gap, true ); ?>">

									<select id="bkap_duration_gap_type" name= "bkap_duration_gap_type" style="max-width:70%;">

								   <?php

									foreach ( $duration_type_array as $key => $value ) {
										$selected_duration = '';

										if ( $duration_gap_type == $key ) {
											$selected_duration = 'selected';
										}
										?>
										<option value='<?php echo $key; ?>' <?php echo $selected_duration; ?> ><?php echo $value; ?></option>
										<?php
									}

									?>
									</select>
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Set gap between two times. Set value to 2 hours/minutes if the gap between your service is 2 hours/minutes. All the duration will be created considering the gap time.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Minimum duration', 'woocommerce-booking' ); ?></td>
						<td>
							<input  type="number"
									style="width:90px"
									name=""
									id="bkap_duration_min"
									min="1"
									value="<?php echo sanitize_text_field( $duration_min, true ); ?>">
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Minimum duration value a customer can select to book the service.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Maximum duration', 'woocommerce-booking' ); ?></td>
						<td>
							<input  type="number"
									style="width:90px"
									name=""
									id="bkap_duration_max"
									min="1"
									max="24"
									value="<?php echo sanitize_text_field( $duration_max, true ); ?>">
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Maximum duration value a customer can select to book the service.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Maximum booking', 'woocommerce-booking' ); ?></td>
						<td>
							<input  type="number"
									style="width:90px"
									name=""
									id="bkap_duration_max_booking"
									min="0"
									max="24"
									value="<?php echo sanitize_text_field( $duration_max_booking, true ); ?>">
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Set this field if you want to place a limit on maximum bookings on the duration. If you can manage up to 15 bookings in a duration, set this value to 15. Once 15 orders have been booked, then that duration will not be available for further bookings.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Duration price', 'woocommerce-booking' ); ?></td>
						<td>
							<div class="bkap_duration_price_div">
								<input  type="text"
										id="bkap_duration_price"
										class="bkap_input_price"
										style="width:90px"
										name="bkap_duration_price"
										placeholder="Price"
										value="<?php echo sanitize_text_field( $duration_price, true ); ?>"/>
							</div>
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Price for the duration. ', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Duration start & end range for days', 'woocommerce-booking' ); ?></td>
						<td>
							<input  type="text"
									id="bkap_duration_start"
									style="width:90px"
									name=""
									placeholder="HH:MM"
									value="<?php echo sanitize_text_field( $first_duration, true ); ?>"/>

							<input  type="text"
									id="bkap_duration_end"
									style="width:90px"
									name=""
									placeholder="HH:MM"
									value="<?php echo sanitize_text_field( $end_duration, true ); ?>"/>
						</td>
						<td>
							<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Set this field if you want to start the duration from perticular time. If your day starts at 10:00am then you can set this value to 10:00. All the durations will be created from 10:00am till the value set in the Duration ends at option. If the Duration ends at option is blank then duration end time will be considered till end of the day.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
						</td>
					</tr>
				</table>
			</div>

			<?php
			$bkap_intervals       = bkap_intervals();
			$all_data_unavailable = '';
			if ( isset( $booking_settings['bkap_all_data_unavailable'] ) && 'on' === $booking_settings['bkap_all_data_unavailable'] ) {
				$all_data_unavailable = 'checked';
			}

			?>
			<div id="bkap_time_duration_availability" class="bkap_availability_range" style="<?php echo $manage_time_availability; ?>">
				<div>
					<h4><?php esc_html_e( 'Manage Time Availability', 'woocommerce-booking' ); ?></h4>
				</div>

				<div class="bkap_all_data_block_unavailable_section" style="display:flex;width:100%;margin-top:20px;">
					<div>
						<?php esc_html_e( 'Make all data block unavailable', 'woocommerce-booking' ); ?>
					</div>
					<div style="margin-left: 10px">
						<label class="bkap_switch">
						<input title="<?php esc_attr_e( 'Enable this option to make all the day/date and time unavailable except the ranges added in the below table', 'woocommerce-booking' ); ?>" type="checkbox" name="bkap_all_data_unavailable" id="bkap_all_data_unavailable" <?php echo $all_data_unavailable; ?>>
						<div class="bkap_slider round"></div>
						</label>
					</div>
					<div>
						<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Enabling this option will disable all the dates in the calendar. Usign this option with below table, you can enable desired dates for booking.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
					</div>
				</div>
				<br>

				<table id="bkap_manage_time_availability" class="widefat">
					<thead>
						<tr>
							<th><b><?php esc_html_e( 'Range type', 'woocommerce-booking' ); ?></b></th>
							<th><b><?php esc_html_e( 'From', 'woocommerce-booking' ); ?></b></th>
							<th></th>
							<th><b><?php esc_html_e( 'To', 'woocommerce-booking' ); ?></b></th>
							<th><b><?php esc_html_e( 'Bookable', 'woocommerce-booking' ); ?></b></th>
							<th><b><?php esc_html_e( 'Priority', 'woocommerce-booking' ); ?></b></th>
							<th class="remove" width="1%">&nbsp;</th>
						</tr>
					</thead>

					<tfoot>
						<tr >
							<th colspan="4" style="text-align: left;font-size: 11px;font-style: italic;">
								<?php esc_html_e( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ).', 'woocommerce-booking' ); ?>
							</th>
							<th colspan="3" style="text-align: right;">
								<a href="#" class="button button-primary bkap_add_row_resource" style="text-align: right;" data-row="
								<?php
									ob_start();
									include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html_resource_availability_table.php';
									$html = ob_get_clean();
									echo esc_attr( $html );
								?>
								"><?php esc_html_e( 'Add Range', 'woocommerce-booking' ); ?></a>
							</th>
						</tr>
					</tfoot>

					<tbody id="bkap_availability_rows">
						<?php
							$values = isset( $booking_settings['bkap_manage_time_availability'] ) ? $booking_settings['bkap_manage_time_availability'] : array();

						if ( ! empty( $values ) && is_array( $values ) ) {
							foreach ( $values as $availability ) {
								include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html_resource_availability_table.php';
							}
						}
						?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Displays the table headers for the Time Slots table
		 * in Booking meta box->Availability tab.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings
		 * @param string  $bkap_display_time_slots_pagination - Display pagination or no. Blanks - indicates Yes
		 * @param integer $bkap_per_page_time_slots - Time Slots to be displayed per page
		 * @param integer $bkap_total_time_slots_number - Total Number of Slots
		 * @param integer $bkap_total_pages - Number of pages
		 * @param string  $bkap_encode_booking_times - JSON encoded time slots data
		 *
		 * @since 4.5.0
		 */

		static function bkap_get_daydate_and_time_heading( $product_id, $booking_settings, $bkap_display_time_slots_pagination, $bkap_per_page_time_slots, $bkap_total_time_slots_number, $bkap_total_pages, $bkap_encode_booking_times ) {

			$heading = apply_filters(
				'bkap_get_daydate_and_time_heading_html',
				array(
					'weekday'          => array(
						'style' => 'width:20%',
						'data'  => __( 'Weekday', 'woocommerce-booking' ),
					),
					'from'             => array(
						'style' => 'width:10%',
						'data'  => __( 'From', 'woocommerce-booking' ),
					),
					'to'               => array(
						'style' => 'width:10%',
						'data'  => __( 'To', 'woocommerce-booking' ),
					),
					'maximum_bookings' => array(
						'style' => 'width:10%',
						'data'  => __( 'Maximum Bookings', 'woocommerce-booking' ),
					),
					'price'            => array(
						'style' => 'width:10%',
						'data'  => __( 'Price', 'woocommerce-booking' ),
					),
					'global'           => array(
						'style' => 'width:10%',
						'data'  => __( 'Global', 'woocommerce-booking' ),
					),
					'note'             => array(
						'style' => 'width:23%',
						'data'  => __( 'Note', 'woocommerce-booking' ),
					),
				)
			);

			?>
			<tr>
				<?php
				foreach ( $heading as $key => $value ) {
					printf( '<th style="%s" id="%s">%s</th>', $value['style'], 'weekdaydatetime_heading_' . $key, $value['data'] );
				}
				?>
				<th width="4%" id="bkap_remove_all_timeslots" style="text-align: center;cursor: pointer;"><i class="fa fa-lg fa-trash" title="<?php esc_attr_e( 'Delete all timeslots', 'woocommerce-booking' ); ?>" aria-hidden="true"></i></th>
			</tr>
			<tr class="bkap-pagination" style="padding:5px; border-top:2px solid #eee; <?php echo $bkap_display_time_slots_pagination; ?> " >
					<td colspan="8" align="right" style="border-right: 0px;">
						<?php
							/**
							 * Add the  pagination
							 */
							self::bkap_get_pagination_for_time_slots( $bkap_per_page_time_slots, $bkap_total_time_slots_number, $bkap_total_pages, $bkap_encode_booking_times );

						?>
					</td>
				</tr>
			<?php
		}

		/**
		 * Add the default row for the time slots table. This row is used to add
		 * other data when the New icon is clicked.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings
		 *
		 * @since 4.0.0
		 */

		static function bkap_get_daydate_and_time_table_base_data( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			global $bkap_weekdays;

			$recurring_weekdays = array();
			$specific_dates     = array();

			$bookable = bkap_common::bkap_get_bookable_status( $product_id );

			if ( $bookable && isset( $booking_settings['booking_recurring'] ) && count( $booking_settings['booking_recurring'] ) > 0 ) { // bookable product
				$recurring_weekdays = $booking_settings['booking_recurring'];
			} elseif ( ! $bookable ) { // it's a new product

				foreach ( $bkap_weekdays as $day_name => $day_value ) {
					$recurring_weekdays[ $day_name ] = 'on'; // all weekdays are on by default
				}
			}

			if ( $bookable && isset( $booking_settings['booking_specific_date'] ) && count( $booking_settings['booking_specific_date'] ) > 0 ) {
				$specific_dates = $booking_settings['booking_specific_date'];
			}

			?>
			<tr id="bkap_default_date_time_row" style="display: none;">
				<td width="20%" class="bkap_dateday_td">
					<select id="bkap_dateday_selector" multiple="multiple">
						<option name="all" value="all"><?php _e( 'All', 'woocommerce-booking' ); ?></option>
					   <?php
						foreach ( $bkap_weekdays as $w_value => $w_name ) {
							if ( isset( $recurring_weekdays[ $w_value ] ) && 'on' == $recurring_weekdays[ $w_value ] ) {
								printf( "<option value='%s'>%s</option>\n", $w_value, $w_name );
							}
						}
						foreach ( $specific_dates as $dates => $lockout ) {
							printf( "<option value='%s'>%s</option>\n", $dates, $dates );
						}
						?>
					</select>
				</td>
				<td width="10%" class="bkap_from_time_td"><input id="bkap_from_time" type="text" name="quantity" style="width:100%;" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" maxlength="5" onkeypress="return bkap_isNumberKey(event)"></td>
				<td width="10%" class="bkap_to_time_td"><input id="bkap_to_time" type="text" name="quantity" style="width:100%;" pattern="^([0-1][0-9]|[2][0-3]):([0-5][0-9])$" title="Please enter time in 24 hour format e.g 14:00 or 03:00" placeholder="HH:MM" maxlength="5" onkeypress="return bkap_isNumberKey(event)"></td>
				<td width="10%" class="bkap_lockout_time_td"><input id="bkap_lockout_time" type="number" name="quantity" style="width:100%;" min="0" onkeypress="return bkap_only_number( event )" placeholder="Max bookings"></td>
				<td width="10%" class="bkap_price_time_td"><input id="bkap_price_time" class="wc_input_price" type="text" name="quantity" style="width:100%;" placeholder="Price"></td>
				<td width="10%" style="text-align:center;" class="bkap_global_time_td">
					<label class="bkap_switch">
					   <input id="bkap_global_time" type="checkbox" name="bkap_global_timeslot" style="margin-left: 35%;">
					   <div class="bkap_slider round"></div>
					</label>
				</td>
				<td width="23%" class="bkap_note_time_td"><textarea id="bkap_note_time" rows="1" cols="2" style="width:100%;"></textarea></td>
				<td width="4%" id="bkap_close" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
			</tr>
			<?php
		}

		/**
		 * This function saves the data from the tabs.
		 * Different save buttons are present in each tab.
		 * They all will call this function, which will check
		 * the data present and save the same.
		 *
		 * @since 4.0.0
		 */

		public static function bkap_save_settings() {

			$post_id = $_POST['product_id']; // Product ID : Array of IDs if its from Bulk setting else single product id.

			if ( is_array( $post_id ) ) {

				if ( in_array( 'all_products', $post_id ) ) { // If all product is selected then get all product ids.
					$all_product_ids = $_POST['all_product_ids'];
					$post_id         = explode( ',', $all_product_ids );
				} else {
					$slugs        = array();
					$all_products = $post_id;

					// Get Product Category values in Post ID array.
					$product_categories = array_filter(
						$post_id,
						function ( $data ) {
							return strpos( $data, 'cat_' ) !== false;
						}
					);

					// Check if Product Category has been selected.
					if ( count( $product_categories ) > 0 ) {
						foreach ( $product_categories as $category ) {

							// Remove category id from product array.
							if ( ( $key = array_search( $category, $all_products ) ) !== false ) {
								unset( $all_products[ $key ] );
							}

							$slugs[] = str_replace( 'cat_', '', $category );
						}

						if ( count( $slugs ) > 0 ) {

							$args = array(
								'posts_per_page' => -1,
								'post_type'      => 'product',
								'tax_query'      => array(
									'relation' => 'AND',
									array(
										'taxonomy' => 'product_cat',
										'field'    => 'slug',
										'terms'    => $slugs,
									),
								),
							);

							$products = get_posts( $args );

							// Add array of products to original array.
							foreach ( $products as $key => $product ) {
								$all_products[] = $product->ID;
							}

							// Return unique Post IDs to remove Products that were selected and are also existing in Product Category.
							$post_id = array_unique( $all_products );
						}
					}
				}

				// Get Product Count. If it is more than 50, then we invoke the background processing action.

				$products_count = count( $post_id );

				if ( $products_count >= 50 ) {

					$bkap_bulk_booking_settings = bkap_bulk_booking_settings();

					if ( isset( $bkap_bulk_booking_settings ) ) {

						$sent_for_processing = 0;
						$product_settings    = array();

						if ( isset( $_POST['booking_options'] ) ) { //phpcs:ignore
							$product_settings['booking_options'] = $_POST['booking_options']; //phpcs:ignore
						}

						if ( isset( $_POST['settings_data'] ) ) { //phpcs:ignore
							$product_settings['settings_data'] = $_POST['settings_data']; //phpcs:ignore
						}

						if ( isset( $_POST['blocks_enabled'] ) ) { //phpcs:ignore
							$product_settings['blocks_enabled'] = $_POST['blocks_enabled']; //phpcs:ignore
						}

						if ( isset( $_POST['fixed_block_data'] ) ) { //phpcs:ignore
							$product_settings['fixed_block_data'] = $_POST['fixed_block_data'];
						} //phpcs:ignore

						if ( isset( $_POST['ranges_enabled'] ) ) { //phpcs:ignore
							$product_settings['ranges_enabled'] = $_POST['ranges_enabled']; //phpcs:ignore
						}

						if ( isset( $_POST['price_range_data'] ) ) { //phpcs:ignore
							$product_settings['price_range_data'] = $_POST['price_range_data']; //phpcs:ignore
						}

						if ( isset( $_POST['resource_data'] ) ) { //phpcs:ignore
							$product_settings['resource_data'] = $_POST['resource_data']; //phpcs:ignore
						}

						if ( isset( $_POST['person_data'] ) ) { //phpcs:ignore
							$product_settings['person_data'] = $_POST['person_data'];
						} //phpcs:ignore

						if ( isset( $_POST['gcal_data'] ) ) { //phpcs:ignore
							$product_settings['gcal_data'] = $_POST['gcal_data']; //phpcs:ignore
						}

						if ( isset( $_POST['rental_data'] ) ) { //phpcs:ignore
							$product_settings['rental_data'] = $_POST['rental_data']; //phpcs:ignore
						}

						foreach ( $post_id as $pk => $pv ) {
							$product_id = bkap_common::bkap_get_product_id( $pv );

							$bkap_bulk_booking_settings->push_to_queue(
								array(
									'product_id'       => $product_id,
									'product_settings' => $product_settings,
							) ); //phpcs:ignore

							$sent_for_processing++;
						}

						if ( ! $sent_for_processing ) {
							return;
						}

						set_transient( 'bkap_bulk_booking_settings_background_process_running', 0 );
						$bkap_bulk_booking_settings->save()->dispatch();

						$return = array( 'message' => 'Settings are being saved in the background.' );
					}
				} else {
					foreach ( $post_id as $pk => $pv ) {
						$product_id = bkap_common::bkap_get_product_id( $pv );
						self::bkap_inactive_old_records_for_product( $product_id );
						self::bkap_save_settingss( $product_id );
					}

					$return = array( 'message' => 'Settings have been saved!!!' );
				}

				wp_send_json( $return );
			} else {
				$product_id = bkap_common::bkap_get_product_id( $post_id );
				self::bkap_save_settingss( $product_id );
				$return = array( 'message' => "Settings are saved for $product_id!!!" );

				wp_send_json( $return );
			}
		}

		/**
		 * Inactivating the old records for the product before adding the new settings from bulk booking settings.
		 *
		 * @since 4.16.0
		 */

		public static function bkap_inactive_old_records_for_product( $product_id ) {

			global $wpdb;

			$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                            SET status = 'inactive'
                            WHERE post_id = '" . $product_id . "'";
							$wpdb->query( $query_update );
		}

		/**
		 * Saving the selected booking settings for the product.
		 *
		 * @since 4.16.0
		 */

		public static function bkap_save_settingss( $product_id ) {

			// Booking Options Tab settings.
			$clean_booking_options = '';
			if ( isset( $_POST['booking_options'] ) ) {
				$post_booking_options  = $_POST['booking_options'];
				$tempData              = str_replace( '\\', '', $post_booking_options );
				$clean_booking_options = json_decode( $tempData );
			}

			// Settings Tab settings.
			$clean_settings_data = '';
			if ( isset( $_POST['settings_data'] ) ) {
				$post_settings_data  = $_POST['settings_data'];
				$tempData            = str_replace( '\\', '', $post_settings_data );
				$clean_settings_data = json_decode( $tempData );
			}

			$ranges_array = array();
			// Fixed Blocks Tab.
			if ( isset( $_POST['blocks_enabled'] ) ) {
				$ranges_array['blocks_enabled'] = $_POST['blocks_enabled'];
			}

			// Fixed Block Booking table data.
			$clean_fixed_block_data = '';
			if ( isset( $_POST['fixed_block_data'] ) ) {
				$post_fixed_block_data  = $_POST['fixed_block_data'];
				$tempData               = str_replace( '\\', '', $post_fixed_block_data );
				$clean_fixed_block_data = json_decode( $tempData );
			}

			// Price Ranges Tab.
			if ( isset( $_POST['ranges_enabled'] ) ) {
				$ranges_array['ranges_enabled'] = $_POST['ranges_enabled'];
			}

			// Price By Range table data.
			$clean_price_range_data = '';
			if ( isset( $_POST['price_range_data'] ) ) {
				$post_price_range_data  = $_POST['price_range_data'];
				$clean_price_range_data = (object) array( 'bkap_price_range_data' => stripslashes( $post_price_range_data ) );
			}

			// Resource Tab settings.
			$clean_resource_data = '';
			if ( isset( $_POST['resource_data'] ) ) {
				$post_resource_data  = $_POST['resource_data'];
				$tempData            = str_replace( '\\', '', $post_resource_data );
				$clean_resource_data = json_decode( $tempData );
			}

			// Person Tab settings.
			$clean_person_data = '';
			if ( isset( $_POST['person_data'] ) ) {
				$post_person_data  = $_POST['person_data'];
				$tempData          = str_replace( '\\', '', $post_person_data );
				$clean_person_data = json_decode( $tempData );
			}

			$clean_gcal_data = '';
			if ( isset( $_POST['gcal_data'] ) ) {
				$post_gcal_data  = $_POST['gcal_data'];
				$tempData        = str_replace( '\\', '', $post_gcal_data );
				$clean_gcal_data = json_decode( $tempData );
			}

			$clean_rental_data = '';
			if ( isset( $_POST['rental_data'] ) ) { //phpcs:ignore
				$post_rental_data  = $_POST['rental_data']; //phpcs:ignore
				$tempData          = str_replace( '\\', '', $post_rental_data );
				$clean_rental_data = json_decode( $tempData );
			}

			$booking_box_class = new bkap_booking_box_class();
			$booking_box_class->setup_data(
				$product_id,
				$clean_booking_options,
				$clean_settings_data,
				$ranges_array,
				$clean_gcal_data,
				$clean_rental_data,
				$clean_fixed_block_data,
				$clean_price_range_data,
				$clean_resource_data,
				$clean_person_data
			);
		}

		/**
		 * Creates the data to be saved in the DB when either the Save button is
		 * clicked from one of the tabs or the product is Published/Updated.
		 *
		 * @param int      $product_id
		 * @param stdClass $clean_booking_options
		 * @param stdClass $clean_settings_data
		 *
		 * @since 4.0.0
		 */

		function setup_data( $product_id, $clean_booking_options, $clean_settings_data, $ranges_array, $clean_gcal_data, $clean_rental_data, $clean_fixed_block_data, $clean_price_range_data, $clean_resource_data = array(), $clean_person_data = array() ) {

			$final_booking_options = array();
			$settings_data         = array();
			$block_ranges          = array();
			$gcal_data             = array();
			$rental_data           = array();
			$fixed_block_data      = array();
			$price_range_data      = array();
			$final_resource_data   = array();
			$final_person_data     = array();

			if ( $clean_booking_options != '' && count( get_object_vars( $clean_booking_options ) ) > 0 ) {

				$final_booking_options['_bkap_enable_booking']        = $clean_booking_options->booking_enable_date;
				$final_booking_options['_bkap_booking_type']          = $clean_booking_options->booking_type;
				$final_booking_options['_bkap_enable_inline']         = $clean_booking_options->enable_inline;
				$final_booking_options['_bkap_purchase_wo_date']      = $clean_booking_options->purchase_wo_date;
				$final_booking_options['_bkap_requires_confirmation'] = $clean_booking_options->requires_confirmation;

				$final_booking_options['_bkap_can_be_cancelled'] = array();

				if ( isset( $clean_booking_options->can_be_cancelled ) ) {

					if ( isset( $clean_booking_options->can_be_cancelled->status ) ) {
						$final_booking_options['_bkap_can_be_cancelled']['status'] = $clean_booking_options->can_be_cancelled->status;
					}

					if ( isset( $clean_booking_options->can_be_cancelled->duration ) ) {
						$final_booking_options['_bkap_can_be_cancelled']['duration'] = $clean_booking_options->can_be_cancelled->duration;
					}

					if ( isset( $clean_booking_options->can_be_cancelled->period ) ) {
						$final_booking_options['_bkap_can_be_cancelled']['period'] = $clean_booking_options->can_be_cancelled->period;
					}
				}

				$final_booking_options = apply_filters( 'bkap_add_setup_data_for_booking_options', $final_booking_options, $clean_booking_options );

				if ( isset( $clean_booking_options->wkpbk_block_single_week ) && isset( $clean_booking_options->special_booking_start_weekday ) && isset( $clean_booking_options->special_booking_end_weekday ) ) {

					$final_booking_options['_bkap_week_blocking'] = $clean_booking_options->wkpbk_block_single_week;
					$final_booking_options['_bkap_start_weekday'] = $clean_booking_options->special_booking_start_weekday;
					$final_booking_options['_bkap_end_weekday']   = $clean_booking_options->special_booking_end_weekday;
				}

				$final_booking_options['_bkap_multidates_type']         = $clean_booking_options->multidates_type;
				$final_booking_options['_bkap_multidates_fixed_number'] = $clean_booking_options->multidates_fixed_number;
				$final_booking_options['_bkap_multidates_range_min']    = $clean_booking_options->multidates_range_min;
				$final_booking_options['_bkap_multidates_range_max']    = $clean_booking_options->multidates_range_max;
			}

			 /* Options of the general tab is prepared in $final_booking_option variable */

			if ( $clean_settings_data != '' && count( get_object_vars( $clean_settings_data ) ) > 0 ) {

				// Booking enabled
				if ( isset( $clean_booking_options->booking_enable_date ) && '' != $clean_booking_options->booking_enable_date ) {
					$booking_enabled = $clean_booking_options->booking_enable_date;
				} else {
					$booking_enabled = get_post_meta( $product_id, '_bkap_enable_booking', true );
				}

				// Booking Type
				if ( isset( $clean_booking_options->booking_type ) && '' != $clean_booking_options->booking_type ) {
					$booking_type = $clean_booking_options->booking_type;
				} else {
					$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );
				}

				$settings_data['_bkap_abp']               = $clean_settings_data->abp;
				$settings_data['_bkap_max_bookable_days'] = $clean_settings_data->max_bookable;

				if ( isset( $clean_settings_data->date_lockout ) ) {
					$settings_data['_bkap_date_lockout'] = $clean_settings_data->date_lockout;
				}

				if ( isset( $clean_settings_data->min_days_multiple ) ) {
					$settings_data['_bkap_multiple_day_min'] = $clean_settings_data->min_days_multiple;
				}

				if ( isset( $clean_settings_data->max_days_multiple ) ) {
					$settings_data['_bkap_multiple_day_max'] = $clean_settings_data->max_days_multiple;
				}

				$booking_recurring = array();
				$recurring_lockout = array();
				$recurring_prices  = array();

				for ( $i = 0; $i <= 6; $i++ ) {
					$weekday_name = "booking_weekday_$i";
					$lockout_name = "weekday_lockout_$i";
					$price_name   = "weekday_price_$i";

					$booking_recurring[ $weekday_name ] = $clean_settings_data->$weekday_name;
					$recurring_lockout[ $weekday_name ] = isset( $clean_settings_data->$lockout_name ) ? $clean_settings_data->$lockout_name : 0;

					if ( is_numeric( $clean_settings_data->$price_name ) ) {
						$recurring_prices[ $weekday_name ] = $clean_settings_data->$price_name;
					}
				}

				$enable_recurring = '';
				if ( in_array( 'on', $booking_recurring ) ) {
					$enable_recurring = 'on';
				}

				$settings_data['_bkap_enable_recurring']   = $enable_recurring;
				$settings_data['_bkap_recurring_weekdays'] = $booking_recurring;
				$settings_data['_bkap_recurring_lockout']  = $recurring_lockout;
				$settings_data['_bkap_enable_specific']    = $clean_settings_data->enable_specific;
				$settings_data['_bkap_product_holidays']   = $this->create_date_list( $clean_settings_data->holidays_list );

				// $settings_data[ '_bkap_specific_dates' ] = $this->create_date_list( $clean_settings_data->specific_list );
				$settings_data['_bkap_specific_dates'] = $this->create_date_list( $clean_settings_data->specific_list );
				$specific_prices                       = $this->create_specific_price_list( $clean_settings_data->specific_list );

				// update the special prices
				$special_price_class                  = new bkap_special_booking_price();
				$settings_data['_bkap_special_price'] = $special_price_class->bkap_save_special_booking_price( $product_id, $recurring_prices, $specific_prices );

				$settings_data['_bkap_custom_ranges']  = $this->create_range_data( $clean_settings_data->custom_range );
				$settings_data['_bkap_holiday_ranges'] = $this->create_range_data( $clean_settings_data->holiday_range );
				$settings_data['_bkap_month_ranges']   = $this->create_range_data( $clean_settings_data->month_range );

				// date & time settings
				$booking_time_settings  = array();
				$existing_time_settings = get_post_meta( $product_id, '_bkap_time_settings', true );

				if ( isset( $clean_settings_data->booking_times ) && count( get_object_vars( $clean_settings_data->booking_times ) ) > 0 ) {

					foreach ( $clean_settings_data->booking_times as $booking_times ) {

						$record_present = false; // assume no record is present for this date/day and time slot
						$days           = array();

						if ( is_array( $booking_times->day ) && count( $booking_times->day ) > 0 ) {

							foreach ( $booking_times->day as $day ) {
								$days[] = $day;
							}
						} else {
							$days[] = $booking_times->day;
						}

						// check if any of the values is set to 'ALL' if so, then unset it and insert records for all the values in the dropdown
						foreach ( $days as $d_key => $d_value ) {

							if ( 'all' == $d_value ) {

								unset( $days[ $d_key ] );

								// add records for all the days/dates
								foreach ( $booking_recurring as $b_key => $b_value ) {
									if ( 'on' == $b_value ) {
										$days[] = $b_key;
									}
								}

								// specific dates
								foreach ( $settings_data['_bkap_specific_dates'] as $dates => $lockout ) {
									$days[] = $dates;
								}
							}
						}

						// for all the days
						foreach ( $days as $day_check ) {

							$from_slot_array = explode( ':', $booking_times->from_time );

							$from_slot_hrs = trim( $from_slot_array[0] );
							$from_slot_min = isset( $from_slot_array[1] ) ? trim( $from_slot_array[1] ) : '00';

							$from_slot_hrs = ( $from_slot_hrs != '' ) ? $from_slot_hrs : '00';
							$from_slot_min = ( $from_slot_min != '' ) ? $from_slot_min : '00';

							$to_slot_hrs = '0';
							$to_slot_min = '00';

							if ( isset( $booking_times->to_time ) && '' != $booking_times->to_time ) {
								$to_slot_array = explode( ':', $booking_times->to_time );

								$to_slot_hrs = trim( $to_slot_array[0] );
								$to_slot_min = trim( $to_slot_array[1] );
							}

							// check if a record exists already
							if ( is_array( $existing_time_settings ) && count( $existing_time_settings ) > 0 ) {

								// check if there's a record present for that day/date
								if ( array_key_exists( $day_check, $existing_time_settings ) ) {

									foreach ( $existing_time_settings[ $day_check ] as $key => $existing_record ) {

										if ( $from_slot_hrs == $existing_record['from_slot_hrs']
										&& $from_slot_min == $existing_record['from_slot_min']
										&& $to_slot_hrs == $existing_record['to_slot_hrs']
										&& $to_slot_min == $existing_record['to_slot_min'] ) {

											$new_key        = $key;
											$record_present = true;
											break;
										}
									}
								}
							}

							if ( ! $record_present ) {
								// check if there's a record present for that day/date
								if ( array_key_exists( $day_check, $booking_time_settings ) ) {
									$new_key = max( array_keys( $booking_time_settings[ $day_check ] ) ) + 1;
								} else {
									$new_key = 0;
								}
							}

							$booking_time_settings[ $day_check ][ $new_key ]['from_slot_hrs']     = $from_slot_hrs;
							$booking_time_settings[ $day_check ][ $new_key ]['from_slot_min']     = $from_slot_min;
							$booking_time_settings[ $day_check ][ $new_key ]['to_slot_hrs']       = $to_slot_hrs;
							$booking_time_settings[ $day_check ][ $new_key ]['to_slot_min']       = $to_slot_min;
							$booking_time_settings[ $day_check ][ $new_key ]['booking_notes']     = $booking_times->booking_notes;
							$booking_time_settings[ $day_check ][ $new_key ]['slot_price']        = $booking_times->slot_price;
							$booking_time_settings[ $day_check ][ $new_key ]['lockout_slot']      = $booking_times->lockout_slot;
							$booking_time_settings[ $day_check ][ $new_key ]['global_time_check'] = $booking_times->global_time_check;
						}
					}

					if ( is_array( $booking_time_settings ) ) {
						$settings_data['_bkap_time_settings'] = $booking_time_settings;
					}

					$settings_data = apply_filters( 'bkap_additional_data_after_timeslots_calculator', $settings_data, $product_id, $clean_settings_data );
				}

				// Duration based booking

				if ( isset( $clean_settings_data->duration_times ) && count( get_object_vars( $clean_settings_data->duration_times ) ) > 0 ) {
					$settings_data['_bkap_duration_settings'] = (array) $clean_settings_data->duration_times;
				}
			}

			if ( isset( $clean_settings_data->manage_time_availability ) && count( $clean_settings_data->manage_time_availability ) > 0 ) {
				$settings_data['_bkap_manage_time_availability'] = json_decode( json_encode( $clean_settings_data->manage_time_availability ), true );
			}

			if ( isset( $clean_settings_data->all_data_unavailable ) ) {
				$settings_data['_bkap_all_data_unavailable'] = $clean_settings_data->all_data_unavailable;
			}

			/* Options of the availability tab is prepared in $settings variable */

			if ( isset( $ranges_array['blocks_enabled'] ) ) {
				$block_ranges['_bkap_fixed_blocks'] = $ranges_array['blocks_enabled'];
			}

			// Fixed Block bookings data.
			if ( $clean_fixed_block_data != '' && count( get_object_vars( $clean_fixed_block_data ) ) > 0 ) {
				$block_ranges['_bkap_fixed_blocks_data'] = bkap_block_booking::bkap_updating_fixed_block_data_in_db( $product_id, $clean_fixed_block_data );

			}

			if ( isset( $ranges_array['ranges_enabled'] ) ) {
				$block_ranges['_bkap_price_ranges'] = $ranges_array['ranges_enabled'];
			}

			// Price by range of day data.
			if ( $clean_price_range_data != '' && count( get_object_vars( $clean_price_range_data ) ) > 0 ) {
				$block_ranges['_bkap_price_range_data'] = bkap_block_booking::bkap_updating_price_range_data_in_db( $product_id, $clean_price_range_data );
			}

			/* Options of the block pricing tab is prepared in $block_ranges variable */

			if ( $clean_gcal_data != '' && count( get_object_vars( $clean_gcal_data ) ) > 0 ) {

				$gcal_data['_bkap_gcal_integration_mode'] = $clean_gcal_data->gcal_sync_mode;
				$gcal_data['_bkap_gcal_key_file_name']    = $clean_gcal_data->key_file_name;
				$gcal_data['_bkap_gcal_service_acc']      = $clean_gcal_data->service_acc_email;
				$gcal_data['_bkap_gcal_calendar_id']      = $clean_gcal_data->calendar_id;

				if ( isset( $clean_gcal_data->gcal_auto_mapping ) ) {
					$gcal_data['_bkap_enable_automated_mapping'] = $clean_gcal_data->gcal_auto_mapping;
				}

				if ( isset( $clean_gcal_data->default_variation ) ) {
					$gcal_data['_bkap_default_variation'] = $clean_gcal_data->default_variation;
				}

				if ( isset( $clean_gcal_data->bkap_calendar_oauth_integration ) ) {
					$bkap_calendar_oauth_integration                                = $clean_gcal_data->bkap_calendar_oauth_integration;
					$gcal_data['_bkap_calendar_oauth_integration']['client_id']     = $bkap_calendar_oauth_integration->client_id;
					$gcal_data['_bkap_calendar_oauth_integration']['client_secret'] = $bkap_calendar_oauth_integration->client_secret;
					$gcal_data['_bkap_calendar_oauth_integration']['calendar_id']   = $bkap_calendar_oauth_integration->calendar_id;
				}

				$import_feed_url = array();

				for ( $i = 0; ; $i++ ) {
					$field_name = "ics_feed_url_$i";

					if ( isset( $clean_gcal_data->$field_name ) ) {
						$import_feed_url[ $i ] = $clean_gcal_data->$field_name;
					} else {
						break;
					}
				}
				$gcal_data['_bkap_import_url'] = $import_feed_url;

				// Zoom option.
				$gcal_data['_bkap_zoom_meeting']      = isset( $clean_gcal_data->zoom_meeting ) ? $clean_gcal_data->zoom_meeting : '';
				$gcal_data['_bkap_zoom_meeting_host'] = isset( $clean_gcal_data->zoom_meeting_host ) ? $clean_gcal_data->zoom_meeting_host : '';

				$gcal_data['_bkap_zoom_meeting_auth']              = isset( $clean_gcal_data->zoom_meeting_auth ) ? $clean_gcal_data->zoom_meeting_auth : false;
				$gcal_data['_bkap_zoom_meeting_join_before_host']  = isset( $clean_gcal_data->zoom_meeting_join_before_host ) ? $clean_gcal_data->zoom_meeting_join_before_host : false;
				$gcal_data['_bkap_zoom_meeting_host_video']        = isset( $clean_gcal_data->zoom_meeting_host_video ) ? $clean_gcal_data->zoom_meeting_host_video : false;
				$gcal_data['_bkap_zoom_meeting_participant_video'] = isset( $clean_gcal_data->zoom_meeting_participant_video ) ? $clean_gcal_data->zoom_meeting_participant_video : false;
				$gcal_data['_bkap_zoom_meeting_mute_upon_entry']   = isset( $clean_gcal_data->zoom_meeting_mute_upon_entry ) ? $clean_gcal_data->zoom_meeting_mute_upon_entry : false;
				$gcal_data['_bkap_zoom_meeting_auto_recording']    = isset( $clean_gcal_data->zoom_meeting_auto_recording ) ? $clean_gcal_data->zoom_meeting_auto_recording : 'local';
				$gcal_data['_bkap_zoom_meeting_alternative_host']  = isset( $clean_gcal_data->zoom_meeting_alternative_host ) ? $clean_gcal_data->zoom_meeting_alternative_host : '';

				// FluentCRM.
				$gcal_data['_bkap_fluentcrm']      = isset( $clean_gcal_data->bkap_fluentcrm ) ? $clean_gcal_data->bkap_fluentcrm : '';
				$gcal_data['_bkap_fluentcrm_list'] = isset( $clean_gcal_data->bkap_fluentcrm_list ) ? $clean_gcal_data->bkap_fluentcrm_list : '';

				$gcal_data = apply_filters( 'bkap_product_integration_data', $gcal_data, $product_id, $clean_gcal_data );
			}

			/* Options of the rental tab is prepared in $rental_data variable */

			if ( $clean_rental_data != '' && count( get_object_vars( $clean_rental_data ) ) > 0 ) {
				$rental_data['_bkap_booking_prior_days_to_book'] = $clean_rental_data->booking_prior_days_to_book;
				$rental_data['_bkap_booking_later_days_to_book'] = $clean_rental_data->booking_later_days_to_book;
				$rental_data['_bkap_booking_charge_per_day']     = isset( $clean_rental_data->booking_charge_per_day ) ? $clean_rental_data->booking_charge_per_day : '';
				$rental_data['_bkap_booking_same_day']           = isset( $clean_rental_data->booking_same_day ) ? $clean_rental_data->booking_same_day: '';
			}

			/* Options of the gval sync tab is prepared in $gcal_data variable */

			if ( $clean_resource_data != '' && count( get_object_vars( $clean_resource_data ) ) > 0 ) {
				$final_resource_data['_bkap_resource']                        = $clean_resource_data->_bkap_resource;
				$final_resource_data['_bkap_product_resource_lable']          = $clean_resource_data->_bkap_product_resource_lable;
				$final_resource_data['_bkap_product_resource_selection']      = $clean_resource_data->_bkap_product_resource_selection;
				$final_resource_data['_bkap_product_resource_selection_type'] = $clean_resource_data->_bkap_product_resource_selection_type;
				$final_resource_data['_bkap_product_resource_max_booking']    = $clean_resource_data->_bkap_product_resource_max_booking;
				$final_resource_data['_bkap_product_resource_sorting']        = $clean_resource_data->_bkap_product_resource_sorting;
				$final_resource_data['_bkap_product_resources']               = $clean_resource_data->_bkap_product_resources;
				$final_resource_data['_bkap_resource_base_costs']             = (array) $clean_resource_data->_bkap_resource_base_costs;
			}

			if ( $clean_person_data != '' && count( get_object_vars( $clean_person_data ) ) > 0 ) {
				$final_person_data['_bkap_person']              = $clean_person_data->bkap_person;
				$final_person_data['_bkap_min_person']          = $clean_person_data->bkap_min_person;
				$final_person_data['_bkap_max_person']          = $clean_person_data->bkap_max_person;
				$final_person_data['_bkap_price_per_person']    = $clean_person_data->bkap_price_per_person;
				$final_person_data['_bkap_each_person_booking'] = $clean_person_data->bkap_each_person_booking;
				$final_person_data['_bkap_person_type']         = $clean_person_data->bkap_person_type;
				$final_person_data['_bkap_person_ids']          = (array) $clean_person_data->bkap_person_ids;
				$final_person_data['_bkap_person_data']         = json_decode( json_encode( $clean_person_data->bkap_person_data ), true );
			}

			// update individual settings.
			$this->update_single_post_meta( $product_id, $final_booking_options, $settings_data, $block_ranges, $gcal_data, $rental_data, $final_resource_data, $final_person_data );

			// update old post meta record.
			$this->update_serialized_post_meta( $product_id, $final_booking_options, $settings_data, $block_ranges, $gcal_data, $rental_data, $final_resource_data, $final_person_data );

			// update booking history.
			if ( ( isset( $booking_enabled ) && 'on' == $booking_enabled ) && isset( $booking_type ) && in_array( $booking_type, array( 'only_day', 'multidates' ) ) ) {
				$this->update_bkap_history_only_days( $product_id, $settings_data );
			} elseif ( ( isset( $booking_enabled ) && 'on' == $booking_enabled ) && isset( $booking_type ) && in_array( $booking_type, array( 'date_time', 'multidates_fixedtime' ) ) ) {
				$this->update_bkap_history_date_time( $product_id, $settings_data );
			}
		}

		/**
		 * Receives a string which contains a list of dates and
		 * the number of recurring years. It splits it into an array
		 * where the date is the key and the number of years is the value
		 *
		 * @param str $dates_string
		 * String format is as below:
		 * date1,date2+years;date3+years;....
		 * @return array $dates_array
		 *
		 * @since 4.0.0
		 */

		function create_date_list( $dates_string ) {

			$dates_array = array();
			$dates_split = explode( ';', $dates_string );

			// if dates have been set up
			if ( is_array( $dates_split ) && count( $dates_split ) > 0 ) {
				foreach ( $dates_split as $d_value ) {
					if ( $d_value != '' ) {

						$dates_list  = $d_value;
						$recur_years = 0;
						// recurring years and prices are added using +
						$recurring_setup = strpos( $d_value, '+' );
						// check if recurring years have been setup
						if ( $recurring_setup !== false ) {
							$dates_list    = substr( $d_value, 0, $recurring_setup );
							$explode_dates = explode( '+', $d_value );
							if ( $explode_dates[1] > 0 ) {
								$recur_years = $explode_dates[1];
							}
						}
						// get the dates list, there maybe more than 1 dates comma separated
						$explode_dates = explode( ',', $dates_list );
						foreach ( $explode_dates as $single_date ) {
							if ( '' != $single_date ) {
								$dates_array[ $single_date ] = $recur_years;
							}
						}
					}
				}
			}

			return $dates_array;
		}

		/**
		 * Creates and returns an array of specific dates with their prices
		 *
		 * @param string $dates_string - Specific Dates with the prices
		 * @since 4.0.0
		 * @return array $dates_array - [date] = 'Price'
		 */

		function create_specific_price_list( $dates_string ) {

			$dates_array = array();

			$dates_split = explode( ';', $dates_string );

			// if dates have been set up
			if ( is_array( $dates_split ) && count( $dates_split ) > 0 ) {
				foreach ( $dates_split as $d_value ) {
					if ( $d_value != '' ) {

						$dates_list  = $d_value;
						$recur_years = 0;
						$date_price  = '';

						$recurring_setup = strpos( $d_value, '+' );
						// check if recurring years & price have been setup
						if ( $recurring_setup !== false ) {
							$dates_list    = substr( $d_value, 0, $recurring_setup );
							$explode_dates = explode( '+', $d_value );
							// check if price is set
							if ( isset( $explode_dates[2] ) && is_numeric( $explode_dates[2] ) && $explode_dates[2] >= 0 ) {
								$date_price = $explode_dates[2];
							}
						}
						// get the dates list
						$explode_dates = explode( ',', $dates_list );
						foreach ( $explode_dates as $single_date ) {
							if ( '' != $single_date && is_numeric( $date_price ) ) {
								$dates_array[ $single_date ] = $date_price;
							}
						}
					}
				}
			}

			return $dates_array;
		}

		/**
		 * Returns an array of the custom ranges data.
		 * This includes the Ranges setup using 'Custom Range' as
		 * well as 'Range of Months'.
		 *
		 * @param string $range_string - Contains the data passed from the table in string format
		 * @return array $range_array - Array with keys 'start', 'end', years_to_recur' and 'range_type'
		 * @global array $bkap_month array of months
		 *
		 * @since 4.0.0
		 */

		function create_range_data( $range_string ) {
			global $bkap_months;
			$range_array = array();

			$range_split = explode( ';', $range_string );

			$current_year = date( 'Y', current_time( 'timestamp' ) );
			$next_year    = date( 'Y', strtotime( '+1 year' ) );

			// if ranges have been set up
			if ( is_array( $range_split ) && count( $range_split ) > 0 ) {
				foreach ( $range_split as $r_value ) {
					if ( $r_value != '' ) {
						$range_start = '';
						$range_end   = '';
						$range_type  = '';
						$range_recur = 0;

						$explode_range = explode( '+', $r_value );

						if ( isset( $explode_range[0] ) ) {
							$range_start = $explode_range[0];
							if ( is_numeric( $range_start ) ) { // it's a month number
								$month_name   = $bkap_months[ $range_start ];
								$month_to_use = "$month_name $current_year";
								$range_start  = date( 'j-n-Y', strtotime( $month_to_use ) );
							} else { // it is a date
								if ( $range_start == '' ) {
									continue; // pick the next range
								} else {
									$range_start = date( 'j-n-Y', strtotime( $range_start ) );
								}
							}
						}
						if ( isset( $explode_range[1] ) ) {
							$range_end = $explode_range[1];
							if ( is_numeric( $range_end ) ) { // it's a month number
								$month_name = $bkap_months[ $range_end ];

								if ( $explode_range[0] <= $explode_range[1] ) {
									$month_to_use = "$month_name $current_year";
								} else {
									$month_to_use = "$month_name $next_year";
								}
								$month_start = date( 'j-n-Y', strtotime( $month_to_use ) );

								$days      = date( 't', strtotime( $month_start ) );
								$days     -= 1;
								$range_end = date( 'j-n-Y', strtotime( "+$days days", strtotime( $month_start ) ) );

							} else { // it is a date
								if ( $range_end == '' ) {
									continue; // pick the next range
								} else {
									$range_end = date( 'j-n-Y', strtotime( $range_end ) );
								}
							}
						}
						if ( isset( $explode_range[2] ) ) {
							$range_recur = $explode_range[2];
						}

						if ( isset( $explode_range[3] ) ) {
							$range_type = $explode_range[3];
						}

						$range_array[] = array(
							'start'          => $range_start,
							'end'            => $range_end,
							'years_to_recur' => $range_recur,
							'range_type'     => $range_type,
						);

					}
				}
			}
			return $range_array;
		}

		/**
		 * Updates the individual booking settings data in the
		 * post meta table.
		 *
		 * @param int   $product_id - Product ID
		 * @param array $booking_options - Data for Booking Options tab
		 * @param array $settings_data - Data for Availability tab
		 * @param array $block_ranges - Data for Block Pricing tab
		 * @param array $gcal_data - Data for Google Sync Settings tab
		 * @param array $rental_data - Data for Rental Settings tab
		 *
		 * @since 4.0.0
		 */

		function update_single_post_meta( $product_id, $booking_options, $settings_data, $block_ranges, $gcal_data, $rental_data, $resource_data, $person_data ) {

			if ( is_array( $booking_options ) && count( $booking_options ) > 0 ) {
				foreach ( $booking_options as $booking_key => $booking_value ) {
					update_post_meta( $product_id, $booking_key, $booking_value );
				}
			}

			if ( is_array( $settings_data ) && count( $settings_data ) > 0 ) {
				foreach ( $settings_data as $settings_key => $settings_value ) {
					update_post_meta( $product_id, $settings_key, $settings_value );
				}
			}

			if ( is_array( $block_ranges ) && count( $block_ranges ) > 0 ) {
				foreach ( $block_ranges as $br_keys => $br_values ) {
					update_post_meta( $product_id, $br_keys, $br_values );
				}
			}

			if ( is_array( $gcal_data ) && count( $gcal_data ) > 0 ) {
				foreach ( $gcal_data as $gcal_key => $gcal_value ) {
					update_post_meta( $product_id, $gcal_key, $gcal_value );
				}
			}

			if ( is_array( $rental_data ) && count( $rental_data ) > 0 ) {
				foreach ( $rental_data as $rental_key => $rental_value ) {
					update_post_meta( $product_id, $rental_key, $rental_value );
				}
			}

			if ( is_array( $resource_data ) && count( $resource_data ) > 0 ) {
				foreach ( $resource_data as $resource_key => $resource_value ) {
					update_post_meta( $product_id, $resource_key, $resource_value );
				}
			}

			if ( is_array( $person_data ) && count( $person_data ) > 0 ) {
				foreach ( $person_data as $person_key => $person_value ) {
					update_post_meta( $product_id, $person_key, $person_value );
				}
			}

			if ( isset( $_POST['bkap_defaults'] ) && 'on' == $_POST['bkap_defaults'] ) {

				$bkap_default_booking_settings = array();
				$bkap_default_booking_settings = array_merge( $bkap_default_booking_settings, $booking_options, $settings_data, $block_ranges, $gcal_data, $resource_data, $person_data );

				update_option( 'bkap_default_individual_booking_settings', $bkap_default_booking_settings );
			}
		}

		/**
		 * Updates the 'woocommerce_booking_settings' record in
		 * postmeta table for the product.
		 *
		 * @param int   $product_id - Product ID
		 * @param array $booking_options - Data for Booking Options tab
		 * @param array $settings_data - Data for Availability tab
		 * @param array $block_ranges - Data for Block Pricing tab
		 * @param array $gcal_data - Data for Google Sync Settings tab
		 * @param array $rental_data - Data for Rental Settings tab
		 *
		 * @since 4.0.0
		 */

		function update_serialized_post_meta( $product_id, $booking_options, $settings_data, $block_ranges, $gcal_data, $rental_data, $resource_data, $person_data ) {

			// Save Bookings
			$updated_settings = array();

			if ( isset( $booking_options ) && is_array( $booking_options ) && count( $booking_options ) > 0 ) {

				if ( isset( $booking_options['_bkap_enable_booking'] ) ) {
					$updated_settings['booking_enable_date'] = $booking_options['_bkap_enable_booking'];
				} else {
					$updated_settings['booking_enable_date'] = '';
				}

				if ( isset( $booking_options['_bkap_booking_type'] ) && '' != $booking_options['_bkap_booking_type'] ) {

					switch ( $booking_options['_bkap_booking_type'] ) {
						case 'date_time':
							$updated_settings['booking_enable_multiple_day'] = '';
							$updated_settings['booking_enable_time']         = 'on';
							break;
						case 'duration_time':
							$updated_settings['booking_enable_multiple_day'] = '';
							$updated_settings['booking_enable_time']         = 'duration_time';
							break;
						case 'multiple_days':
							$updated_settings['booking_enable_multiple_day'] = 'on';
							$updated_settings['booking_enable_time']         = '';
							break;
						case 'only_day':
							$updated_settings['booking_enable_multiple_day'] = '';
							$updated_settings['booking_enable_time']         = '';
							break;
						case 'multidates':
							$updated_settings['booking_enable_multiple_day'] = 'multidates';
							$updated_settings['booking_enable_time']         = '';
							break;
						case 'multidates_fixedtime':
							$updated_settings['booking_enable_multiple_day'] = 'multidates';
							$updated_settings['booking_enable_time']         = 'dates_time';
							break;
						default:
							// code...
							break;
					}
				}

				$updated_settings['multidates_type']         = $booking_options['_bkap_multidates_type'];
				$updated_settings['multidates_fixed_number'] = $booking_options['_bkap_multidates_fixed_number'];
				$updated_settings['multidates_range_min']    = $booking_options['_bkap_multidates_range_min'];
				$updated_settings['multidates_range_max']    = $booking_options['_bkap_multidates_range_max'];

				$updated_settings = apply_filters( 'bkap_update_serialized_post_meta_after_booking_type', $updated_settings, $booking_options );

				if ( isset( $booking_options['_bkap_enable_inline'] ) ) {
					$updated_settings['enable_inline_calendar'] = $booking_options['_bkap_enable_inline'];
				} else {
					$updated_settings['enable_inline_calendar'] = '';
				}

				if ( isset( $booking_options['_bkap_purchase_wo_date'] ) ) {
					$updated_settings['booking_purchase_without_date'] = $booking_options['_bkap_purchase_wo_date'];
				} else {
					$updated_settings['booking_purchase_without_date'] = '';
				}

				if ( isset( $booking_options['_bkap_requires_confirmation'] ) ) {
					$updated_settings['booking_confirmation'] = $booking_options['_bkap_requires_confirmation'];
				} else {
					$updated_settings['booking_confirmation'] = '';
				}

				if ( isset( $booking_options['_bkap_week_blocking'] ) ) {
					$updated_settings['wkpbk_block_single_week'] = $booking_options['_bkap_week_blocking'];
				} else {
					$updated_settings['wkpbk_block_single_week'] = '';
				}

				if ( isset( $booking_options['_bkap_start_weekday'] ) ) {
					$updated_settings['special_booking_start_weekday'] = $booking_options['_bkap_start_weekday'];
				} else {
					$updated_settings['special_booking_start_weekday'] = '';
				}

				if ( isset( $booking_options['_bkap_end_weekday'] ) ) {
					$updated_settings['special_booking_end_weekday'] = $booking_options['_bkap_end_weekday'];
				} else {
					$updated_settings['special_booking_end_weekday'] = '';
				}

				$updated_settings['booking_can_be_cancelled'] = '';
				if ( isset( $booking_options['_bkap_can_be_cancelled'] ) ) {
					$updated_settings['booking_can_be_cancelled'] = $booking_options['_bkap_can_be_cancelled'];
				}

				$updated_settings = apply_filters( 'bkap_update_serialized_post_meta_booking_option', $updated_settings, $booking_options );
			}

			if ( isset( $settings_data ) && is_array( $settings_data ) && count( $settings_data ) > 0 ) {

				// product level - minimum booking for multiple days
				$multiple_min_days = 0;
				if ( isset( $settings_data['_bkap_multiple_day_min'] ) && $settings_data['_bkap_multiple_day_min'] > 0 ) {
					$updated_settings['booking_minimum_number_days_multiple'] = $settings_data['_bkap_multiple_day_min'];
					$updated_settings['enable_minimum_day_booking_multiple']  = 'on';
				} else {
					$updated_settings['enable_minimum_day_booking_multiple']  = '';
					$updated_settings['booking_minimum_number_days_multiple'] = 0;
				}

				$multiple_max_days = 365;
				if ( isset( $settings_data['_bkap_multiple_day_max'] ) && $settings_data['_bkap_multiple_day_max'] > 0 ) {
					$multiple_max_days = $settings_data['_bkap_multiple_day_max'];
				}
				$updated_settings['booking_maximum_number_days_multiple'] = $multiple_max_days;

				if ( isset( $settings_data['_bkap_custom_ranges'] ) ) {
					$updated_settings['booking_date_range'] = $settings_data['_bkap_custom_ranges'];
				} else {
					$updated_settings['booking_date_range'] = array();
				}

				if ( isset( $settings_data['_bkap_abp'] ) ) {
					$updated_settings['booking_minimum_number_days'] = $settings_data['_bkap_abp'];
				} else {
					$updated_settings['booking_minimum_number_days'] = 0;
				}

				if ( isset( $settings_data['_bkap_max_bookable_days'] ) ) {
					$updated_settings['booking_maximum_number_days'] = $settings_data['_bkap_max_bookable_days'];
				} else {
					$updated_settings['booking_maximum_number_days'] = '';
				}

				if ( isset( $settings_data['_bkap_date_lockout'] ) ) {
					$updated_settings['booking_date_lockout'] = $settings_data['_bkap_date_lockout'];
				} else {
					$updated_settings['booking_date_lockout'] = '';
				}

				if ( isset( $settings_data['_bkap_product_holidays'] ) ) {
					$updated_settings['booking_product_holiday'] = $settings_data['_bkap_product_holidays'];
				} else {
					$updated_settings['booking_product_holiday'] = array();
				}

				if ( isset( $settings_data['_bkap_specific_dates'] ) ) {
					$updated_settings['booking_specific_date'] = $settings_data['_bkap_specific_dates'];
				} else {
					$updated_settings['booking_specific_date'] = array();
				}

				if ( isset( $settings_data['_bkap_enable_recurring'] ) ) {
					$updated_settings['booking_recurring_booking'] = $settings_data['_bkap_enable_recurring'];
				} else {
					$updated_settings['booking_recurring_booking'] = '';
				}

				if ( isset( $settings_data['_bkap_recurring_weekdays'] ) ) {
					$updated_settings['booking_recurring'] = $settings_data['_bkap_recurring_weekdays'];
				} else {
					$updated_settings['booking_recurring'] = array();
				}

				if ( isset( $settings_data['_bkap_recurring_lockout'] ) ) {
					$updated_settings['booking_recurring_lockout'] = $settings_data['_bkap_recurring_lockout'];
				} else {
					$updated_settings['booking_recurring_lockout'] = array();
				}

				if ( isset( $settings_data['_bkap_enable_specific'] ) ) {
					$updated_settings['booking_specific_booking'] = $settings_data['_bkap_enable_specific'];
				} else {
					$updated_settings['booking_specific_booking'] = '';
				}

				if ( isset( $settings_data['_bkap_time_settings'] ) ) {
					$updated_settings['booking_time_settings'] = $settings_data['_bkap_time_settings'];
				} else {
					$updated_settings['booking_time_settings'] = array();
				}

				if ( isset( $settings_data['_bkap_duration_settings'] ) ) {
					$updated_settings['bkap_duration_settings'] = $settings_data['_bkap_duration_settings'];
				} else {
					$updated_settings['bkap_duration_settings'] = array();
				}

				if ( isset( $settings_data['_bkap_manage_time_availability'] ) ) {
					$updated_settings['bkap_manage_time_availability'] = $settings_data['_bkap_manage_time_availability'];
				} else {
					$updated_settings['bkap_manage_time_availability'] = array();
				}

				if ( isset( $settings_data['_bkap_all_data_unavailable'] ) ) {
					$updated_settings['bkap_all_data_unavailable'] = $settings_data['_bkap_all_data_unavailable'];
				} else {
					$updated_settings['bkap_all_data_unavailable'] = array();
				}

				$updated_settings = apply_filters( 'bkap_update_serialized_post_meta_availability_data', $updated_settings, $settings_data );
			}

			if ( isset( $block_ranges ) && is_array( $block_ranges ) && count( $block_ranges ) > 0 ) {
				if ( isset( $block_ranges['_bkap_fixed_blocks'] ) ) {
					$updated_settings['booking_fixed_block_enable'] = $block_ranges['_bkap_fixed_blocks'];
				}

				if ( isset( $block_ranges['_bkap_price_ranges'] ) ) {
					$updated_settings['booking_block_price_enable'] = $block_ranges['_bkap_price_ranges'];
				}

				if ( isset( $block_ranges['_bkap_price_range_data'] ) ) {
					$updated_settings['bkap_price_range_data'] = $block_ranges['_bkap_price_range_data'];
				}

				if ( isset( $block_ranges['_bkap_fixed_blocks_data'] ) ) {
					$updated_settings['bkap_fixed_blocks_data'] = $block_ranges['_bkap_fixed_blocks_data'];
				}
			}

			if ( isset( $gcal_data ) && is_array( $gcal_data ) && count( $gcal_data ) > 0 ) {

				if ( isset( $gcal_data['_bkap_gcal_integration_mode'] ) ) {
					$updated_settings['product_sync_integration_mode'] = $gcal_data['_bkap_gcal_integration_mode'];
				} else {
					$updated_settings['product_sync_integration_mode'] = '';
				}

				if ( isset( $gcal_data['_bkap_gcal_key_file_name'] ) ) {
					$updated_settings['product_sync_key_file_name'] = $gcal_data['_bkap_gcal_key_file_name'];
				} else {
					$updated_settings['product_sync_key_file_name'] = '';
				}

				if ( isset( $gcal_data['_bkap_gcal_service_acc'] ) ) {
					$updated_settings['product_sync_service_acc_email_addr'] = $gcal_data['_bkap_gcal_service_acc'];
				} else {
					$updated_settings['product_sync_service_acc_email_addr'] = '';
				}

				if ( isset( $gcal_data['_bkap_gcal_calendar_id'] ) ) {
					$updated_settings['product_sync_calendar_id'] = $gcal_data['_bkap_gcal_calendar_id'];
				} else {
					$updated_settings['product_sync_calendar_id'] = '';
				}

				if ( isset( $gcal_data['_bkap_enable_automated_mapping'] ) ) {
					$updated_settings['enable_automated_mapping'] = $gcal_data['_bkap_enable_automated_mapping'];
				} else {
					$updated_settings['enable_automated_mapping'] = '';
				}

				if ( isset( $gcal_data['_bkap_default_variation'] ) ) {
					$updated_settings['gcal_default_variation'] = $gcal_data['_bkap_default_variation'];
				} else {
					$updated_settings['gcal_default_variation'] = '';
				}

				if ( isset( $gcal_data['_bkap_import_url'] ) ) {
					$updated_settings['ics_feed_url'] = $gcal_data['_bkap_import_url'];
				} else {
					$updated_settings['ics_feed_url'] = array();
				}

				// OAuth Integration Settings.
				if ( isset( $gcal_data['_bkap_calendar_oauth_integration'] ) ) {
					$updated_settings['bkap_calendar_oauth_integration'] = $gcal_data['_bkap_calendar_oauth_integration'];
				} else {
					$updated_settings['bkap_calendar_oauth_integration'] = array();
				}

				// Zoom Meetings Settings.
				if ( isset( $gcal_data['_bkap_zoom_meeting'] ) ) {
					$updated_settings['zoom_meeting'] = $gcal_data['_bkap_zoom_meeting'];
				} else {
					$updated_settings['zoom_meeting'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_host'] ) ) {
					$updated_settings['zoom_meeting_host'] = $gcal_data['_bkap_zoom_meeting_host'];
				} else {
					$updated_settings['zoom_meeting_host'] = '';
				}

				// Zoom Meeting Additional Settings.

				if ( isset( $gcal_data['_bkap_zoom_meeting_auth'] ) /* && 'on' !== $gcal_data['_bkap_zoom_meeting_auth'] */ ) {
					$updated_settings['zoom_meeting_auth'] = $gcal_data['_bkap_zoom_meeting_auth'];
				} else {
					$updated_settings['zoom_meeting_auth'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_join_before_host'] ) /* && '' !== $gcal_data['_bkap_zoom_meeting_join_before_host'] */ ) {
					$updated_settings['zoom_meeting_join_before_host'] = $gcal_data['_bkap_zoom_meeting_join_before_host'];
				} else {
					$updated_settings['zoom_meeting_join_before_host'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_host_video'] ) /* && '' !== $gcal_data['_bkap_zoom_meeting_host_video'] */ ) {
					$updated_settings['zoom_meeting_host_video'] = $gcal_data['_bkap_zoom_meeting_host_video'];
				} else {
					$updated_settings['zoom_meeting_host_video'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_participant_video'] ) /* && '' !== $gcal_data['_bkap_zoom_meeting_participant_video'] */ ) {
					$updated_settings['zoom_meeting_participant_video'] = $gcal_data['_bkap_zoom_meeting_participant_video'];
				} else {
					$updated_settings['zoom_meeting_participant_video'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_mute_upon_entry'] ) /* && '' !== $gcal_data['_bkap_zoom_meeting_mute_upon_entry'] */ ) {
					$updated_settings['zoom_meeting_mute_upon_entry'] = $gcal_data['_bkap_zoom_meeting_mute_upon_entry'];
				} else {
					$updated_settings['zoom_meeting_mute_upon_entry'] = '';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_auto_recording'] ) && '' !== $gcal_data['_bkap_zoom_meeting_auto_recording'] ) {
					$updated_settings['zoom_meeting_auto_recording'] = $gcal_data['_bkap_zoom_meeting_auto_recording'];
				} else {
					$updated_settings['zoom_meeting_auto_recording'] = 'none';
				}

				if ( isset( $gcal_data['_bkap_zoom_meeting_alternative_host'] ) && '' !== $gcal_data['_bkap_zoom_meeting_alternative_host'] ) {
					$updated_settings['zoom_meeting_alternative_host'] = $gcal_data['_bkap_zoom_meeting_alternative_host'];
				} else {
					$updated_settings['zoom_meeting_alternative_host'] = array();
				}

				// FluentCRM Settings.
				if ( isset( $gcal_data['_bkap_fluentcrm'] ) ) {
					$updated_settings['bkap_fluentcrm'] = $gcal_data['_bkap_fluentcrm'];
				} else {
					$updated_settings['bkap_fluentcrm'] = '';
				}

				if ( isset( $gcal_data['_bkap_fluentcrm_list'] ) ) {
					$updated_settings['bkap_fluentcrm_list'] = $gcal_data['_bkap_fluentcrm_list'];
				} else {
					$updated_settings['bkap_fluentcrm_list'] = '';
				}

				$updated_settings = apply_filters( 'bkap_update_serialized_post_meta_integration_data', $updated_settings, $gcal_data );
			}

			if ( isset( $rental_data ) && is_array( $rental_data ) && count( $rental_data ) > 0 ) {

				$updated_settings['booking_prior_days_to_book'] = isset( $rental_data['_bkap_booking_prior_days_to_book'] ) ? $rental_data['_bkap_booking_prior_days_to_book'] : 0;
				$updated_settings['booking_later_days_to_book'] = isset( $rental_data['_bkap_booking_later_days_to_book'] ) ? $rental_data['_bkap_booking_later_days_to_book'] : 0;
				$updated_settings['booking_charge_per_day']     = isset( $rental_data['_bkap_booking_charge_per_day'] ) ? $rental_data['_bkap_booking_charge_per_day'] : '';
				$updated_settings['booking_same_day']           = isset( $rental_data['_bkap_booking_same_day'] ) ? $rental_data['_bkap_booking_same_day'] : '';

				$updated_settings = apply_filters( 'bkap_update_serialized_post_meta_rental_data', $updated_settings, $rental_data );
			}

			if ( isset( $resource_data ) && is_array( $resource_data ) && count( $resource_data ) > 0 ) {
				if ( isset( $resource_data['_bkap_resource'] ) ) {
					$updated_settings['_bkap_resource'] = $resource_data['_bkap_resource'];
				} else {
					$updated_settings['_bkap_resource'] = '';
				}

				if ( isset( $resource_data['_bkap_product_resource_lable'] ) ) {
					$updated_settings['_bkap_product_resource_lable'] = $resource_data['_bkap_product_resource_lable'];
				} else {
					$updated_settings['_bkap_product_resource_lable'] = '';
				}

				if ( isset( $resource_data['_bkap_product_resource_selection'] ) ) {
					$updated_settings['_bkap_product_resource_selection'] = $resource_data['_bkap_product_resource_selection'];
				} else {
					$updated_settings['_bkap_product_resource_selection'] = '';
				}

				$updated_settings['_bkap_product_resource_selection_type'] = isset( $resource_data['_bkap_product_resource_selection_type'] ) ? $resource_data['_bkap_product_resource_selection_type'] : '';

				if ( isset( $resource_data['_bkap_product_resource_max_booking'] ) ) {
					$updated_settings['_bkap_product_resource_max_booking'] = $resource_data['_bkap_product_resource_max_booking'];
				} else {
					$updated_settings['_bkap_product_resource_max_booking'] = '';
				}

				if ( isset( $resource_data['_bkap_product_resource_sorting'] ) ) {
					$updated_settings['_bkap_product_resource_sorting'] = $resource_data['_bkap_product_resource_sorting'];
				} else {
					$updated_settings['_bkap_product_resource_sorting'] = '';
				}

				if ( isset( $resource_data['_bkap_resource_base_costs'] ) ) {
					$updated_settings['_bkap_resource_base_costs'] = $resource_data['_bkap_resource_base_costs'];
				} else {
					$updated_settings['_bkap_resource_base_costs'] = '';
				}

				if ( isset( $resource_data['_bkap_product_resources'] ) ) {
					$updated_settings['_bkap_product_resources'] = $resource_data['_bkap_product_resources'];
				} else {
					$updated_settings['_bkap_product_resources'] = '';
				}
			}

			if ( isset( $person_data ) && is_array( $person_data ) && count( $person_data ) > 0 ) {
				foreach ( $person_data as $key => $value ) {
					$updated_settings[ substr( $key, 1 ) ] = $value;
				}

				if ( isset( $updated_settings['bkap_person_data'] ) && count( $updated_settings['bkap_person_data'] ) > 0 ) {

					foreach ( $updated_settings['bkap_person_data'] as $key => $value ) {
						// Update post 37
						$person_post = array(
							'ID'         => $key,
							'post_title' => $value['person_name'],
						);

						// Update the post into the database
						wp_update_post( $person_post );
					}
				}
			}

			// Fetch the existing settings.
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			// Merge the existing settings with the updated ones.
			$final_settings = ( is_array( $booking_settings ) && count( $booking_settings ) > 0 ) ? array_merge( $booking_settings, $updated_settings ) : $updated_settings;
			// update post meta.
			update_post_meta( $product_id, 'woocommerce_booking_settings', $final_settings );

			if ( isset( $_POST['bkap_defaults'] ) && 'on' == $_POST['bkap_defaults'] ) {
				update_option( 'bkap_default_booking_settings', $final_settings );
			}
		}

		/**
		 * Updates the Booking History table for Only Days
		 * Booking Type
		 *
		 * @param int   $product_id - Product ID
		 * @param array $settings_data - Data for Availability tab
		 * @global object $wpdb Global wpdb Object
		 *
		 * @since 4.0.0
		 */

		function update_bkap_history_only_days( $product_id, $settings_data ) {

			if ( count( $settings_data ) > 0 ) {

				global $wpdb;

				$recurring_array   = $settings_data['_bkap_recurring_weekdays'];
				$recurring_lockout = $settings_data['_bkap_recurring_lockout'];
				$specific_array    = isset( $settings_data['_bkap_specific_dates'] ) ? $settings_data['_bkap_specific_dates'] : array();

				// recurring days and lockout update
				if ( count( $recurring_array ) > 0 && count( $recurring_lockout ) > 0 ) {

					foreach ( $recurring_array as $weekday => $w_status ) {

						if ( 'on' == $w_status ) { // weekday is enabled

							$insert            = true;
							$available_booking = $recurring_lockout[ $weekday ];
							$updated_lockout   = $recurring_lockout[ $weekday ];

							// check if the weekday is already present
							$check_weekday_query = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                                WHERE post_id = %d
                                                AND weekday = %s
                                                AND start_date = '0000-00-00'
                                                AND status = ''";
							$check_weekday       = $wpdb->get_results( $wpdb->prepare( $check_weekday_query, $product_id, $weekday ) );

							// if yes, then update the lockout
							if ( isset( $check_weekday ) && count( $check_weekday ) > 0 ) { // there will be only 1 active record at any given time
								$insert = false;
								if ( is_numeric( $recurring_lockout[ $weekday ] ) && $recurring_lockout[ $weekday ] > 0 ) {
									$change_in_lockout = $recurring_lockout[ $weekday ] - $check_weekday[0]->total_booking;
								} elseif ( $recurring_lockout[ $weekday ] === '' || $recurring_lockout[ $weekday ] == 0 ) { // unlimited bookings
									$change_in_lockout = 0;
								}
							} else {
								// if not found, check if there's a date record present
								$existing_lockout = 'SELECT total_booking FROM `' . $wpdb->prefix . "booking_history`
                                                    WHERE post_id = %d
                                                    AND start_date != '0000-00-00'
                                                    AND weekday = %s
                                                    ORDER BY id DESC LIMIT 1";
								$lockout_results  = $wpdb->get_results( $wpdb->prepare( $existing_lockout, $product_id, $weekday ) );

								if ( isset( $lockout_results ) && count( $lockout_results ) > 0 ) {
									if ( is_numeric( $recurring_lockout[ $weekday ] ) && $recurring_lockout[ $weekday ] > 0 ) {
										$change_in_lockout = $recurring_lockout[ $weekday ] - $lockout_results[0]->total_booking;
										$available_booking = $lockout_results[0]->total_booking + $change_in_lockout;
									} elseif ( $recurring_lockout[ $weekday ] === '' || $recurring_lockout[ $weekday ] == 0 ) {
										$change_in_lockout = 0;
										$available_booking = 0;
									}
								}
							}

							if ( $insert ) {
								$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                                                (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                                                VALUES (
                                                '" . $product_id . "',
                                                '" . $weekday . "',
                                                '0000-00-00',
                                                '0000-00-00',
                                                '',
                                                '',
                                                '" . $updated_lockout . "',
                                                '" . $available_booking . "' )";
								$wpdb->query( $query_insert );
							} elseif ( isset( $change_in_lockout ) && is_numeric( $change_in_lockout ) ) {

								// Update the existing record so that lockout is managed and orders do not go missing frm the View bookings page
								if ( $change_in_lockout == 0 && ( $recurring_lockout[ $weekday ] === '' || $recurring_lockout[ $weekday ] == 0 ) ) { // unlimited bookings

									$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $updated_lockout . "',
                                                    available_booking = '" . $change_in_lockout . "'
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $weekday . "'
                                                    AND start_date = '0000-00-00'
                                                    AND status = ''";
								} else {
									$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $updated_lockout . "',
                                                    available_booking = available_booking + '" . $change_in_lockout . "'
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $weekday . "'
                                                    AND start_date = '0000-00-00'
                                                    AND status = ''";
								}
								$wpdb->query( $query_update );
							}

							if ( isset( $change_in_lockout ) && is_numeric( $change_in_lockout ) ) {

								// Update the existing records for the dates
								if ( $change_in_lockout == 0 && ( $recurring_lockout[ $weekday ] === '' || $recurring_lockout[ $weekday ] == 0 ) ) { // unlimited bookings

									$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $updated_lockout . "',
                                                    available_booking = '" . $change_in_lockout . "',
                                                    status = ''
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $weekday . "'
                                                    AND start_date <> '0000-00-00'";

								} else {
									$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $updated_lockout . "',
                                                    available_booking = available_booking + '" . $change_in_lockout . "',
                                                    status = ''
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $weekday . "'
                                                    AND start_date <> '0000-00-00'";
								}

								$wpdb->query( $query_update );
							}
						} else { // weekday is disabled

							// if a record exists in the table, it needs to be deactivated
							$update_query = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                        SET status = 'inactive'
                                        WHERE post_id = %d
                                        AND weekday = %s";
							$wpdb->query( $wpdb->prepare( $update_query, $product_id, $weekday ) );

							// Delete the base records for the recurring weekdays
							$delete_base_query = 'DELETE FROM `' . $wpdb->prefix . "booking_history`
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $weekday . "'
                                                    AND start_date = '0000-00-00'";

							$wpdb->query( $delete_base_query );
						}
					}
				}

				if ( is_array( $specific_array ) && count( $specific_array ) > 0 ) {

					foreach ( $specific_array as $specific_date => $specific_lockout ) {

						$specific_date     = date( 'Y-m-d', strtotime( $specific_date ) );
						$insert            = true;
						$available_booking = $specific_lockout;
						$updated_lockout   = $specific_lockout;

						$check_date_query1 = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                            WHERE post_id = %d
                                            AND weekday != ''
                                            AND start_date = %s
                                            AND status = ''";
						$check_date1       = $wpdb->get_results( $wpdb->prepare( $check_date_query1, $product_id, $specific_date ) );

						if ( count( $check_date1 ) > 0 ) {

							$query_update1 = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                            SET weekday = '',
                                            status = ''
                                            WHERE post_id = '" . $product_id . "'
                                            AND start_date = '" . $specific_date . "'";

							$wpdb->query( $query_update1 );
						}

						// check if the date is already present
						$check_date_query = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                            WHERE post_id = %d
                                            AND weekday = ''
                                            AND start_date = %s
                                            AND status = ''";

						$check_date = $wpdb->get_results( $wpdb->prepare( $check_date_query, $product_id, $specific_date ) );

						// if yes, then update the lockout
						if ( isset( $check_date ) && count( $check_date ) > 0 ) { // there will be only 1 active record at any given time
							$insert = false;
							if ( is_numeric( $specific_lockout ) && $specific_lockout > 0 ) {
								$change_in_lockout = $specific_lockout - $check_date[0]->total_booking;
							} elseif ( $specific_lockout === '' || $specific_lockout == 0 ) { // unlimited bookings
								$change_in_lockout = 0;
							}
						} else {
							// if not found, check if there's an inactive date record present
							$existing_lockout = 'SELECT total_booking FROM `' . $wpdb->prefix . "booking_history`
                                                WHERE post_id = %d
                                                AND start_date = %s
                                                AND weekday = ''
                                                AND status <> ''";
							$lockout_results  = $wpdb->get_results( $wpdb->prepare( $existing_lockout, $product_id, $specific_date ) );

							if ( isset( $lockout_results ) && count( $lockout_results ) > 0 ) {
								$insert = false;
								if ( is_numeric( $specific_lockout ) && $specific_lockout > 0 ) {
									$change_in_lockout = $specific_lockout - $lockout_results[0]->total_booking;
								} elseif ( $specific_lockout === '' || $specific_lockout == 0 ) { // unlimited bookings
									$change_in_lockout = 0;
								}
							}
						}

						if ( $insert ) {
							$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                                            (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                                            VALUES (
                                            '" . $product_id . "',
                                            '',
                                            '" . $specific_date . "',
                                            '0000-00-00',
                                            '',
                                            '',
                                            '" . $specific_lockout . "',
                                            '" . $available_booking . "' )";
							$wpdb->query( $query_insert );
						} elseif ( isset( $change_in_lockout ) && is_numeric( $change_in_lockout ) ) {

							// Update the existing record so that lockout is managed and orders do not go missing frm the View bookings page
							if ( $change_in_lockout == 0 && ( $specific_lockout === '' || $specific_lockout == 0 ) ) { // unlimited bookings

								$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                SET total_booking = '" . $specific_lockout . "',
                                                available_booking = '" . $change_in_lockout . "',
                                                status = ''
                                                WHERE post_id = '" . $product_id . "'
												AND weekday = ''
                                                AND start_date = '" . $specific_date . "'";

							} else {
								$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                SET total_booking = '" . $specific_lockout . "',
                                                available_booking = available_booking + '" . $change_in_lockout . "',
                                                status = ''
                                                WHERE post_id = '" . $product_id . "'
												AND weekday = ''
                                                AND start_date = '" . $specific_date . "'";
							}
							$wpdb->query( $query_update );
						}
					}
				}
			}
		}

		/**
		 * Updates the Booking History table for Date & Time
		 * Booking Type
		 *
		 * @param int   $product_id - Product ID
		 * @param array $settings_data - Data for Availability tab
		 * @global object $wpdb Global wpdb Object
		 *
		 * @since 4.0.0
		 */

		function update_bkap_history_date_time( $product_id, $settings_data ) {

			if ( count( $settings_data ) > 0 ) {

				global $wpdb;

				$booking_time_settings = isset( $settings_data['_bkap_time_settings'] ) ? $settings_data['_bkap_time_settings'] : array();

				// recurring days and lockout update
				if ( is_array( $booking_time_settings ) && count( $booking_time_settings ) > 0 ) {

					foreach ( $booking_time_settings as $day => $s_data ) {

						if ( 'booking' == substr( $day, 0, 7 ) ) { // recurring weekdays

							foreach ( $s_data as $time_data ) {

								$insert            = true;
								$available_booking = $time_data['lockout_slot'];
								$updated_lockout   = $time_data['lockout_slot'];

								$from_time = $time_data['from_slot_hrs'] . ':' . $time_data['from_slot_min'];
								$to_time   = $time_data['to_slot_hrs'] . ':' . $time_data['to_slot_min'];

								if ( $to_time == '0:00' ) {
									$to_time = '';
								}

								$from_db = date( 'H:i', strtotime( $from_time ) );
								$to_db   = date( 'H:i', strtotime( $to_time ) );

								// check if the weekday is already present
								// Duplicate records were being inserted when openended timeslot becasue DATE_TIME of blank returns no records.
								// Hence in below if, we are not comparing with DATE_TIME function.

								if ( $to_time == '' ) {
									$check_weekday_query = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                                            WHERE post_id = %d
                                                            AND weekday = %s
                                                            AND start_date = '0000-00-00'
                                                            AND TIME_FORMAT( from_time, '%H:%i' ) = %s
                                                            AND to_time = %s
                                                            AND status = ''";
									$check_weekday       = $wpdb->get_results( $wpdb->prepare( $check_weekday_query, $product_id, $day, $from_db, $to_time ) );

								} else {
									$check_weekday_query = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                                            WHERE post_id = %d
                                                            AND weekday = %s
                                                            AND start_date = '0000-00-00'
                                                            AND TIME_FORMAT( from_time, '%H:%i' ) = %s
                                                            AND TIME_FORMAT( to_time, '%H:%i' ) = %s
                                                            AND status = ''";
									$check_weekday       = $wpdb->get_results( $wpdb->prepare( $check_weekday_query, $product_id, $day, $from_db, $to_db ) );
								}

								// if yes, then update the lockout
								if ( isset( $check_weekday ) && count( $check_weekday ) > 0 ) { // there will be only 1 active record at any given time
									$insert = false;
									if ( is_numeric( $time_data['lockout_slot'] ) && $time_data['lockout_slot'] > 0 ) {
										$change_in_lockout = $time_data['lockout_slot'] - $check_weekday[0]->total_booking;
									} elseif ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) { // unlimited bookings
										$change_in_lockout = 0;
									}
								} else {
									// if not found, check if there's a date record present
									$existing_lockout = 'SELECT total_booking FROM `' . $wpdb->prefix . "booking_history`
                                                        WHERE post_id = %d
                                                        AND start_date != '0000-00-00'
                                                        AND weekday = %s
                                                        AND TIME_FORMAT( from_time, '%H:%i' ) = %s
                                                        AND TIME_FORMAT( to_time, '%H:%i' ) = %s
                                                        ORDER BY id DESC LIMIT 1";
									$lockout_results  = $wpdb->get_results( $wpdb->prepare( $existing_lockout, $product_id, $day, $from_db, $to_db ) );

									if ( isset( $lockout_results ) && count( $lockout_results ) > 0 ) {

										if ( is_numeric( $time_data['lockout_slot'] ) && $time_data['lockout_slot'] > 0 ) {
											$change_in_lockout = $time_data['lockout_slot'] - $lockout_results[0]->total_booking;
											$available_booking = $lockout_results[0]->total_booking + $change_in_lockout;
										} elseif ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) { // unlimited bookings
											$change_in_lockout = 0;
											$available_booking = 0;
										}
									}
								}

								if ( $insert ) {

									$current_date = date( 'Y-m-d', current_time( 'timestamp' ) );

									$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                                                    (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                                                    VALUES (
                                                    '" . $product_id . "',
                                                    '" . $day . "',
                                                    '0000-00-00',
                                                    '0000-00-00',
                                                    '" . $from_time . "',
                                                    '" . $to_time . "',
                                                    '" . $updated_lockout . "',
                                                    '" . $available_booking . "' )";
									$wpdb->query( $query_insert );

									// if there are other time slots present for the weekday, add this slot for the date
									$fetch_dates = 'SELECT DISTINCT( start_date ) FROM `' . $wpdb->prefix . 'booking_history`
                                                    WHERE start_date >= %s
                                                    AND post_id = %d
                                                    AND weekday = %s';
									$dates_set   = $wpdb->get_col( $wpdb->prepare( $fetch_dates, $current_date, $product_id, $day ) );

									if ( is_array( $dates_set ) && count( $dates_set ) > 0 ) {

										// build an array of dates that already have this slot present
										$fetch_dates_present = 'SELECT DISTINCT( start_date ) FROM `' . $wpdb->prefix . "booking_history`
                                                WHERE start_date >= %s
                                                AND post_id = %d
                                                AND weekday = %s
                                                AND TIME_FORMAT( from_time, '%H:%i' ) = %s
                                                AND TIME_FORMAT( to_time, '%H:%i' ) = %s";

										$dates_present = $wpdb->get_col( $wpdb->prepare( $fetch_dates_present, $current_date, $product_id, $day, $from_db, $to_db ) );

										foreach ( $dates_set as $date ) {
											// In a scenario where a future date is locked out, as all the time slot bookings are full,
											// we need to run this insert to ensure the date is unblocked and bookings can be taken for the new slot
											if ( ! in_array( $date, $dates_present ) ) {

												$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                                                    (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                                                    VALUES (
                                                    '" . $product_id . "',
                                                    '" . $day . "',
                                                    '" . $date . "',
                                                    '0000-00-00',
                                                    '" . $from_time . "',
                                                    '" . $to_time . "',
                                                    '" . $updated_lockout . "',
                                                    '" . $available_booking . "' )";

												$wpdb->query( $query_insert );
											}
										}
									}
								} elseif ( isset( $change_in_lockout ) && is_numeric( $change_in_lockout ) ) {

									// Update the existing record so that lockout is managed and orders do not go missing frm the View bookings page
									if ( $change_in_lockout == 0 && ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) ) { // unlimited bookings

										$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                        SET total_booking = '" . $updated_lockout . "',
                                                        available_booking = '" . $change_in_lockout . "'
                                                        WHERE post_id = '" . $product_id . "'
                                                        AND weekday = '" . $day . "'
                                                        AND start_date = '0000-00-00'
                                                        AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
                                                        AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'
                                                        AND status = ''";

									} else {
										if ( $to_time == '' ) {

											$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                                SET total_booking = '" . $updated_lockout . "',
                                                                available_booking = available_booking + '" . $change_in_lockout . "'
                                                                WHERE post_id = '" . $product_id . "'
                                                                AND weekday = '" . $day . "'
                                                                AND start_date = '0000-00-00'
                                                                AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
                                                                AND to_time = ''
                                                                AND status = ''";

										} else {
											$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                            SET total_booking = '" . $updated_lockout . "',
                                                            available_booking = available_booking + '" . $change_in_lockout . "'
                                                            WHERE post_id = '" . $product_id . "'
                                                            AND weekday = '" . $day . "'
                                                            AND start_date = '0000-00-00'
                                                            AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
                                                            AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'
                                                            AND status = ''";
										}
									}
									$wpdb->query( $query_update );
								}

								if ( isset( $change_in_lockout ) && is_numeric( $change_in_lockout ) ) {

									// Update the existing records for the dates
									if ( $change_in_lockout == 0 && ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) ) { // unlimited bookings

										$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $updated_lockout . "',
                                                    available_booking = '" . $change_in_lockout . "',
                                                    status = ''
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND weekday = '" . $day . "'
                                                    AND start_date <> '0000-00-00'
                                                    AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
													AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'
													AND status = ''";
									} else {

										if ( $to_time == '' ) {
											$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                        SET total_booking = '" . $updated_lockout . "',
                                                        available_booking = available_booking + '" . $change_in_lockout . "',
                                                        status = ''
                                                        WHERE post_id = '" . $product_id . "'
                                                        AND weekday = '" . $day . "'
                                                        AND start_date <> '0000-00-00'
                                                        AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
														AND to_time = ''
														AND status = ''";

										} else {
											$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                        SET total_booking = '" . $updated_lockout . "',
                                                        available_booking = available_booking + '" . $change_in_lockout . "',
                                                        status = ''
                                                        WHERE post_id = '" . $product_id . "'
                                                        AND weekday = '" . $day . "'
                                                        AND start_date <> '0000-00-00'
                                                        AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
														AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'
														AND status = ''";
										}
									}
									$wpdb->query( $query_update );
								}
							}
						} else { // specific dates

							$date = date( 'Y-m-d', strtotime( $day ) );
							foreach ( $s_data as $time_data ) {

								$insert            = true;
								$available_booking = $time_data['lockout_slot'];
								$updated_lockout   = $time_data['lockout_slot'];

								$from_time = $time_data['from_slot_hrs'] . ':' . $time_data['from_slot_min'];
								$to_time   = $time_data['to_slot_hrs'] . ':' . $time_data['to_slot_min'];

								if ( $to_time == '0:00' ) {
									$to_time = '';
								}

								// check if the date is already present
								$check_date_query = 'SELECT total_booking, available_booking FROM `' . $wpdb->prefix . "booking_history`
                                                    WHERE post_id = %d
                                                    AND weekday = ''
                                                    AND start_date = %s
                                                    AND from_time = %s
                                                    AND to_time = %s
                                                    AND status = ''";
								$check_date       = $wpdb->get_results( $wpdb->prepare( $check_date_query, $product_id, $date, $from_time, $to_time ) );

								// if yes, then update the lockout
								if ( isset( $check_date ) && count( $check_date ) > 0 ) { // there will be only 1 active record at any given time
									$insert = false;
									if ( is_numeric( $time_data['lockout_slot'] ) && $time_data['lockout_slot'] > 0 ) {
										$change_in_lockout = $time_data['lockout_slot'] - $check_date[0]->total_booking;
									} elseif ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) { // unlimited bookings
										$change_in_lockout = 0;
									}
								} else {
									// if not found, check if there's an inactive date record present
									$existing_lockout = 'SELECT total_booking FROM `' . $wpdb->prefix . "booking_history`
                                                        WHERE post_id = %d
                                                        AND start_date = %s
                                                        AND weekday = ''
                                                        AND from_time = %s
                                                        AND to_time = %s
                                                        AND status <> ''";
									$lockout_results  = $wpdb->get_results( $wpdb->prepare( $existing_lockout, $product_id, $date, $from_time, $to_time ) );

									if ( isset( $lockout_results ) && count( $lockout_results ) > 0 ) {
										$insert = false;
										if ( is_numeric( $time_data['lockout_slot'] ) && $time_data['lockout_slot'] > 0 ) {
											$change_in_lockout = $time_data['lockout_slot'] - $lockout_results[0]->total_booking;
										} elseif ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) { // unlimited bookings
											$change_in_lockout = 0;
										}
									}
								}

								if ( $insert ) {
									$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                                                (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                                                VALUES (
                                                '" . $product_id . "',
                                                '',
                                                '" . $date . "',
                                                '0000-00-00',
                                                '" . $from_time . "',
                                                '" . $to_time . "',
                                                '" . $time_data['lockout_slot'] . "',
                                                '" . $available_booking . "' )";
									$wpdb->query( $query_insert );
								} else {

									// Update the existing record so that lockout is managed and orders do not go missing frm the View bookings page
									if ( $change_in_lockout == 0 && ( $time_data['lockout_slot'] === '' || $time_data['lockout_slot'] == 0 ) ) { // unlimited bookings
										$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $time_data['lockout_slot'] . "',
                                                    available_booking = '" . $change_in_lockout . "',
                                                    status = ''
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND start_date = '" . $date . "'
                                                    AND from_time = '" . $from_time . "'
                                                    AND to_time = '" . $to_time . "'";
									} else {
										$query_update = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                                    SET total_booking = '" . $time_data['lockout_slot'] . "',
                                                    available_booking = available_booking + '" . $change_in_lockout . "',
                                                    status = ''
                                                    WHERE post_id = '" . $product_id . "'
                                                    AND start_date = '" . $date . "'
                                                    AND from_time = '" . $from_time . "'
                                                    AND to_time = '" . $to_time . "'";
									}
									$wpdb->query( $query_update );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Called when a record from the Set Availability by Dates/Months
		 * Table in Booking meta box->Availability tab needs to be deleted.
		 *
		 * Called via AJAX
		 *
		 * @since 4.0.0
		 */

		static function bkap_delete_specific_range() {

			$product_id  = $_POST['product_id'];
			$record_type = $_POST['record_type'];
			$start       = $_POST['start'];
			$end         = $_POST['end'];

			$booking_box_class = new bkap_booking_box_class();
			$booking_box_class->delete_ranges( $product_id, $record_type, $start, $end );

			die();
		}

		/**
		 * Deletes a record from the Set Availability by Dates/Months
		 * Table in Booking meta box->Availability tab.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $record_type - Such as 'range_of_months', 'custom_range' and so on.
		 * @param string $start - Start Date
		 * @param string $end - End Date
		 *
		 * @since 4.0.0
		 */

		function delete_ranges( $product_id, $record_type, $start, $end ) {

			if ( '' != $record_type ) {
				switch ( $record_type ) {

					case 'custom_range':
						$custom_ranges = get_post_meta( $product_id, '_bkap_custom_ranges', true );

						// get the key for the range
						$delete_key = $this->get_range_key( $custom_ranges, $start, $end );
						if ( is_numeric( $delete_key ) ) {
							$this->delete_serialized_range( $product_id, 'booking_date_range', $delete_key );
							$this->delete_single_range( $product_id, '_bkap_custom_ranges', $delete_key );
						}
						break;
					case 'range_of_months':
						global $bkap_months;

						$current_year = date( 'Y', current_time( 'timestamp' ) );
						$next_year    = date( 'Y', strtotime( '+1 year' ) );

						$month_range = get_post_meta( $product_id, '_bkap_month_ranges', true );

						if ( is_numeric( $start ) ) { // it's a month number
							$month_name   = $bkap_months[ $start ];
							$month_to_use = "$month_name $current_year";
							$range_start  = date( 'j-n-Y', strtotime( $month_to_use ) );
						}

						if ( is_numeric( $end ) ) { // it's a month number
							$month_name = $bkap_months[ $end ];
							if ( $start < $end ) {
								$month_to_use = "$month_name $current_year";
							} else {
								$month_to_use = "$month_name $next_year";
							}
							$month_start = date( 'j-n-Y', strtotime( $month_to_use ) );

							$days      = date( 't', strtotime( $month_start ) );
							$days     -= 1;
							$range_end = date( 'j-n-Y', strtotime( "+$days days", strtotime( $month_start ) ) );
						}

						// get the key for the range
						$delete_key = $this->get_range_key( $month_range, $range_start, $range_end );

						if ( is_numeric( $delete_key ) ) {
							$this->delete_single_range( $product_id, '_bkap_month_ranges', $delete_key );
						}
						break;
					case 'specific_dates':
						// remove the record from serial data
						$this->delete_serialized_range( $product_id, 'booking_specific_date', $start );
						// remove from individual data
						$this->delete_single_range( $product_id, '_bkap_specific_dates', $start );
						// update booking history
						$this->delete_specific_date( $product_id, $start );
						// update the special prices data
						$this->delete_special_price( $product_id, $start );
						break;
					case 'holidays':
						// remove the record from serial data
						$this->delete_serialized_range( $product_id, 'booking_product_holiday', $start );
						// remove from individual data
						$this->delete_single_range( $product_id, '_bkap_product_holidays', $start );
						break;
					case 'holiday_range':
						$holiday_range = get_post_meta( $product_id, '_bkap_holiday_ranges', true );

						// get the key for the range
						$delete_key = $this->get_range_key( $holiday_range, $start, $end );

						if ( is_numeric( $delete_key ) ) {
							$this->delete_single_range( $product_id, '_bkap_holiday_ranges', $delete_key );
						}
					default:
						break;
				}
			}

		}

		/**
		 * Returns the array key from a given range if a match
		 * is found.
		 *
		 * @param array  $range - array to search
		 * @param string $start - start date (j-n-Y)
		 * @param string $end - end date (j-n-Y)
		 * @return int $key - array key
		 *
		 * @since 4.0.0
		 */

		function get_range_key( $range, $start, $end ) {

			$delete_key = '';
			if ( is_array( $range ) && count( $range ) > 0 ) {
				foreach ( $range as $range_key => $range_value ) {
					$r_start = $range_value['start'];
					$r_end   = $range_value['end'];

					if ( $r_start == $start && $r_end == $end ) {
						$delete_key = $range_key;
						break;
					}
				}
			}

			return $delete_key;

		}

		/**
		 * Deletes a given array record from the
		 * individual booking settings in postmeta.
		 *
		 * @param integer $product_id - Product ID
		 * @param string  $range_name - Meta Key from which the data needs to be removed.
		 * @param integer $key - Array key to be removed.
		 *
		 * @since 4.0.0
		 */

		function delete_single_range( $product_id, $range_name, $key ) {

			$range_data = get_post_meta( $product_id, $range_name, true );

			if ( array_key_exists( $key, $range_data ) ) {
				unset( $range_data[ $key ] );
			}

			update_post_meta( $product_id, $range_name, $range_data );
		}

		/**
		 * Deletes a record from a given range in
		 * the serialized booking settings i.e. woocommerce_booking_settings
		 * in postmeta table.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $name - contains the array key name
		 * @param int    $key - Array key to be unset
		 *
		 * @since 4.0.0
		 */

		function delete_serialized_range( $product_id, $name, $key ) {

			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			$record_data = $booking_settings[ $name ];

			if ( array_key_exists( $key, $record_data ) ) {
				unset( $record_data[ $key ] );
			}

			$booking_settings[ $name ] = $record_data;

			update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
		}

		/**
		 * Updates a specific date record to inactive
		 * status in booking history table for a given
		 * date.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $date - j-n-Y Format
		 * @global object $wpdb Global wpdb Object
		 *
		 * @since 4.0.0
		 */

		function delete_specific_date( $product_id, $date ) {

			global $wpdb;

			$specific_date = date( 'Y-m-d', strtotime( $date ) );

			$update_specific = 'UPDATE `' . $wpdb->prefix . "booking_history`
                            SET status = 'inactive'
                            WHERE post_id = '" . $product_id . "'
                            AND start_date = '" . $specific_date . "'
                            AND weekday = ''
                            AND from_time = ''
                            AND to_time = ''";

			$wpdb->query( $update_specific );
		}

		/**
		 * Deletes the special price record
		 * from post meta for a given specific
		 * date.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $date - Date for which the data needs to be removed.
		 * @since 4.0.0
		 */

		function delete_special_price( $product_id, $date ) {

			$date = date( 'Y-m-d', strtotime( $date ) );

			$special_prices = get_post_meta( $product_id, '_bkap_special_price', true );

			if ( is_array( $special_prices ) && count( $special_prices ) > 0 ) {

				$updated_special_prices = array();
				foreach ( $special_prices as $s_key => $s_price ) {

					if ( $s_price['booking_special_date'] != $date ) {
						$updated_special_prices[ $s_key ] = $s_price;
					}
				}

				update_post_meta( $product_id, '_bkap_special_price', $updated_special_prices );
			}
		}

		/**
		 * Update timeslot settings.
		 *
		 * @param array $existing_settings The current time slot settings stored.
		 * @param array $_time_slot An array of product id, weekdays and pre/cur setting.
		 * @since 5.2.1
		 * @return array The updated time slot settings.
		 */
		public function bkap_update_time_slots_settings( $existing_settings, $_time_slot ) {

			// Time slot to be updated in the existing settings.
			$timeslot_weekday = $_time_slot['booking_weekday'];
			if ( is_array( $existing_settings ) && count( $existing_settings ) > 0 ) {

				foreach ( $existing_settings as $booking_weekday => $time_slot_settings ) {

					if ( $booking_weekday !== $_time_slot['booking_weekday'] ) {
						continue;
					} else {

						$from_time_segments = explode( ':', $_time_slot['prev_time_slot']['from_time'] );
						$from_hrs           = $from_time_segments[0];
						$from_mins          = $from_time_segments[1];

						$to_time_segments = explode( ':', $_time_slot['prev_time_slot']['to_time'] );
						$to_hrs           = $to_time_segments[0];
						$to_mins          = isset( $to_time_segments[1] ) ? $to_time_segments[1] : '00';

						foreach ( $time_slot_settings  as $key => $slot_val ) {
							if ( ( $from_hrs == $slot_val['from_slot_hrs'] ) &&
								( $from_mins == $slot_val['from_slot_min'] ) &&
								( $to_hrs == $slot_val['to_slot_hrs'] ) &&
								( $to_mins == $slot_val['to_slot_min'] ) &&
								( $_time_slot['prev_time_slot']['lockout_time'] == $slot_val['lockout_slot'] ) &&
								( $_time_slot['prev_time_slot']['global_time_check'] == $slot_val['global_time_check'] ) &&
								( $_time_slot['prev_time_slot']['product_price'] == $slot_val['slot_price'] )
							) {

								$cur_from_time_segments = explode( ':', $_time_slot['cur_time_slot']['from_time'] );
								$cur_from_hrs           = $cur_from_time_segments[0];
								$cur_from_mins          = isset( $cur_from_time_segments[1] ) ? $cur_from_time_segments[1] : '00';

								$cur_to_time_segments  = explode( ':', $_time_slot['cur_time_slot']['to_time'] );
								$cur_to_hrs            = $cur_to_time_segments[0];
								$cur_to_mins           = $cur_to_time_segments[1];
								$cur_lockout_slot      = $_time_slot['cur_time_slot']['lockout_time'];
								$cur_global_time_check = $_time_slot['cur_time_slot']['global_time_check'];
								$cur_slot_price        = $_time_slot['cur_time_slot']['product_price'];
								$cur_booking_notes     = $_time_slot['cur_time_slot']['additional_note'];

								$existing_settings[ $booking_weekday ][ $key ] = array(
									'from_slot_hrs'     => $cur_from_hrs,
									'from_slot_min'     => $cur_from_mins,
									'to_slot_hrs'       => $cur_to_hrs,
									'to_slot_min'       => $cur_to_mins,
									'booking_notes'     => $cur_booking_notes,
									'slot_price'        => $cur_slot_price,
									'lockout_slot'      => $cur_lockout_slot,
									'global_time_check' => $cur_global_time_check,
								);

							}
						}
					}
				}
			}
			return $existing_settings;
		}

		/**
		 * Update all booking meta settings.
		 *
		 * @param array $_time_slot An array of product id, weekdays and pre/cur setting.
		 * @since 5.2.1
		 * @return bool.
		 */
		public function bkap_update_all_meta_settings( $_time_slot ) {

			$product_id            = (int) $_time_slot['product_id'];
			$booking_settings      = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$existing_settings     = $booking_settings['booking_time_settings'];
			$updated_time_settings = $this->bkap_update_time_slots_settings( $existing_settings, $_time_slot );

			$booking_settings['booking_time_settings'] = $updated_time_settings;
			$status                                    = update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );

			// Return true, as update_post_meta returns true and false for successful update.
			if ( true === $status || false === $status ) {
				return true;
			}

		}

		/**
		 * Update all booking history timeslots records settings.
		 *
		 * @param array $_time_slot An array of product id and weekdays and pre/cur setting.
		 * @since 5.2.1
		 * @return bool.
		 */
		public function bkap_update_booking_history( $_time_slot ) {
			global $wpdb;
			$product_id        = (int) $_time_slot['product_id'];
			$booking_weekday   = $_time_slot['booking_weekday'];
			$prev_from_time    = $_time_slot['prev_time_slot']['from_time'];
			$prev_to_time      = $_time_slot['prev_time_slot']['to_time'];
			$prev_lockout_time = $_time_slot['prev_time_slot']['lockout_time'];
			$cur_from_time     = $_time_slot['cur_time_slot']['from_time'];
			$cur_to_time       = $_time_slot['cur_time_slot']['to_time'];
			$cur_lockout_time  = $_time_slot['cur_time_slot']['lockout_time'];

			$where = array(
				'post_id'   => $product_id,
				'weekday'   => $booking_weekday,
				'from_time' => $prev_from_time,
				'to_time'   => $prev_to_time,
			);

			if ( false !== strpos( $booking_weekday, '_' ) ) {
				$where['weekday'] = $booking_weekday;
			} else {
				$date_from_format    = DateTime::createFromFormat( 'j-n-Y', $booking_weekday );
				$where['start_date'] = $date_from_format->format( 'Y-m-d' );
				$where['weekday']    = '';
			}

			$data = array(
				'from_time'     => $cur_from_time,
				'to_time'       => $cur_to_time,
				'total_booking' => $cur_lockout_time,
			);

			$placeholder = array(
				'%s',
				'%s',
				'%s',
			);

			$status = $wpdb->update(
				$wpdb->prefix . 'booking_history',
				$data,
				$where,
				$placeholder,
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);

			if ( $prev_from_time === $cur_from_time && $cur_to_time === $prev_to_time ) {
				$where['start_date']       = '0000-00-00';
				$data['available_booking'] = $cur_lockout_time;
				$placeholder               = array(
					'%s',
					'%s',
					'%s',
					'%s',
				);

				$status_base = $wpdb->update(
					$wpdb->prefix . 'booking_history',
					$data,
					$where,
					$placeholder,
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			}

			if ( false !== $status ) {
				return true;
			} else {
				return false;
			}

		}

		/**
		 * Update booking products individual timeslot meta settings.
		 *
		 * @param array $_time_slot An array of product id and weekdays and pre/cur setting.
		 * @since 5.2.1
		 * @return bool.
		 */
		public function bkap_update_individual_time_settings( $_time_slot ) {
			$product_id            = (int) $_time_slot['product_id'];
			$existing_settings     = get_post_meta( $product_id, '_bkap_time_settings', true );
			$updated_time_settings = $this->bkap_update_time_slots_settings( $existing_settings, $_time_slot );
			$status                = update_post_meta( $product_id, '_bkap_time_settings', $updated_time_settings );

			// Return true, as update_post_meta returns true and false for successful update.
			if ( true === $status || false === $status ) {
				return true;
			}

		}


		/**
		 * Updates previous Date/Day and Time Slot setting
		 * values under availability tab.
		 * Called via ajax
		 *
		 * @since 5.2.1
		 */
		static function bkap_update_date_time_slot() {

			$response = array(
				'message' => __( 'Oops! something wrong happens, please contact admin.', 'woocommerce-booking' ),
				'status'  => 'false',
			);

			$product_id      = (int) $_POST['product_id'];
			$booking_weekday = sanitize_text_field( $_POST['booking_weekday']['0'] );

			$prev_time_slot = array(
				'from_time'         => sanitize_text_field( $_POST['from_time'] ),
				'to_time'           => sanitize_text_field( $_POST['to_time'] ),
				'lockout_time'      => (int) $_POST['max_lockout'],
				'global_time_check' => sanitize_text_field( $_POST['global_time_check'] ),
				'product_price'     => floatval( $_POST['product_price'] ),
				'additional_note'   => sanitize_text_field( $_POST['additional_note'] ),
			);

			$cur_time_slot = array(
				'from_time'         => sanitize_text_field( $_POST['cur_from_time'] ),
				'to_time'           => sanitize_text_field( $_POST['cur_to_time'] ),
				'lockout_time'      => (int) $_POST['cur_max_lockout'],
				'global_time_check' => isset( $_POST['cur_global_time_check'] ) ? sanitize_text_field( $_POST['cur_global_time_check'] ) : '',
				'product_price'     => floatval( $_POST['cur_product_price'] ),
				'additional_note'   => sanitize_text_field( $_POST['cur_additional_note'] ),
			);

			// Update the individual post meta key `_bkap_time_settings`
			$booking_box_class = new bkap_booking_box_class();
			$_time_slot        = array(
				'product_id'      => $product_id,
				'booking_weekday' => $booking_weekday,
				'prev_time_slot'  => $prev_time_slot,
				'cur_time_slot'   => $cur_time_slot,
			);

			// Update individual time settings stored under `_bkap_time_settings` meta key.
			$_status_1 = $booking_box_class->bkap_update_individual_time_settings( $_time_slot );

			// Update complete post meta key `woocommerce_booking_settings`
			$_status_2 = $booking_box_class->bkap_update_all_meta_settings( $_time_slot );

			// Update records in `{prefix}_booking_history` table.
			$_status_3 = $booking_box_class->bkap_update_booking_history( $_time_slot );

			// Some exceptional handling to be managed later as per dicsussion.
			if ( $_status_1 && $_status_2 && $_status_3 ) {
				$response['message'] = __( 'Timeslot updated successfully.', 'woocommerce-booking' );
				$response['status']  = 'true';
			}
			wp_send_json( $response );
			die();
		}



		/**
		 * Deletes the Date/Day and Time Slot from the
		 * Date & Time table in the Availability settings
		 *
		 * Called via ajax
		 *
		 * @since 4.0.0
		 */

		static function bkap_delete_date_time() {

			$product_id = isset( $_POST['product_id'] ) ? $_POST['product_id'] : '';
			$day        = isset( $_POST['day'] ) ? $_POST['day'] : ''; // this will be an array
			$from_time  = isset( $_POST['from_time'] ) ? $_POST['from_time'] : '';
			$to_time    = isset( $_POST['to_time'] ) ? $_POST['to_time'] : '';

			$booking_box_class = new bkap_booking_box_class();
			if ( is_array( $day ) && count( $day ) > 0 ) {
				foreach ( $day as $day_value ) {
					// update post meta serialized
					$booking_box_class->delete_serialized_time_settings( $product_id, $day_value, $from_time, $to_time );
					// update post meta individual
					$booking_box_class->delete_individual_time_settings( $product_id, $day_value, $from_time, $to_time );
					// update booking history
					$booking_box_class->delete_booking_history( $product_id, $day_value, $from_time, $to_time );
					do_action( 'bkap_delete_timeslot', $product_id, $day_value, $from_time, $to_time );
				}
			}
			die();
		}

		/**
		 * Deletes all Date/Day and Time Slots
		 *
		 * Called via ajax
		 *
		 * @since 4.19.2
		 */
		static function bkap_delete_all_date_time() {

			global $wpdb;

			$product_id                                = $_POST['product_id'];
			$booking_settings                          = bkap_setting( $product_id );
			$booking_settings['booking_time_settings'] = array();

			update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
			update_post_meta( $product_id, '_bkap_time_settings', array() );

			$update_date_query = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                SET status = 'inactive'
                                WHERE post_id = '" . $product_id . "'";
			$delete_result     = $wpdb->query( $update_date_query );
			die();
		}

		/**
		 * Deletes the time slot from the serialized
		 * post meta record i.e. woocommerce_booking_settings
		 * in postmeta table.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $day_value - Weekday/Date
		 * @param string $from_time - H:i
		 * @param string $to_time - H:i
		 *
		 * @since 4.0.0
		 */

		function delete_serialized_time_settings( $product_id, $day_value, $from_time, $to_time ) {

			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			$existing_settings = $booking_settings['booking_time_settings'];

			$updated_time_settings = $this->unset_time_array( $existing_settings, $day_value, $from_time, $to_time );

			$booking_settings['booking_time_settings'] = $updated_time_settings;

			update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
		}

		/**
		 * Deletes the time slot from the _bkap_time_settings
		 * post meta record in postmeta table.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $day_value - Weekday/Date
		 * @param string $from_time - H:i
		 * @param string $to_time - H:i
		 *
		 * @since 4.0.0
		 */

		function delete_individual_time_settings( $product_id, $day_value, $from_time, $to_time ) {

			$existing_settings = get_post_meta( $product_id, '_bkap_time_settings', true );

			$updated_time_settings = $this->unset_time_array( $existing_settings, $day_value, $from_time, $to_time );

			update_post_meta( $product_id, '_bkap_time_settings', $updated_time_settings );

		}

		/**
		 * Unsets the time slots record which needs to be removed from the list
		 * of time slots present for the product.
		 *
		 * @param array  $existing_settings - Existing Time Slots for the Product
		 * @param string $day_value - Weekday/Date
		 * @param string $from_time - H:i
		 * @param string $to_time - H:i
		 * @return array $existing_settings - updated array with the desired slot removed.
		 *
		 * @since 4.0.0
		 */

		function unset_time_array( $existing_settings, $day_value, $from_time, $to_time ) {

			// split the time into hrs and mins
			$from_time_array = explode( ':', $from_time );
			$from_hrs        = $from_time_array[0];
			$from_mins       = $from_time_array[1];

			$to_hrs  = '0';
			$to_mins = '00';
			if ( isset( $to_time ) && '' != $to_time ) {
				$to_time_array = explode( ':', $to_time );
				$to_hrs        = $to_time_array[0];
				$to_mins       = $to_time_array[1];
			}

			if ( is_array( $existing_settings ) && count( $existing_settings ) > 0 ) {

				foreach ( $existing_settings as $day => $day_settings ) {

					if ( $day == $day_value ) { // matching day/date

						foreach ( $day_settings as $time_key => $time_settings ) {

							// Match the time
							if ( trim( $from_hrs ) == $time_settings['from_slot_hrs'] &&
								trim( $from_mins ) == $time_settings['from_slot_min'] &&
								trim( $to_hrs ) == $time_settings['to_slot_hrs'] &&
								trim( $to_mins ) == $time_settings['to_slot_min']
							) {
								$unset_key = $time_key;
								break;
							}
						}
						// unset the array
						if ( isset( $unset_key ) && is_numeric( $unset_key ) ) {
							unset( $existing_settings[ $day ][ $unset_key ] );
							break;
						}
					}
				}
			}

			return $existing_settings;
		}

		/**
		 * Updates the Booking History table. Removes/Inactivates the
		 * desired records for the deleted time slot.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $day_value - Weekday/Date
		 * @param string $from_time - H:i
		 * @param string $to_time - H:i
		 * @global object $wpdb Global wpdb Object
		 *
		 * @since 4.0.0
		 */

		function delete_booking_history( $product_id, $day_value, $from_time = '', $to_time = '' ) {

			global $wpdb;

			$to_hrs  = '';
			$to_mins = '';

			if ( isset( $to_time ) && '' != $to_time ) {
				$to_time_array = explode( ':', $to_time );
				$to_hrs        = $to_time_array[0];
				$to_mins       = $to_time_array[1];
			}

			if ( $to_hrs == 0 && $to_mins == 0 ) {
				$to_time = '';
			}

			// set all date records to inactive
			$from_db = date( 'H:i', strtotime( $from_time ) );
			$to_db   = date( 'H:i', strtotime( $to_time ) );

			if ( isset( $day_value ) && substr( $day_value, 0, 7 ) == 'booking' ) { // recurring weekday

				// delete the base record
				$delete_base = 'DELETE FROM `' . $wpdb->prefix . "booking_history`
					WHERE post_id = '" . $product_id . "'
					AND weekday = '" . $day_value . "'
					AND start_date = '0000-00-00'
					AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
					AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'";
				$wpdb->query( $delete_base );

				if ( $to_time == '' ) {
					$update_date_status = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                    SET status = 'inactive'
                                    WHERE post_id = '" . $product_id . "'
                                    AND weekday = '" . $day_value . "'
                                    AND start_date <> '0000-00-00'
                                    AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
                                    AND to_time = '" . $to_time . "'";

				} else {
					$update_date_status = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                    SET status = 'inactive'
                                    WHERE post_id = '" . $product_id . "'
                                    AND weekday = '" . $day_value . "'
                                    AND start_date <> '0000-00-00'
                                    AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_db . "'
                                    AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_db . "'";
				}
				$wpdb->query( $update_date_status );

			} elseif ( isset( $day_value ) && '' != $day_value ) { // specific date

				$date = date( 'Y-m-d', strtotime( $day_value ) );

				// set the date record to inactive
				$update_date_query = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                SET status = 'inactive'
                                WHERE post_id = '" . $product_id . "'
                                AND start_date = '" . $date . "'
                                AND from_time = '" . $from_time . "'
                                AND to_time = '" . $to_time . "'";

				$delete_result = $wpdb->query( $update_date_query );

				if ( $delete_result == 0 ) {

					// set the date record to inactive
					$update_date_query = 'UPDATE `' . $wpdb->prefix . "booking_history`
                                  SET status = 'inactive'
                                  WHERE post_id = '" . $product_id . "'
                                  AND start_date = '" . $date . "'
                                  AND from_time = '" . $from_db . "'
                                  AND to_time = '" . $to_db . "'";

					$delete_result = $wpdb->query( $update_date_query );
				}
			}
		}

		/**
		 * This function duplicates the booking settings
		 * of the original product to the new product.
		 *
		 * @param int     $new_id Product ID
		 * @param WP_Post $post Product Post Object
		 *
		 * @globals mixed $wpdb Global wpdb object
		 *
		 * @since 4.0.0
		 */

		function bkap_product_duplicate( $new_id, $post ) {
			global $wpdb;

			$old_id          = $post->ID;
			$duplicate_query = 'SELECT * FROM `' . $wpdb->prefix . "booking_history` WHERE post_id = %d AND status = '' ";
			$results_date    = $wpdb->get_results( $wpdb->prepare( $duplicate_query, $old_id ) );

			foreach ( $results_date as $key => $value ) {
				$query_insert = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
                  (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
                  VALUES (
                  '" . $new_id . "',
                  '" . $value->weekday . "',
                  '" . $value->start_date . "',
                  '" . $value->end_date . "',
                  '" . $value->from_time . "',
                  '" . $value->to_time . "',
                  '" . $value->total_booking . "',
                  '" . $value->total_booking . "' )";
				  $wpdb->query( $query_insert );
			}
			do_action( 'bkap_product_addon_duplicate', $new_id, $old_id );
		}
	}//end class
	$bkap_booking_box_class = new bkap_booking_box_class();
}
?>
