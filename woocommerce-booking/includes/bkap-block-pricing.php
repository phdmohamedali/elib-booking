<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Fixed Blocks and Price Ranges
 *
 * @author   Tyche Softwares
 * @package  BKAP/Block-Pricing
 * @category Classes
 */

if ( ! class_exists( 'bkap_block_booking' ) ) {

	/**
	 * Class for handling Fixed Blocks and Price Ranges
	 *
	 * @class bkap_block_booking class
	 **/
	class bkap_block_booking {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */

		public function __construct() {
			$this->variable_block_price = 0;

			add_action( 'admin_init', array( &$this, 'bkap_load_ajax_block_pricing' ) );

			// Adding Block Pricing tab in Booking Meta Box.
			add_action( 'bkap_add_tabs', array( &$this, 'block_pricing_tab' ), 5, 1 );

			// used to add new settings on the product page booking box
			add_action( 'bkap_after_listing_enabled', array( &$this, 'bkap_block_pricing_show_field_settings' ), 5, 4 );

			// Fixed Block.
			// add_action( 'bkap_display_multiple_day_updated_price',  array( &$this, 'bkap_fixed_block_show_updated_price'), 6, 7 );
			// Price by Range.
			add_action( 'bkap_display_multiple_day_updated_price', array( &$this, 'bkap_price_range_show_updated_price' ), 5, 11 );

			// add_action( 'woocommerce_before_add_to_cart_form',      array( &$this, 'bkap_fixed_block_before_add_to_cart' ) );

			add_action( 'bkap_before_booking_form', array( &$this, 'bkap_fixed_block_booking_after_add_to_cart' ), 7, 1 );

			add_action( 'bkap_display_price_div', array( &$this, 'bkap_fixed_block_display_price' ), 10, 1 );

			// Copy the exisiting variable blocks to the new product when the product is duplicated
			add_action( 'bkap_product_addon_duplicate', array( &$this, 'price_range_product_duplicate' ), 10, 2 );

			add_action( 'bkap_before_booking_form', array( &$this, 'bkap_price_range_booking_after_add_to_cart' ), 10, 1 );

		}

		/**
		 * This function is used to load ajax functions required for
		 * Fixed Blocks & Price Ranges
		 *
		 * @since 4.1.0
		 */
		function bkap_load_ajax_block_pricing() {

			// ajax for deleting a single fixed block.
			add_action( 'wp_ajax_bkap_delete_block', array( &$this, 'bkap_delete_block' ) );

			// ajax for deleting all fixed blocks.
			add_action( 'wp_ajax_bkap_delete_all_blocks', array( &$this, 'bkap_delete_all_blocks' ) );

			// ajax for clearing the block pricing options.
			add_action( 'wp_ajax_bkap_block_pricing_options', array( &$this, 'bkap_block_pricing_options' ) );

			// ajax for deleting a single price range.
			add_action( 'wp_ajax_bkap_delete_range', array( &$this, 'bkap_delete_range' ) );

			// ajax for deleting all price ranges.
			add_action( 'wp_ajax_bkap_delete_all_ranges', array( &$this, 'bkap_delete_all_ranges' ) );
		}

		/**
		 * This function is used to delete existing Fixed blocks from the
		 * table in the Booking meta box
		 *
		 * @since 4.1.0
		 */

		function bkap_delete_block() {

			$product_id = $_POST['post_id'];
			$key        = $_POST['fixed_block_key'];

			$bkap_fixed_blocks_data = get_post_meta( $product_id, '_bkap_fixed_blocks_data', true );

			if ( $bkap_fixed_blocks_data != '' ) {

				if ( array_key_exists( $key, $bkap_fixed_blocks_data ) ) {
					unset( $bkap_fixed_blocks_data[ $key ] );
				}
			}
			update_post_meta( $product_id, '_bkap_fixed_blocks_data', $bkap_fixed_blocks_data );

			die();
		}

		/**
		 * This function is used to delete existing Price Ranges from the
		 * table in the Booking meta box.
		 *
		 * @since 4.1.0
		 */

		function bkap_delete_range() {

			$product_id = $_POST['post_id'];
			$key        = $_POST['price_range_key'];

			$bkap_price_range_data = get_post_meta( $product_id, '_bkap_price_range_data', true );

			if ( $bkap_price_range_data != '' ) {

				if ( array_key_exists( $key, $bkap_price_range_data ) ) {
					unset( $bkap_price_range_data[ $key ] );
				}
			}
			update_post_meta( $product_id, '_bkap_price_range_data', $bkap_price_range_data );

			die();
		}

		/**
		 * This function is used to delete all the Fixed Blocks present
		 * for a product.
		 *
		 * @since 4.1.0
		 */

		function bkap_delete_all_blocks() {

			$post_id    = $_POST['post_id'];
			$blank_data = '';
			update_post_meta( $post_id, '_bkap_fixed_blocks_data', $blank_data );

			die();
		}

		/**
		 * This function are used to delete all the Price Ranges present
		 * for a product.
		 *
		 * @since 4.1.0
		 */

		function bkap_delete_all_ranges() {

			$post_id    = $_POST['post_id'];
			$blank_data = '';
			update_post_meta( $post_id, '_bkap_price_range_data', $blank_data );

			die();
		}

		/**
		 * This function are used to clear block pricing option radio button selection
		 * in the Booking meta box.
		 *
		 * @since 4.1.0
		 */

		function bkap_block_pricing_options() {

			$post_id    = $_POST['product_id'];
			$blank_data = '';

			update_post_meta( $post_id, '_bkap_price_ranges', $blank_data );
			update_post_meta( $post_id, '_bkap_fixed_blocks', $blank_data );

			die();
		}

		/**
		 * This function will add the Block Pricing tab in the Booking meta box.
		 *
		 * @hook bkap_add_tabs
		 * @since 4.1.0
		 */
		function block_pricing_tab( $product_id ) {
			?>
			<li class="tstab-tab" data-link="block_booking_page">
				<a id="block_booking" class="bkap_tab" style="display:none">
					<i class="fa fa-align-justify" aria-hidden="true"></i><?php _e( 'Block Pricing', 'woocommerce-booking' ); ?>
				</a>
			</li>
			<?php
		}

		/**
		 * This function add the content for the Booking meta box->Block Pricing tab.
		 *
		 * @param integer $product_id Product ID
		 * @global object $post WP_Post
		 *
		 * @hook bkap_after_listing_enabled
		 * @since 4.1.0
		 */

		public function bkap_block_pricing_show_field_settings( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$duplicate_of = bkap_common::bkap_get_product_id( $product_id );
			?>
			<div id="block_booking_page" class="tstab-content" style="position: relative; display: none;">
				<?php
				// This is the section where one can enable the block pricing option.
				$this->bkap_enable_price_by_range_or_fixed_block_booking( $duplicate_of, $booking_settings, $default_booking_settings, $defaults );
				?>
				<hr/>				
				<?php
					// This functions are for Fixed Block Booking and Price By Range tables.
					$this->bkap_fixed_block_booking_table( $duplicate_of, $booking_settings, $default_booking_settings, $defaults );
					$this->bkap_price_range_booking_table( $duplicate_of, $booking_settings, $default_booking_settings, $defaults );
				?>
				<div id='block_pricing_update_notification' style='display:none;'></div>
			</div>
			<?php
		}

		/**
		 * Create and return an array of Fixed Blocks data.
		 * Executed when settings are saved using either from the Booking meta box
		 * or the product is saved/updated.
		 *
		 * @param $product_id Product ID
		 * @param object                $clean_fixed_block_data
		 * @return array $array_of_all_fixed_block_data
		 * @since 4.1.0
		 */

		static function bkap_updating_fixed_block_data_in_db( $product_id, $clean_fixed_block_data ) {

			$fixed_block_data      = $clean_fixed_block_data->bkap_fixed_block_data;
			$fixed_block_each_data = explode( ';', $fixed_block_data );

			array_pop( $fixed_block_each_data );

			$array_of_all_fixed_block_data = array();

			foreach ( $fixed_block_each_data as $fixed_block_each_data_value ) {
				$array_of_individual_fixed_block_data = explode( '&&', $fixed_block_each_data_value );
				$array_of_all_fixed_block_data[ $array_of_individual_fixed_block_data[5] ]['block_name']     = $array_of_individual_fixed_block_data[0];
				$array_of_all_fixed_block_data[ $array_of_individual_fixed_block_data[5] ]['number_of_days'] = $array_of_individual_fixed_block_data[1];
				$array_of_all_fixed_block_data[ $array_of_individual_fixed_block_data[5] ]['start_day']      = $array_of_individual_fixed_block_data[2];
				$array_of_all_fixed_block_data[ $array_of_individual_fixed_block_data[5] ]['end_day']        = $array_of_individual_fixed_block_data[3];
				$array_of_all_fixed_block_data[ $array_of_individual_fixed_block_data[5] ]['price']          = $array_of_individual_fixed_block_data[4];

			}

			return $array_of_all_fixed_block_data;
		}

		/**
		 * Create and return an array of Price Ranges data.
		 * Executed when settings are saved using either from the Booking meta box
		 * or the product is saved/updated.
		 *
		 * @param $product_id Product ID
		 * @param object                $clean_price_range_data
		 * @return array $array_of_all_price_range_data
		 * @since 4.1.0
		 */

		static function bkap_updating_price_range_data_in_db( $product_id, $clean_price_range_data ) {

			$price_range_data      = $clean_price_range_data->bkap_price_range_data;
			$price_range_each_data = explode( ';;', $price_range_data );

			array_pop( $price_range_each_data );

			$array_of_all_price_range_data = array();

			$product_attributes = get_post_meta( $product_id, '_product_attributes', true );

			$product_attributes_keys = array();
			if ( is_array( $product_attributes ) && $product_attributes > 0 ) {
				$product_attributes_keys = array_keys( $product_attributes );
			}

			foreach ( $price_range_each_data as $price_range_each_data_value ) {
				$array_of_individual_price_range_data = explode( '~~', $price_range_each_data_value );

				$key_of_array = end( $array_of_individual_price_range_data );
				$count        = count( $array_of_individual_price_range_data );

				if ( $count > 5 ) {
					$count_new = $count - 6;
					for ( $i = 0; $i <= $count_new; $i++ ) {
						$attribute = $product_attributes_keys[ $i ];
						$array_of_all_price_range_data[ $key_of_array ][ $attribute ] = $array_of_individual_price_range_data[ $i ];
					}
				}

				$min_number = ( isset( $array_of_individual_price_range_data[ $count - 5 ] ) && ! empty( $array_of_individual_price_range_data[ $count - 5 ] ) ) ? $array_of_individual_price_range_data[ $count - 5 ] : '';
				$max_number = ( isset( $array_of_individual_price_range_data[ $count - 4 ] ) && ! empty( $array_of_individual_price_range_data[ $count - 4 ] ) ) ? $array_of_individual_price_range_data[ $count - 4 ] : '';
				$p_d_price  = ( isset( $array_of_individual_price_range_data[ $count - 3 ] ) && ! empty( $array_of_individual_price_range_data[ $count - 3 ] ) ) ? $array_of_individual_price_range_data[ $count - 3 ] : 0;
				$f_price    = ( isset( $array_of_individual_price_range_data[ $count - 2 ] ) && ! empty( $array_of_individual_price_range_data[ $count - 2 ] ) ) ? $array_of_individual_price_range_data[ $count - 2 ] : 0;

				$array_of_all_price_range_data[ $key_of_array ]['min_number']    = $min_number;
				$array_of_all_price_range_data[ $key_of_array ]['max_number']    = $max_number;
				$array_of_all_price_range_data[ $key_of_array ]['per_day_price'] = $p_d_price;
				$array_of_all_price_range_data[ $key_of_array ]['fixed_price']   = $f_price;

			}
			return $array_of_all_price_range_data;
		}

		/**
		 * This function add the fixed block dropdown on the frontend product page
		 * as per the settings selected when Enable Fixed Block Booking is enabled.
		 *
		 * @param $product_id - Product ID
		 *
		 * @hook bkap_before_booking_form
		 * @since 4.1.0
		 */

		function bkap_fixed_block_booking_after_add_to_cart( $product_id ) {

			global $post;

			if ( ! is_null( $post ) && get_post_type() == 'product' ) {
				$duplicate_of = $post->ID;
			} else {
				$duplicate_of = $product_id;
			}

			$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

			if ( ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' )
			   && ( isset( $booking_settings['booking_fixed_block_enable'] ) && $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable' ) ) {

				// Getting Fixed block data from the post meta.
				// $results = $this->bkap_get_fixed_blocks( $duplicate_of );
				$results = $booking_settings['bkap_fixed_blocks_data'];

				$number = 0;
				if ( get_post_type() == 'bkap_booking' ) {

					$bkap           = new BKAP_Booking( $post->ID );
					$number_of_days = $bkap->end - $bkap->start;
					$number         = floor( $number_of_days / ( 60 * 60 * 24 ) );
					$number         = ( $number == 0 ) ? 1 : $number;

					if ( isset( $bkap->custom_fields['_bkap_fixed_block'] ) ) {
						$bkap_fixed_block = $bkap->custom_fields['_bkap_fixed_block'];

						if ( '' !== $bkap_fixed_block[0] ) {
							$bkap_selected_fixed_block = explode( '&', $bkap_fixed_block[0] );
							$bkap_selected_fixed_block_day = $bkap_selected_fixed_block[0];

						}
					}

				} elseif ( isset( WC()->cart ) ) {

					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

						if ( array_key_exists( 'bkap_booking', $values ) ) {
								$booking     = $values['bkap_booking'];
								$hidden_date = $booking[0]['hidden_date'];

							if ( array_key_exists( 'hidden_date_checkout', $booking[0] ) ) {
								$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
							}
						}
						break;
					}
				}

				// Getting first key of $result array.
				reset( $results );
				$first_key = key( $results );

				$show_field = apply_filters( 'bkap_show_fixed_blocks_field', true, $duplicate_of, $booking_settings, $results );
				
				if ( count( $results ) > 0 && $show_field ) {

					$curr_lang = 'en';
					if ( defined( 'ICL_LANGUAGE_CODE' ) ) { // Fetching the current language of WPML or any other transaltion plugin is active.

						if ( ICL_LANGUAGE_CODE == 'en' ) {
							$curr_lang = 'en';
						} else {
							$curr_lang = ICL_LANGUAGE_CODE;
						}
					}

					printf(
						'<div class="bkap-fixed-block-div"><label>' . __( get_option( 'book_fixed-block-label' ), 'woocommerce-booking' ) . ': </label>
		                     <br/>
		                     <select name="block_option" id="block_option" >'
					);

					$block_selected = false;

					foreach ( $results as $key => $value ) {

						$name_msg   = 'bkap_fixed_' . $key . '_block_name';
						$block_name = $value['block_name'];
						$block_name = $this->bkap_get_translated_texts( $name_msg, $block_name, $curr_lang );

						$selected = '';
						if ( $number == $value['number_of_days'] && ! $block_selected ) {
							if ( isset( $bkap_selected_fixed_block_day ) ) {
								if ( $bkap_selected_fixed_block_day == $value['start_day'] ) {
									$selected       = 'selected';
									$first_key      = $key;
									$block_selected = true;
								}
							} else {
								$selected       = 'selected';
								$first_key      = $key;
								$block_selected = true;
							}
						}

						printf( '<option value=%s&%s&%s %s>%s</option>', $value['start_day'], $value['number_of_days'], $value['price'], $selected, $block_name );
					}
					printf( '</select> <br/> <br/></div>' );

					if ( count( $results ) >= 0 ) {
						$sd = $results[ $first_key ]['start_day'];
						$nd = $results[ $first_key ]['number_of_days'];
						$pd = $results[ $first_key ]['price'];
					}
					echo ' <input type="hidden" id="block_option_enabled"  name="block_option_enabled" value="on"/> <input type="hidden" id="block_option_start_day"  name="block_option_start_day" value="' . $sd . '"/> <input type="hidden" id="block_option_number_of_day"  name="block_option_number_of_day" value="' . $nd . '"/><input type="hidden" id="block_option_price"  name="block_option_price" value="' . $pd . '"/>';
				} else {
					$number_of_fixed_price_blocks = 0;
					echo ' <input type="hidden" id="block_option_enabled"  name="block_option_enabled" value="off"/> <input type="hidden" id="block_option_start_day"  name="block_option_start_day" value=""/> <input type="hidden" id="block_option_number_of_day"  name="block_option_number_of_day" value=""/><input type="hidden" id="block_option_price"  name="block_option_price" value=""/>';
				}
			}
		}

		/**
		 * This function adds the price div to display the Booking Price
		 * on the Product page.
		 *
		 * @param integer $product_id - Product ID
		 *
		 * @hook bkap_display_price_div
		 * @since 4.1.0
		 */

		function bkap_fixed_block_display_price( $product_id ) {

			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			if ( isset( $_POST['booking_fixed_block_enable'] ) && $_POST['booking_partial_payment_radio'] != '' ) :
				$currency_symbol = get_woocommerce_currency_symbol();
				if ( has_filter( 'bkap_show_addon_price' ) ) {
					$show_price = apply_filters( 'bkap_show_addon_price', '' );
				} else {
					$show_price = 'show';
				}
				print( '<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:' . $show_price . ';">' . $currency_symbol . ' 0</div>' );
			endif;
		}

		/**
		 * This function is to display Block Pricing radio button settings
		 * in Booking meta box->Block Pricing tab.
		 *
		 * @param integer $duplicate_of - Product ID
		 * @since 4.1.0
		 **/

		function bkap_enable_price_by_range_or_fixed_block_booking( $duplicate_of, $booking_settings, $default_booking_settings, $defaults ) {

			$bkap_fixed_blocks_check = '';
			$bkap_price_ranges_check = '';

			$bkap_fixed_blocks = bkap_get_post_meta_data( $duplicate_of, '_bkap_fixed_blocks', $default_booking_settings, $defaults );
			$bkap_price_ranges = bkap_get_post_meta_data( $duplicate_of, '_bkap_price_ranges', $default_booking_settings, $defaults );

			if ( isset( $bkap_fixed_blocks ) && $bkap_fixed_blocks != '' ) {
				$bkap_fixed_blocks_check = 'checked';
			}

			if ( isset( $bkap_price_ranges ) && $bkap_price_ranges != '' ) {
				$bkap_price_ranges_check = 'checked';
			}

			?>
			<div id="enable_block_pricing_section" class="block_pricing_flex_main">

				<div class="block_pricing_flex_child pricing_left">
					<label><?php _e( 'Block Pricing', 'woocommerce-booking' ); ?></label>
				</div>

				<div class="block_pricing_flex_child pricing_center block_type_main"> 
					<div class="block_pricing_flex_child_block_type" >
						<input type="radio" id="booking_fixed_block_enable" name="bkap_enable_block_pricing_type" value="booking_fixed_block_enable" onclick="bkap_save_fixed_block_settings()" <?php echo $bkap_fixed_blocks_check; ?>></input>
						<label for="booking_fixed_block_enable"> <?php _e( 'Fixed Block Booking', 'woocommerce-booking' ); ?> </label>
					</div>

					<div class="block_pricing_flex_child_block_type" >
						<input type="radio" id="booking_block_price_enable" name="bkap_enable_block_pricing_type" value="booking_block_price_enable" onclick="bkap_save_price_by_range_settings()" <?php echo $bkap_price_ranges_check; ?>></input>
						<label for="booking_block_price_enable"> <?php _e( 'Price By Range Of Nights', 'woocommerce-booking' ); ?> </label>
					</div>
				</div>

				<div class="block_pricing_flex_child pricing_right bkap_help">
					<a href="#" class="bkap_clear_block_pricing_selection" style="font-size: 12px;font-style: italic;" >Clear selection</a>

					<img class="help_tip" width="16" height="16"  data-tip="<?php _e( 'Select Fixed Block Booking option if you want customers to book or rent this product for fixed number of days. Select Price By Range Of Nights option if you want to charge customers different prices for different day ranges.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png" />
				</div>

			</div>

			<?php
		}

		/**
		 * Price By Range Of Days table in Booking meta box->Block Pricing.
		 *
		 * @param $product_id - Product ID
		 * @param array                   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		public static function bkap_price_range_booking_table( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$bkap_price_range_option   = bkap_get_post_meta_data( $product_id, '_bkap_price_ranges', $default_booking_settings, $defaults );
			$bkap_fixed_price_option   = bkap_get_post_meta_data( $product_id, '_bkap_fixed_blocks', $default_booking_settings, $defaults );
			$display_price_range_table = 'display:none';

			if ( $bkap_price_range_option == '' && $bkap_fixed_price_option == '' ) {
				$display_price_range_table = '';
			}

			$disable_block_pricing_class = 'bkap_disable_block_pricing';

			if ( isset( $bkap_price_range_option ) && $bkap_price_range_option == 'booking_block_price_enable' ) {
				$disable_block_pricing_class = '';
				$display_price_range_table   = '';
			}

			$attribute_count    = 0;
			$product_attributes = get_post_meta( $product_id, '_product_attributes', true );

			if ( is_array( $product_attributes ) && $product_attributes > 0 ) {
				$attribute_count  = count( $product_attributes );
				$attribute_count += 1;
			} else {
				$attribute_count = 2;
			}
			?>
			
			<!-- Table for Price by range of days -->
			<div class="bkap_price_range_booking <?php echo $disable_block_pricing_class; ?>" style="<?php echo $display_price_range_table; ?>">
				 
				<div>
				   <h4><?php _e( 'Price by range of nights :', 'woocommerce-booking' ); ?></h4>
				</div>
				
				<table id="bkap_price_range_booking_table" >
				
				<?php

				 self::bkap_get_price_range_booking_heading( $product_id, $booking_settings ); // Adding Heading of the table.
				 self::bkap_get_price_range_base_data( $product_id, $booking_settings ); // Adding default one hidden row in the table based on which we will add new ranges.
				 self::bkap_get_price_range_booking( $product_id, $booking_settings, $default_booking_settings, $defaults  ); // Displaying the table based on the added ranges.

				?>
				
				<tr style="padding:5px; border-top:2px solid #eee">
				   <td colspan="<?php echo $attribute_count; ?>" style="border-right: 0px;">
					   <i>
						   <small><?php _e( 'Create block ranges and its per day and/or fixed price.', 'woocommerce-booking' ); ?></small>
					   <i>
				   </td>
				   <td colspan="4" align="right" style="border-left: none;">
				   <?php if ( isset( $_GET['action'] ) && $_GET['action'] != 'bulk_booking_settings' ) { ?>
				   <button type="button" class="button-primary bkap_save_price_range" onclick="bkap_save_price_ranges()"><i class="fas fa-save fa-lg"></i> <?php _e( 'Save', 'woocommerce-booking' ); ?></button>
					<?php } ?>
				   <button type="button" class="button-primary bkap_add_new_price_range"><i class="fa fa-plus" aria-hidden="true"></i> <?php _e( 'Add New Range', 'woocommerce-booking' ); ?></button></td>
				</tr>
				
				</table>
			</div>
			<?php
		}

		/**
		 * Echoes the Column Headers for the Price Ranges table in
		 * Booking meta box->Block Pricing
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		static function bkap_get_price_range_booking_heading( $product_id, $booking_settings ) {

			$currency_symbol    = get_woocommerce_currency_symbol();
			$product_attributes = get_post_meta( $product_id, '_product_attributes', true );
			$width              = '';
			$count_attributes   = 0;

			if ( is_array( $product_attributes ) && count( $product_attributes ) > 0 ) {
				$count_attributes = count( $product_attributes );
			}

			$count_attributes += 4;
			$available_width   = 90;

			$width_size = ( $available_width / $count_attributes );
			$width_size = round( $width_size, 2 );
			$width      = 'width="' . $width_size . '%"';

			$product = wc_get_product( $product_id );
			if ( false == $product ) {
				$product_type = '';
			} else {
				$product_type = $product->get_type();
			}

			?>
			<tr>
			<?php
			if ( $product_attributes != '' && $product_type == 'variable' ) {
				foreach ( $product_attributes as $k => $v ) {
					$attribute_name = wc_attribute_label( $v['name'] );
					?>
						<th <?php echo $width; ?>><?php _e( $attribute_name, 'woocommerce-booking' ); ?></th>
					<?php
				}
			}
			?>
				<th <?php echo $width; ?>><?php _e( 'Minimum Day', 'woocommerce-booking' ); ?></th>
				<th <?php echo $width; ?>><?php _e( 'Maximum Day', 'woocommerce-booking' ); ?></th>
				<th <?php echo $width; ?>><?php _e( "Per Day ($currency_symbol)", 'woocommerce-booking' ); ?></th>
				<th <?php echo $width; ?>><?php _e( "Fixed ($currency_symbol)", 'woocommerce-booking' ); ?></th>
				
				<th width="4%" id="bkap_price_range_all_close" class="bkap_remove_all_price_ranges" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></th>
			</tr>
			<?php
		}

		/**
		 * Fixed Blocks table in Booking meta box->Block Pricing.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		public static function bkap_fixed_block_booking_table( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$bkap_fixed_block_option = ( isset( $booking_settings['booking_fixed_block_enable'] ) && '' !== $booking_settings['booking_fixed_block_enable'] ) ? $booking_settings['booking_fixed_block_enable'] : '';
			$bkap_price_range_option = bkap_get_post_meta_data( $product_id, '_bkap_price_ranges', $default_booking_settings, $defaults );

			$display_fixed_block_table = 'display:none';
			if ( $bkap_fixed_block_option == '' && $bkap_price_range_option == '' ) {
				$display_fixed_block_table = '';
			}

			$disable_block_pricing_class = 'bkap_disable_block_pricing';

			if ( isset( $bkap_fixed_block_option ) && $bkap_fixed_block_option == 'booking_fixed_block_enable' ) {
				$disable_block_pricing_class = '';
				$display_fixed_block_table   = '';
			}

			?>
		
		<!-- Table for Fixed Block Booking -->
		<div class="bkap_fixed_block_booking <?php echo $disable_block_pricing_class; ?>" style="<?php echo $display_fixed_block_table; ?>">
		
			<div>
				<h4><?php _e( 'Fixed Blocks Booking :', 'woocommerce-booking' ); ?></h4>
			</div>
		
			<table id="bkap_fixed_block_booking_table" >
				<?php
				 // add date and time setup.
				 self::bkap_get_fixed_block_booking_heading( $product_id, $booking_settings );
				 self::bkap_get_fixed_block_booking_base_data( $product_id, $booking_settings );
				 self::bkap_get_fixed_block_booking( $product_id, $booking_settings, $default_booking_settings, $defaults );
				?>
				
				<tr style="padding:5px; border-top:2px solid #eee">
				   <td colspan="3" style="border-right: 0px;">
					   <i>
						   <small><?php _e( 'Create fixed blocks of booking and its price.', 'woocommerce-booking' ); ?></small>
					   <i>
				   </td>
				   <td colspan="3" align="right" style="border-left: none;">
				   <?php if ( isset( $_GET['action'] ) && $_GET['action'] != 'bulk_booking_settings' ) { ?>
					   <button type="button" class="button-primary bkap_save_fixed_block" onclick="bkap_save_fixed_blocks()"><i class="fas fa-save fa-lg"></i> <?php _e( 'Save', 'woocommerce-booking' ); ?></button>
					<?php } ?> 
				   
				   <button type="button" class="button-primary bkap_add_new_fixed_block"><i class="fa fa-plus" aria-hidden="true"></i> <?php _e( 'Add New Block', 'woocommerce-booking' ); ?></button></td>
				</tr>
			</table>
		
		</div>
			<?php
		}

		/**
		 * Echoes the Column Headers for the Fixed Blocks table in
		 * Booking meta box->Block Pricing
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		static function bkap_get_fixed_block_booking_heading( $product_id, $booking_settings ) {
			$currency_symbol = get_woocommerce_currency_symbol();
			?>
			<tr>
				<th width="25%"><?php _e( 'Block Name', 'woocommerce-booking' ); ?></th>
				<th width="10%"><?php _e( 'Days', 'woocommerce-booking' ); ?></th>
				<th width="20%"><?php _e( 'Start Day', 'woocommerce-booking' ); ?></th>
				<th width="20%"><?php _e( 'End Day', 'woocommerce-booking' ); ?></th>
				<th width="20"><?php _e( 'Price (' . $currency_symbol . ')', 'woocommerce-booking' ); ?></th>
				<td width="4%" id="bkap_fixed_block_all_close" class="bkap_remove_all_fixed_blocks" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></th>
			</tr>
			<?php
		}

		/**
		 * Adds a base table row for Booking meta box->Block Pricing->Fixed Blocks.
		 * Used when adding a new row to the table.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @global array $bkap_fixed_days array of weeekdays and its number
		 * @since 4.1.0
		 **/
		static function bkap_get_fixed_block_booking_base_data( $product_id, $booking_settings ) {
			global $bkap_fixed_days;
			?>
		
		<tr id="bkap_default_fixed_block_row" style="display: none;">
			<td width="25%">
				<input type="text" id="booking_block_name" name="booking_block_name" style="width:100%" placeholder="Enter Name of Block"></input>
			</td>
			<td width="10%">
				<input type="number" id="number_of_days" name="number_of_days" min=0 style="width:100%"></input>
			</td>
			<td width="20%">
				<select id="start_day" name="start_day" style="width:100%">
					<?php
					$days = $bkap_fixed_days;
					foreach ( $days as $dkey => $dvalue ) {
						?>
					<option value="<?php echo $dkey; ?>"><?php echo $dvalue; ?></option>
						<?php
					}
					?>
				</select>
			</td>
			<td width="20%">
				<select id="end_day" name="end_day" style="width:100%">
				<?php
				foreach ( $days as $dkey => $dvalue ) {
					?>
					<option value="<?php echo $dkey; ?>"><?php echo $dvalue; ?></option>
					<?php
				}
				?>
				</select>
			 </td>
			<td width="20%"><input type="text" class="bkap_input_price" id="fixed_block_price" name="fixed_block_price" style="width:100%" placeholder="Block Price"></input></td>
			
			<td width="4%" id="bkap_fixed_block_close" class="" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
		</tr>
		
			<?php
		}

		/**
		 * Adds a base table row for Booking meta box->Block Pricing->Price Ranges.
		 * Used when adding a new row to the table.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		static function bkap_get_price_range_base_data( $product_id, $booking_settings ) {

			$product_attributes = get_post_meta( $product_id, '_product_attributes', true );
			$i                  = 1;
			$j                  = 1;
			$product            = wc_get_product( $product_id );
			if ( $product == false ) {
				$product_type = '';
			} else {
				$product_type = $product->get_type();
			}

			?>
			<tr id="bkap_default_price_range_row" style="display: none;">
			<?php
			if ( $product_attributes != '' && $product_type == 'variable' ) {

				foreach ( $product_attributes as $key => $value ) {

					if ( $value['is_taxonomy'] ) {
						$value_array = wc_get_product_terms( $product_id, $value['name'], array( 'fields' => 'names' ) );
					} else {
						$value_array = explode( ' | ', $value['value'] );
					}

						print( '<td><select name="attribute_' . $i . '" id="attribute_' . $i . '" value="" style="width:100%">' );

						$j = 1;

					foreach ( $value_array as $k => $v ) {
						$attr_value = trim( $v );
						print( '<option name="option_attribute_' . $i . '_' . $j . '" id="option_attribute_' . $i . '_' . $j . '" value="' . htmlspecialchars( $attr_value ) . '">' . $v . '</option>' );
					}

						print( '</select></td>' );
						$i++;
						$j++;
				}
			}
			?>
				<td>
					<input type="number" id="number_of_start_days" name="number_of_start_days" min=0 style="width:100%"></input>
				</td>
				<td>
					<input type="number" id="number_of_end_days" name="number_of_end_days" min=0 style="width:100%"></input>
				</td>
				<td >
					<input type="text" class="bkap_input_price" id="per_day_price" name="per_day_price" style="width:100%"></input>
				</td>
				<td>
					<input type="text" class="bkap_input_price" id="fixed_price" name="fixed_price" style="width:100%"></input>
				</td>
				
				<td width="4%" id="bkap_price_range_close" class="" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
			</tr>
		
			<?php
		}

		/**
		 * Displays the existing Price Ranges in Booking meta box->Block Pricing->Price Ranges
		 * as per the saved data.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @since 4.1.0
		 **/

		static function bkap_get_price_range_booking( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			$result      = bkap_get_post_meta_data( $product_id, '_bkap_price_range_data', $default_booking_settings, $defaults );
			$results     = ( isset( $result ) && $result != '' ) ? $result : array();
			$block_count = count( $results );
			$row_number  = 0;
			$max_key     = 0;
			$_product    = wc_get_product( $product_id );

			if ( $_product == false ) {
				$product_type = '';
			} else {
				$product_type = $_product->get_type();
			}

			if ( $block_count != 0 ) {
				$max_key = max( array_keys( $results ) );
			}

			$product_attributes = '';
			if ( $product_type == 'variable' ) {

				$product_attributes = get_post_meta( $product_id, '_product_attributes', true );

				if ( $product_attributes != '' ) {
					$product_attributes_keys = array_keys( $product_attributes );
				}
			}

			$c = 0;
			while ( $row_number <= $max_key ) {

				if ( ! in_array( $row_number, array_keys( $results ) ) ) {
					$row_number++;
					continue;
				}

				$i                 = 0;
				$number_of_columns = count( $results[ $row_number ] );
				$min_number        = $max_number = $per_day_price = $fixed_price = '';

				$min_number    = $results[ $row_number ]['min_number'];
				$max_number    = $results[ $row_number ]['max_number'];
				$per_day_price = $results[ $row_number ]['per_day_price'];
				$fixed_price   = $results[ $row_number ]['fixed_price'];

				$bkap_row_toggle         = '';
				$bkap_row_toggle_display = '';

				if ( $c > 4 ) {
					$bkap_row_toggle         = 'bkap_price_range_row_toggle';
					$bkap_row_toggle_display = 'style="display:none;"';
				}

				?>
				<tr id="bkap_price_range_row_<?php echo $row_number; ?>" class="<?php echo $bkap_row_toggle; ?>" <?php echo $bkap_row_toggle_display; ?>>
				<?php
				if ( $product_attributes != '' && $product_type == 'variable' ) {

					foreach ( $product_attributes as $key => $value ) {

						if ( $value['is_taxonomy'] ) {
							$value_array = wc_get_product_terms( $product_id, $value['name'], array( 'fields' => 'names' ) );
						} else {
							$value_array = explode( ' | ', $value['value'] );
						}

							print( '<td><select name="attribute_' . $i . '_' . $row_number . '" id="attribute_' . $i . '_' . $row_number . '" value="" style="width:100%">' );

							$j = 1;

						foreach ( $value_array as $k => $v ) {
							$attr_value    = trim( $v );
							$attribute_key = $product_attributes_keys[ $i ];
							$selected      = '';

							if ( $attr_value == $results[ $row_number ][ $attribute_key ] ) {
								$selected = 'selected';
							}
							print( '<option name="option_attribute_' . $i . '_' . $j . '" id="option_attribute_' . $i . '_' . $j . '" value="' . htmlspecialchars( $attr_value ) . '" ' . $selected . '>' . $v . '</option>' );
						}

							print( '</select></td>' );
							$i++;
							$j++;
					}
				}
				?>
					<td>
						<input type="number" id="number_of_start_days_<?php echo $row_number; ?>" name="number_of_start_days" min=0 style="width:100%" value="<?php echo $min_number; ?>"></input>
					</td>
					<td>
						<input type="number" id="number_of_end_days_<?php echo $row_number; ?>" name="number_of_end_days" min=0 style="width:100%" value="<?php echo $max_number; ?>"></input>
					</td>
					<td >
						<input type="text" class="wc_input_price" id="per_day_price_<?php echo $row_number; ?>" name="per_day_price" style="width:100%" value="<?php echo $per_day_price; ?>"></input>
					</td>
					<td>
						<input type="text" class="wc_input_price" id="fixed_price_<?php echo $row_number; ?>" name="fixed_price" style="width:100%" value="<?php echo $fixed_price; ?>"></input>
					</td>
					
					<td width="4%" id="bkap_price_range_close_<?php echo $row_number; ?>" class="" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
				</tr>
			
				<?php

				$row_number++;
				$c++;
			}

			if ( $block_count > 5 ) {
				?>
			<tr style="">
			   <td colspan="<?php echo $number_of_columns + 1; ?>">
			   <span class="bkap_expand-close">
				   <a href="#" class="bkap_price_range_expand_all"><?php echo __( 'Expand', 'woocommerce-booking' ); ?></a> / <a href="#" class="bkap_price_range_close_all"><?php echo __( 'Close', 'woocommerce-booking' ); ?></a>
			   </span>
			   </td>
			</tr>
				<?php
			}

		}

		/**
		 * Displays the existing Fixed Blocks in Booking meta box->Block Pricing->Fixed Blocks
		 * as per the saved data.
		 *
		 * @param integer $product_id - Product ID
		 * @param array   $booking_settings - Booking Settings for the product.
		 * @global array $bkap_fixed_days Array of weekdays and its numbers
		 * @global object $post WP_Post
		 * @global object $wpdb Global wpdb object
		 *
		 * @since 4.1.0
		 **/

		static function bkap_get_fixed_block_booking( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

			global $bkap_fixed_days;

			global $post, $wpdb;

			$duplicate_of = bkap_common::bkap_get_product_id( $product_id );

			if ( isset( $duplicate_of ) ) {
				$post_id = $duplicate_of;
			}

			$result      = bkap_get_post_meta_data( $post_id, '_bkap_fixed_blocks_data', $default_booking_settings, $defaults );
			$results     = ( isset( $result ) && $result != '' ) ? $result : array();
			$block_count = count( $results );
			$max_key     = 0;

			if ( $block_count != 0 ) {
				$max_key = max( array_keys( $results ) );
			}

			$row_number = 0;
			$i          = 0;

			while ( $row_number <= $max_key ) {

				if ( ! in_array( $row_number, array_keys( $results ) ) ) {
					$row_number++;
					continue;
				}

				$block_name = $number_of_days = $start_day = $end_day = $price = '';

				$block_name     = $results[ $row_number ]['block_name'];
				$number_of_days = $results[ $row_number ]['number_of_days'];
				$start_day      = $results[ $row_number ]['start_day'];
				$end_day        = $results[ $row_number ]['end_day'];
				$price          = $results[ $row_number ]['price'];

				$bkap_row_toggle         = '';
				$bkap_row_toggle_display = '';

				if ( $i > 4 ) {
					$bkap_row_toggle         = 'bkap_fixed_row_toggle';
					$bkap_row_toggle_display = 'style="display:none;"';
				}

				?>

			<tr id="bkap_fixed_block_row_<?php echo $row_number; ?>" class="<?php echo $bkap_row_toggle; ?>" <?php echo $bkap_row_toggle_display; ?>>
				<td width="25%">
					<input type="text" id="booking_block_name_<?php echo $row_number; ?>" name="booking_block_name" style="width:100%" placeholder="Enter Name of Block" value="<?php echo $block_name; ?>"></input>
				</td>
				<td width="10%">
					<input type="number" id="number_of_days_<?php echo $row_number; ?>" name="number_of_days" min=0 style="width:100%" value="<?php echo $number_of_days; ?>"></input>
				</td>
				<td width="20%">

					<select id="start_day_<?php echo $row_number; ?>" name="start_day" style="width:100%">
						<?php
						$days = $bkap_fixed_days;
						foreach ( $days as $dkey => $dvalue ) {
							$start_selected = '';
							// echo gettype( $dkey ) . ' - ' . gettype( $start_day );
							if ( (string) $dkey == (string) $start_day ) {
								$start_selected = 'selected';
							}
							?>
							<option value="<?php echo $dkey; ?>" <?php echo $start_selected; ?>><?php echo $dvalue; ?></option> 
							<?php
						}
						?>
					</select>
				</td>
				<td width="20%">
					<select id="end_day_<?php echo $row_number; ?>" name="end_day" style="width:100%">
					<?php
					foreach ( $days as $dkey => $dvalue ) {
						$end_selected = '';
						if ( (string) $dkey == (string) $end_day ) {
							$end_selected = 'selected';

						}
						?>
						<option value="<?php echo $dkey; ?>" <?php echo $end_selected; ?>><?php echo $dvalue; ?></option> 
						<?php
					}
					?>
					</select>
				 </td>
				<td width="20%"><input type="text" class="bkap_input_price" id="fixed_block_price_<?php echo $row_number; ?>" name="fixed_block_price" style="width:100%" placeholder="Block Price" value="<?php echo $price; ?>"></input></td>
				
				<td width="4%" id="bkap_fixed_block_close_<?php echo $row_number; ?>" class="" style="text-align: center;cursor:pointer;"><i class="fa fa-trash" aria-hidden="true"></i></td>
			</tr>
		
				<?php
				$row_number++;
				$i++;
			}

			if ( $block_count > 5 ) {
				?>
			<tr style="">
			   <td colspan="6">
			   <span class="bkap_expand-close">
				   <a href="#" class="bkap_fixed_expand_all"><?php echo __( 'Expand', 'woocommerce-booking' ); ?></a> / <a href="#" class="bkap_fixed_close_all"><?php echo __( 'Close', 'woocommerce-booking' ); ?></a>
			   </span>
			   </td>
			</tr>
				<?php
			}
		}

		/**
		 * This function is used to clculate and show the booking price
		 * when Fixed Blocks are used
		 *
		 * @param integer $product_id  - Product ID
		 * @param string  $product_type - Product Type
		 * @param integer $variation_id - Variation ID, 0 for simple products
		 * @param string  $checkin_date - Booking start date
		 * @param string  $checkout_date - Booking end date
		 * @param string  $currency_selected - Currency such as USD, AUD etc.
		 *
		 * @hook bkap_display_multiple_day_updated_price
		 * @since 4.1.0
		 */

		function bkap_fixed_block_show_updated_price( $product_id, $product_type, $variation_id, $checkin_date, $checkout_date, $currency_selected ) {

			do_action( 'bkap_validating_booking_with_high_season', $product_id, $product_type, $variation_id, $checkin_date, $checkout_date, $currency_selected );

			if ( ! isset( $_POST['variable_blocks'] ) || ( isset( $_POST['variable_blocks'] ) && $_POST['variable_blocks'] != 'Y' ) ) {

				$duplicate_of     = bkap_common::bkap_get_product_id( $product_id );

			} else {

				if ( isset( $_POST['price'] ) ) {
					$price = $_POST['price'];
				}
			}
			$error_message = __( 'Please select an option.', 'woocommerce-booking' );
			if ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() || function_exists( 'is_bkap_seasonal_active' ) && is_bkap_seasonal_active() ) {
				if ( isset( $price ) && $price != '' ) {
					$_POST['price'] = $price;
				} else {
					print( 'jQuery( "#show_time_slot" ).html( "' . addslashes( $error_message ) . '");' );
					die();
				}
			} else {

				if ( isset( $price ) && $price !== '' ) {
					$_POST['price'] = $price;
				} else {
					print( 'jQuery( "#show_time_slot" ).html( "' . addslashes( $error_message ) . '");' );
					die();
				}
			}
		}

		/**
		 * This function is used to clculate and show the booking price
		 * when Price Ranges are used.
		 *
		 * @param integer $product_id  - Product ID
		 * @param string  $product_type - Product Type
		 * @param integer $variation_id - Variation ID, 0 for simple products
		 * @param string  $checkin_date - Booking start date
		 * @param string  $checkout_date - Booking end date
		 * @param string  $currency_selected - Currency such as USD, AUD etc.
		 *
		 * @hook bkap_display_multiple_day_updated_price
		 * @since 4.1.0
		 */

		public function bkap_price_range_show_updated_price(    $product_id,
																$booking_settings,
																$product_obj,
																$variation_id,
																$checkin_date,
																$checkout_date,
																$number,
																$gf_options,
																$resource_id,
																$person_data,
																$currency_selected
																 ) {
			/*
			 $price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $checkin_date, $checkout_date );*/

			/**
			 * If Price By Range is Enabled for Product
			 */

			if ( isset( $booking_settings['booking_block_price_enable'] ) && $booking_settings['booking_block_price_enable'] == 'booking_block_price_enable'
					&& ! empty( $booking_settings['bkap_price_range_data'] )
				) {

				global $sitepress;

				$product_type = $product_obj->get_type();

				if ( ! is_null( $sitepress ) ) {
					$curr_lang = $sitepress->get_current_language();

					if ( $curr_lang != 'en' ) {
						$proid = icl_object_id( $product_id, 'product', true, $curr_lang );
					} else {
						$proid = $product_id;
					}
				} else {
					$proid = $product_id;
				}

				$variations_selected = bkap_get_attribute_variations( $product_id, $proid, $product_type );

				if ( has_filter( 'bkap_price_ofsingleday_on_weekend' ) ) {
					$price = apply_filters(
						'bkap_price_ofsingleday_on_weekend',
						$product_id,
						$checkin_date,
						$checkout_date,
						$number,
						$product_type,
						$variation_id,
						$variations_selected
					);
				} else {
					$price = $this->price_range_calculate_price(
						$product_id,
						$product_type,
						$variation_id,
						$number,
						$variations_selected
					);
				}

				if ( isset( $_POST['variable_blocks'] ) && $_POST['variable_blocks'] == 'Y' ) {
					$_POST['price'] = $price;
				}
			} elseif ( isset( $booking_settings['booking_fixed_block_enable'] )
						&& $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable'
						&& $booking_settings['bkap_fixed_blocks_data']
						&& ! empty( $booking_settings['bkap_fixed_blocks_data'] )
					) {

				/**
				 * If Product has Fixed Blocks
				 */

				$product_type = $product_obj->get_type();

				do_action( 'bkap_validating_booking_with_high_season', $product_id, $product_type, $variation_id, $checkin_date, $checkout_date, $currency_selected );

				$product_type = $product_obj->get_type();

				if ( $product_type == 'variable' ) {

					if ( $variation_id != '' ) {
						$price = 0;
						if ( apply_filters( 'bkap_allow_variation_price', true ) ) {
							$price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $checkin_date, $checkout_date );
						}
						$price += $_POST['block_option_price'];
					}
				} else {
					$price = sanitize_text_field( $_POST['block_option_price'] );
				}

				$price = apply_filters( 'bkap_custom_price_for_fixed_block', $price, $product_id, $variation_id, $product_type, $checkin_date, $checkout_date );

				$_POST['price'] = get_standard_decimal_thousand_separator_price( $price );
			}
		}

		/**
		 * Returns the booking price applicable when Price Ranges is enabled.
		 *
		 * @param $product_id - Product ID
		 * @param string                  $product_type - Product Type
		 * @param integer                 $variation_id - Variation ID, 0 for simple products.
		 * @param integer                 $number - Number of nights being booked
		 * @param array                   $variations_selected - Attributes selected and their values.
		 *
		 * @since 4.1.0
		 *
		 * @return string $price Price based on the price by range
		 */

		public static function price_range_calculate_price( $product_id, $product_type, $variation_id, $number, $variations_selected ) {

			$price            = 0;
			$results_price    = array();
			$price_range      = get_post_meta( $product_id, '_bkap_price_ranges', true );
			$price_range_data = get_post_meta( $product_id, '_bkap_price_range_data', true );
			$_product         = wc_get_product( $product_id );

			if ( $product_type == 'variable' ) {

				if ( $price_range == 'booking_block_price_enable' && $price_range_data != '' ) {

					$bkap_post                = $_POST; // Getting all the data from
					$attribute_string         = 'attribute_';
					$attribute_variation_pair = array();

					// Preparing array for attribute and its value
					// so that in post meta array we can check for that combination
					foreach ( $bkap_post as $post_key => $post_value ) {

						if ( strpos( $post_key, $attribute_string ) !== false ) {

							$p_key                              = str_replace( $attribute_string, '', $post_key );
							$attribute_variation_pair[ $p_key ] = $post_value;
						}
					}

					// Removing attribute_selected from the attribute array so we got the final array.
					array_shift( $attribute_variation_pair );

					$attribute_variation_pair = array();

					$attribute_variation_pair = $variations_selected;

					// Modify the array to ensure Attribute names are used instead of slugs.
					foreach ( $attribute_variation_pair as $a_name => $a_slug ) {
						$term = get_term_by( 'slug', $a_slug, $a_name );
						if ( false !== $term ) {
							$attribute_variation_pair[ $a_name ] = $term->name;
						}
					}

					$_POST['fixed_price'] = $_POST['variable_blocks'] = 'N';

					$result_array = $results = array();

					// Looping through post meta and checking if there is range available
					// for selected variation and min max numbers of days.

					$e = 0;

					foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {

						$i           = 0;
						$a_v_p_count = count( $attribute_variation_pair );
						/*
						 * Looping through $attribute_variation_pair and setting $i
						 * so if the count of $attribute_variation_pair and $i is equal
						 * then combination is found in post meta for selected attributes
						 */

						foreach ( $attribute_variation_pair as $a_v_p_key => $a_v_p_value ) {
							if ( array_key_exists( $a_v_p_key, $price_range_data_value ) && $price_range_data_value[ $a_v_p_key ] == stripslashes( $a_v_p_value ) ) {
								$i++;
							}
						}

						/*
						 * Checking if the combination matched and selected number of days for booking
						 * is falling in min and max numbers set in range or not.
						 * If set then assign that array in $result_price
						 */

						if ( $i == $a_v_p_count && $price_range_data_value['min_number'] <= $number && $price_range_data_value['max_number'] >= $number ) {

							$results_price[ $e ] = $price_range_data_value; // here we got the range for the selection.
						}
						$e++;
					}

					// If we found the ranges in _bkap_price_range_data.
					if ( isset( $results_price ) && count( $results_price ) > 0 ) {

						// Looping throught the ranges and calculating the price.
						foreach ( $results_price as $k => $v ) {

							if ( ! empty( $results_price[ $k ] ) ) {
								$_POST['variable_blocks'] = 'Y';

								if ( $variation_id != '' ) {

									$price = get_post_meta( $variation_id, '_sale_price', true );

									if ( ! isset( $price ) || $price == '' || $price == 0 ) {
										$price = get_post_meta( $variation_id, '_regular_price', true );
									}

									if ( $v['fixed_price'] != '0' ) {
										$calc_price           = get_standard_decimal_thousand_separator_price( $v['fixed_price'] );
										$price                = $calc_price . '-fixed';
										$_POST['fixed_price'] = 'Y';
									} else {

										$calc_price = get_standard_decimal_thousand_separator_price( $v['per_day_price'] );
										$calc_price = $calc_price * $number;
										$price      = $calc_price . '-per_day';
									}
								} else {
									$price = 0;
								}
							} else {
								unset( $results_price[ $k ] );
							}
						}
					} else {

						/*
						 You will come here when number of days selected for booking do not have any matching range in data
						 * then see the last matching range based on nearest max day in other range.
						 */

						// Finding the ranges which have max days lower than selected number of days for booking.
						foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {

							$i           = 0;
							$a_v_p_count = count( $attribute_variation_pair );

							foreach ( $attribute_variation_pair as $a_v_p_key => $a_v_p_value ) {
								if ( array_key_exists( $a_v_p_key, $price_range_data_value ) && $price_range_data_value[ $a_v_p_key ] == stripslashes( $a_v_p_value ) ) {

									$i++;
								}
							}

							if ( $i == $a_v_p_count && $price_range_data_value['min_number'] <= $number ) {

								$results_price[ $e ] = $price_range_data_value;
							}
							$e++;
						}

						// In $results_price array all the ranges will be added which
						// have lower max days compare to selected days for booking.

						/*
						 * Sorting the array so the price of the range will be calculated correctly.
						 * Issue was occurring when range of days not added sequencially
						 */

						foreach ( $results_price as $kk => $val ) {
							$ab[ $kk ] = $val['max_number'];
						}

						asort( $ab );
						$ordered = array();

						foreach ( $ab as $key => $value ) {
							if ( array_key_exists( $key, $results_price ) ) {
								$ordered[ $key ] = $results_price[ $key ];
								unset( $results_price[ $key ] );
							}
						}

						$results_price = $ordered; // Here we are gettting sorted array for $result_price

						// Looping throught the ranges and calculating the price.
						foreach ( $results_price as $k => $v ) {

							if ( ! empty( $results_price[ $k ] ) ) {

								$_POST['variable_blocks'] = 'Y';

								if ( $variation_id != '' ) {
									$price = get_post_meta( $variation_id, '_sale_price', true );

									if ( ! isset( $price ) || $price == '' || $price == 0 ) {
										$price = get_post_meta( $variation_id, '_regular_price', true );
									}

									$diff_days = '';

									if ( $v['max_number'] < $number ) {
										$diff_days = $number - $v['max_number'];

										if ( $v['fixed_price'] != '0' ) {
											$_POST['fixed_price'] = 'Y';
											$calc_price           = $v['fixed_price'] + ( $price * $diff_days );
											$price                = $calc_price . '-fixed';
										} else {
											$calc_price = ( $v['per_day_price'] * $v['max_number'] ) + ( $price * $diff_days );
											$price      = $calc_price . '-per_day';
										}
									} else {

										if ( $v['fixed_price'] != '0' ) {
											$_POST['fixed_price'] = 'Y';
											$calc_price           = get_standard_decimal_thousand_separator_price( $v['fixed_price'] );
											$price                = $calc_price . '-fixed';
										} else {
											$calc_price = get_standard_decimal_thousand_separator_price( $v['per_day_price'] );
											$calc_price = $calc_price * $number;
											$price      = $calc_price . '-per_day';
										}
									}
								} else {
									$price = 0;
								}
							} else {
								unset( $results_price[ $k ] );
							}
						}

						// If no result found then simply set price as variation price.
						if ( count( $results_price ) == 0 ) {
							$price = 0;

							if ( $variation_id != '' ) {
								$price = get_post_meta( $variation_id, '_sale_price', true );

								if ( ! isset( $price ) || $price == '' || $price == 0 ) {
									$price = get_post_meta( $variation_id, '_regular_price', true );
								}
								$price .= '-';
							}
						}
					}
				} else { // price by range is not enable and no record in the price by range data.
					if ( $variation_id != '' ) {
						$price = get_post_meta( $variation_id, '_sale_price', true );

						if ( ! isset( $price ) || $price == '' || $price == 0 ) {
							$price = get_post_meta( $variation_id, '_regular_price', true );
						}
						$price .= '-';
					} else {
						$price = 0;
					}
				}
				// Code for variable product ends here
			} else {

				if ( $price_range == 'booking_block_price_enable' ) {
					// Price by range is active.

					$results_price = array();

					foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {

						if ( $price_range_data_value['min_number'] <= $number && $price_range_data_value['max_number'] >= $number ) {
							$results_price[ $price_range_data_key ] = $price_range_data[ $price_range_data_key ];
						}
					}

					if ( count( $results_price ) == 0 ) {
						// if no records found in $result price array then

						foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {

							if ( $price_range_data_value['min_number'] <= $number ) {
								$results_price[ $price_range_data_key ] = $price_range_data[ $price_range_data_key ];
							}
						}

						if ( count( $results_price ) == 0 ) {
							  $price  = $_product->get_price();
							  $price .= '-';

						} else {

							// select max value from the range
							$max_results_block = array();
							foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {
								if ( $price_range_data_value['min_number'] <= $number ) {
									array_push( $max_results_block, $price_range_data_value['max_number'] );
								}
							}

							$results_block['maximum_number_of_days'] = max( $max_results_block ); // Max day in all the range.

							$results_price = array();

							foreach ( $price_range_data as $price_range_data_key => $price_range_data_value ) {

								if ( $price_range_data_value['min_number'] <= $number && $price_range_data_value['max_number'] == $results_block['maximum_number_of_days'] ) {
									$results_price[ $price_range_data_key ] = $price_range_data[ $price_range_data_key ];
								}
							}

							foreach ( $results_price as $k => $v ) {

								if ( ! empty( $results_price[ $k ] ) ) {

									$_POST['variable_blocks'] = 'Y';
									$price                    = $_product->get_price();

									if ( $results_block['maximum_number_of_days'] < $number ) {
										$diff_days = $number - $results_block['maximum_number_of_days'];

										if ( $v['fixed_price'] != '0' ) {
											$_POST['fixed_price'] = 'Y';
											$calc_price           = $v['fixed_price'] + ( $price * $diff_days );
											$price                = $calc_price . '-fixed';
										} else {
											$calc_price = get_standard_decimal_thousand_separator_price( $v['per_day_price'] );
											$calc_price = ( $calc_price * $results_block['maximum_number_of_days'] ) + ( $price * $diff_days );
											$price      = $calc_price . '-per_day';
										}
									}
								} else {
									unset( $results_price[ $k ] );
								}
							}
						}
					} else {

						foreach ( $results_price as $k => $v ) {

							if ( ! empty( $results_price[ $k ] ) ) {

								$_POST['variable_blocks'] = 'Y';

								if ( $v['fixed_price'] != '0' ) {

									$_POST['fixed_price'] = 'Y';
									$price                = get_standard_decimal_thousand_separator_price( $v['fixed_price'] );
									$price               .= '-fixed';
									$price               .= '-';
								} else {

									$dtsprice = get_standard_decimal_thousand_separator_price( $v['per_day_price'] );

									$price = $dtsprice * $number;

									// $price = number_format ( $price, wc_get_price_decimals(), $decimal_separator, $thousand_separator );
									$price .= '-per_day';
									$price .= '-';
								}
							} else {
								unset( $results_price[ $k ] );
							}
						}
					}
				} else {
					// Price by range is not active.
					$price  = $_product->get_price();
					$price .= '-';

				}
			}

			return $price;
		}


		/**
		 * This function return the count of Fixed Blocks present for a product.
		 *
		 * @param integer $post_id - Product ID
		 * @return integer Count of Blocks present.
		 *
		 * @since 4.1.0
		 */

		static function bkap_get_fixed_blocks_count( $post_id ) {
			global $wpdb;

			$result  = get_post_meta( $post_id, '_bkap_fixed_blocks_data', true );
			$results = ( isset( $result ) && $result != '' ) ? $result : array();

			return count( $results );
		}

		/**
		 * Returns the Fixed Blocks present for a product.
		 *
		 * @param integer $post_id - Product ID
		 * @return array Fixed Blocks for the product.
		 *
		 * @since 4.1.0
		 */

		static function bkap_get_fixed_blocks( $post_id ) {
			global $wpdb;

			$result  = get_post_meta( $post_id, '_bkap_fixed_blocks_data', true );
			$results = ( isset( $result ) && $result != '' ) ? $result : array();

			return $results;
		}

		/**
		 * This function will return translated string for the Fixed Block names.
		 *
		 * @param string $get_translated_text
		 * @param string $message - Message being translated
		 * @param string $language - Language to be translated to
		 * @return string $translated - Translated Data
		 * @since 4.1.0
		 **/

		function bkap_get_translated_texts( $get_translated_text, $message, $language ) {

			if ( function_exists( 'icl_register_string' ) ) {
				if ( $language == 'en' ) {
					return $message;
				} else {
					global $wpdb;
					$context    = 'woocommerce-booking';
					$translated = '';
					$results    = $wpdb->get_results(
						$wpdb->prepare(
							"
			                SELECT s.name, s.value, t.value AS translation_value, t.status
			                FROM  {$wpdb->prefix}icl_strings s
			                LEFT JOIN {$wpdb->prefix}icl_string_translations t ON s.id = t.string_id
			                WHERE s.context = %s
			                AND (t.language = %s OR t.language IS NULL)
			                ",
							$context,
							$language
						),
						ARRAY_A
					);

					foreach ( $results as $each_entry ) {
						if ( $each_entry['name'] == $get_translated_text ) {
							if ( $each_entry['translation_value'] ) {
									$translated = $each_entry['translation_value'];
							} else {
										$translated = $each_entry['value'];
							}
						}

						if ( $translated != '' ) {
							return $translated;
						} else {
							return $message;
						}
					}
				}
			} else {
				return $message;
			}
		}

		  /**
		   * This function will ensure that when a new product
		   * is created as a duplicate of an existing one, then
		   * the Pricing Options (Fixed Blocks and Price Ranges)
		   * are also copied alongwith the other settings.
		   *
		   * @param integer $new_id - ID of the Product being created
		   * @param integer $old_id - ID of the product being copied from.
		   * @hook bkap_product_addon_duplicate
		   * @since 4.1.0
		   */

		function price_range_product_duplicate( $new_id, $old_id ) {

			$price_range      = get_post_meta( $old_id, '_bkap_price_ranges', true );
			$price_range_data = get_post_meta( $old_id, '_bkap_price_range_data', true );

			$fixed_block      = get_post_meta( $old_id, '_bkap_fixed_blocks', true );
			$fixed_block_data = get_post_meta( $old_id, '_bkap_fixed_blocks_data', true );

			update_post_meta( $new_id, '_bkap_price_ranges', $price_range );
			update_post_meta( $new_id, '_bkap_price_range_data', $price_range_data );

			update_post_meta( $new_id, '_bkap_fixed_blocks', $fixed_block );
			update_post_meta( $new_id, '_bkap_fixed_blocks_data', $fixed_block_data );

		}

		  /**
		   * This function adds hidden fields on the frontend
		   * product page if Price Ranges are enabled.
		   *
		   * @param $product_id - Product ID
		   *
		   * @hook bkap_before_booking_form
		   * @since 4.1.0
		   */

		function bkap_price_range_booking_after_add_to_cart( $product_id ) {

			$bkap_price_range = get_post_meta( $product_id, '_bkap_price_ranges', true );

			if ( isset( $bkap_price_range ) && $bkap_price_range == 'booking_block_price_enable' ) {

				$selected_attribute = '';

				if ( isset( $_GET['page'] ) && $_GET['page'] == 'bkap_create_booking_page' && isset( $_POST['bkap_product_id'] ) ) { // Added this for #3251

					if ( $product_id != $_POST['bkap_product_id'] ) {
						$variation_class = new WC_Product_Variation( $_POST['bkap_product_id'] );
						$get_attributes  = $variation_class->get_variation_attributes();

						if ( count( $get_attributes ) > 0 ) {

							foreach ( $get_attributes as $key => $value ) {
								$selected_attribute .= '|' . $value;
							}
						}
					}
				}

				echo ' <input type="hidden" id="block_option_enabled_price"  name="block_option_enabled_price" value="on"/>';
				  // echo ' <input type="hidden" id="block_variable_option_price"  name="block_variable_option_price" value=""/>';
				printf( ' <input type="hidden" id="wapbk_variation_value"  name="wapbk_variation_value" value="%s"/>', $selected_attribute );
			} else {
				echo ' <input type="hidden" id="block_option_enabled_price"  name="block_option_enabled_price" value=""/>';
			}
		}
	}
}
	$bkap_block_booking = new bkap_block_booking();

?>
