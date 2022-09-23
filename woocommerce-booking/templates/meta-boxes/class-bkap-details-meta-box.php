<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Booking Details Meta Boxes
 *
 * @author   Tyche Softwares
 * @package  BKAP/Meta-Boxes
 * @category Classes
 * @class    BKAP_Details_Meta_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Details_Meta_Box.
 */
class BKAP_Details_Meta_Box {

	/**
	 * Meta box ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Meta box title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Meta box context.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Meta box priority.
	 *
	 * @var string
	 */
	public $priority;

	/**
	 * Meta box post types.
	 *
	 * @var array
	 */
	public $post_types;

	/**
	 * Are meta boxes saved?
	 *
	 * @var boolean
	 */
	private static $saved_meta_box = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'bkap-booking-data';
		$this->title      = __( 'Booking Details', 'woocommerce-booking' );
		$this->context    = 'normal';
		$this->priority   = 'high';
		$this->post_types = array( 'bkap_booking' );
	}

	/**
	 * Check data and output warnings.
	 */
	private function sanity_check_notices( $booking, $product ) {

		global $post;

		if ( $booking->get_start() && strtotime( $booking->get_start() ) > strtotime( '+ 2 year', current_time( 'timestamp' ) ) ) {
			echo '<div class="updated highlight"><p>' . __( 'This booking is scheduled over 2 years into the future. Please ensure this is correct.', 'woocommerce-booking' ) . '</p></div>';
		}

		/*
			  if ( $product && is_callable( array( $product, 'get_max_date' ) ) ) {
			$max      = $product->get_max_date();
			$max_date = strtotime( "+{$max['value']} {$max['unit']}", current_time( 'timestamp' ) );
			if ( $booking->get_start() > $max_date || $booking->get_end() > $max_date ) {
				echo '<div class="updated highlight"><p>' . sprintf( __( 'This booking is scheduled over the products allowed max booking date (%s). Please ensure this is correct.', 'woocommerce-booking' ), date_i18n( wc_date_format(), $max_date ) ) . '</p></div>';
			}
		} */

		$product_id   = $booking->product_id;
		$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );
		if ( 'date_time' !== $booking_type ) { // dont run for time bookings as open ended time slots have a lower end time as compared to the start
			if ( $booking->get_start() && $booking->get_end() && strtotime( $booking->get_start() ) > strtotime( $booking->get_end() ) ) {
				echo '<div class="error"><p>' . __( 'This booking has an end date set before the start date.', 'woocommerce-booking' ) . '</p></div>';
			}
		}

		if ( $booking->get_product_id() && ! wc_get_product( $booking->get_product_id() ) ) {
			echo '<div class="error"><p>' . __( 'It appears the booking product associated with this booking has been removed.', 'woocommerce-booking' ) . '</p></div>';
		}

		// check if update errors exist
		$update_errors = get_post_meta( $post->ID, '_bkap_update_errors' );
		if ( is_array( $update_errors ) && count( $update_errors ) > 0 ) {
			foreach ( $update_errors as $msg ) {
				echo '<div class="error"><p>' . __( $msg[0], 'woocommerce-booking' ) . '</p></div>';
			}
			delete_post_meta( $post->ID, '_bkap_update_errors' );
		}

		return;
	}

	/**
	 * Meta box content.
	 */
	public function meta_box_inner( $post ) {
		global $booking;

		wp_nonce_field( 'bkap_details_meta_box', 'bkap_details_meta_box_nonce' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		if ( get_post_type( $post->ID ) === 'bkap_booking' ) {
			$booking = new BKAP_Booking( $post->ID );
		}

		$order      = $booking->get_order();
		$order_id   = absint( ( is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id ) );
		$product_id = $booking->get_product_id();

		$global_settings = bkap_global_setting();
		$booking_type    = get_post_meta( $product_id, '_bkap_booking_type', true );

		bkap_load_scripts_class::inlcude_frontend_scripts_css( $product_id );

		$customer_id       = $booking->get_customer_id();
		$product           = $booking->get_product( $product_id );
		$customer          = $booking->get_customer();
		$resource          = $booking->get_resource();
		$persons           = $booking->get_persons();
		
		$bkap_common       = new bkap_common();
		$statuses          = $bkap_common->get_bkap_booking_statuses();
		$bookable_products = array( '' => __( 'N/A', 'woocommerce-booking' ) );

		$quantity = $booking->get_quantity();
		if ( ! is_numeric( $quantity ) || $quantity < 1 ) {
			$quantity = 1;
		}

		$variation_id = $booking->get_variation_id();
		$booking_date = bkap_date_as_format( $booking->get_start(), 'Y-m-d' );

		$times_selected = explode( '-', $booking->get_time() );
		$time_format    = $bkap_common->bkap_get_time_format( $global_settings );
		$date_format    = $bkap_common->bkap_get_date_format( $global_settings );
		$time_display   = bkap_date_as_format( trim( $times_selected[0] ), $time_format );
		date( $time_format, strtotime( trim( $times_selected[0] ) ) );

		if ( isset( $times_selected[1] ) && '' !== trim( $times_selected[1] ) ) {
			$time_display .= ' - ' . date( $time_format, strtotime( trim( $times_selected[1] ) ) );
		}

		$this->sanity_check_notices( $booking, $product );

		$hidden_date = bkap_date_as_format( $booking_date, 'j-n-Y' );

		if ( 'multiple_days' === $booking_type ) {
			$hidden_checkout = bkap_date_as_format( $booking->get_end(), 'j-n-Y' );
			$past_checkout   = ( strtotime( $hidden_checkout ) < current_time( 'timestamp' ) ) ? 'YES' : 'NO';
		} else {
			$hidden_checkout = '';
			$past_checkout   = 'NO';
		}

		// Displaying Booking Information on the Edit Booking post page.

		$start_date_label = bkap_option( 'start_date' );
		$end_date_label   = bkap_option( 'end_date' );
		$time_label       = bkap_option( 'time' );

		$display_start_date = $display_end_date = '';
		$get_start_date     = $booking->get_start_date();
		$display_start_date = $get_start_date;

		$get_start_time = $booking->get_start_time();
		if ( $get_start_time != '' ) {
			$display_start_date .= ' - ' . $get_start_time;
		}

		$get_end_date     = $booking->get_end_date();
		$display_end_date = $get_end_date;

		$get_end_time = $booking->get_end_time();
		if ( $get_end_time != '' ) {
			$display_end_date .= ' - ' . $get_end_time;
		}

		if ( $display_start_date == $display_end_date ) {
			$display_end_date = '';
		}

		// Timezone Calculation
		$timezone_name = $booking->get_timezone_name();
		$asclientstr   = __( ' (As per customer Timezone)', 'woocommerce-booking' );

		if ( $timezone_name != '' ) {

			$bkap_offset = bkap_get_offset( $booking->get_timezone_offset() );
			date_default_timezone_set( bkap_booking_get_timezone_string() );
			$t_start_date = date( $date_format . ' ' . $time_format, $bkap_offset + strtotime( $booking->get_start() ) );
			$t_end_date   = date( $date_format . ' ' . $time_format, $bkap_offset + strtotime( $booking->get_end() ) );

			date_default_timezone_set( 'UTC' );
		}

		$duplicate_of = bkap_common::bkap_get_product_id( $product_id );

		?>
		<style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<div class="panel-wrap woocommerce">
			<div id="bkap_data" class="panel">
				<h2><?php printf( __( 'Booking #%s details', 'woocommerce-booking' ), esc_html( $post->ID ) ); ?></h2>
				<p class="bkap_number">
				<?php
				if ( $order ) {
					printf( ' ' . __( 'Linked to order %s.', 'woocommerce-booking' ), '<a href="' . admin_url( 'post.php?post=' . absint( ( is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id ) ) . '&action=edit' ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' );
				}

				?>
				</p>

				<div class="bkap_data_column_container">
					<div class="bkap_data_column">
						<h4><?php _e( 'General details', 'woocommerce-booking' ); ?></h4>

						<p class="form-field form-field-wide">
							<label for="_bkap_order_id"><?php _e( 'Order ID:', 'woocommerce-booking' ); ?></label>
							<?php if ( $booking->get_order_id() && $order ) : ?>
								<input name="_bkap_order_id" id="_bkap_order_id" value="<?php echo esc_html( $order->get_order_number() . ' &ndash; ' . date_i18n( wc_date_format(), strtotime( is_callable( array( $order, 'get_date_created' ) ) ? $order->get_date_created() : $order->post_date ) ) ); ?>" readonly/>
							<?php endif; ?>
						</p>

						<p class="form-field form-field-wide"><label for="bkap_date"><?php _e( 'Date created:', 'woocommerce-booking' ); ?></label>
							<input type="text" name="bkap_date" id="bkap_date" maxlength="10" value="<?php echo date_i18n( 'Y-m-d', strtotime( $booking->get_date_created() ) ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" readonly /> @ <input type="number" class="hour" placeholder="<?php _e( 'h', 'woocommerce-booking' ); ?>" name="bkap_date_hour" id="bkap_date_hour" maxlength="2" size="2" value="<?php echo date_i18n( 'H', strtotime( $booking->get_date_created() ) ); ?>" pattern="\-?\d+(\.\d{0,})?" readonly />:<input type="number" class="minute" placeholder="<?php _e( 'm', 'woocommerce-booking' ); ?>" name="bkap_date_minute" id="bkap_date_minute" maxlength="2" size="2" value="<?php echo date_i18n( 'i', strtotime( $booking->get_date_created() ) ); ?>" pattern="\-?\d+(\.\d{0,})?" readonly />
						</p>

						<p class="form-field form-field-wide">
							<label for="_bkap_status"><?php _e( 'Booking status:', 'woocommerce-booking' ); ?></label>
							<select id="_bkap_status" name="_bkap_status" class="wc-enhanced-select">
							<?php
							foreach ( $statuses as $key => $value ) {
								echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $booking->get_status(), false ) . '>' . esc_html__( $value, 'woocommerce-booking' ) . '</option>';
							}
							?>
							</select>
						</p>
						<?php if ( $timezone_name != '' ) { ?>
						<p class="form-field form-field-wide">
							<table style="border-collapse:collapse;">
								<tr>
									<td colspan="2"><label><b><?php echo __( 'Booking details as per client timezone:', 'woocommerce-booking' ); ?></b></label></td>
								</tr>
								<tr>
									<td><label><?php echo __( 'Time Zone:', 'woocommerce-booking' ); ?></label></td>
									<td><i><?php echo $timezone_name; ?></i></td>
								</tr>
								<tr>
									<td><label><?php echo $start_date_label; ?></label></td>
									<td><i><?php echo $t_start_date; ?></i></td>
								</tr>
								<tr>
									<td><label><?php echo $end_date_label; ?></label></td>
									<td><i><?php echo $t_end_date; ?></i></td>
								</tr>

							</table>
						</p>
						<?php } ?>
						<?php do_action( 'bkap_admin_booking_data_after_booking_details', $post->ID, $booking ); ?>

					</div>
					<div class="bkap_data_column">
						<h4><?php _e( 'Booking specification', 'woocommerce-booking' ); ?></h4>

						<p class="form-field form-field-wide">
							<label for="bkap_product_id"><?php _e( 'Booked Product:', 'woocommerce-booking' ); ?></label>
							<?php
							if ( $product ) {
								if ( $variation_id > 0 ) {
									$variation_obj = wc_get_product( $variation_id );
									$product_name  = $variation_obj->get_name();
								} else {
									$product_name = $product->get_name();
								}
								?>
								<input name="bkap_product_name" id="bkap_product_name" value="<?php echo esc_html( $product_name ); ?>" readonly/>
								<input type="hidden" name="bkap_product_id" id="bkap_product_id" value="<?php echo $product_id; ?>" readonly/>
							<?php } ?>
						</p>
						
						<p class="form-field form-field-wide">
							<label for="bkap_qty"><?php _e( 'Quantity:', 'woocommerce-booking' ); ?></label>
							<input type='number' min=1 name="bkap_qty" id="bkap_qty" class="input-text qty text" value="<?php echo $quantity; ?>" />
						</p>
						<p class="form-field form-field-wide">
							<label><?php echo $start_date_label; ?></label>
							<input value="<?php echo $display_start_date; ?>"readonly="">
						</p>
						
						<?php if ( $display_end_date != '' ) { ?>
						<p class="form-field form-field-wide">	
							<label><?php echo $end_date_label; ?></label>
							<input value="<?php echo $display_end_date; ?>"readonly="">
						</p>
						<?php } ?>
						<p class="form-field form-field-wide">
							<label><?php echo bkap_option( 'price' ); ?></label>
							<input value="<?php echo strip_tags( wc_price( $booking->get_cost() * $quantity ) ); ?>"readonly="">
						</p>
					</div>
					<div class="bkap_data_column">

					<?php if ( $product ) { ?>

						<input type="button" id="edit_bkap" class="button button-primary" value="<?php _e( 'Click to edit booking', 'woocommerce-booking' ); ?>" style="margin: 0 25%;">

						<div class="edit-page-booking-form" style="visibility: hidden;">
						
						<?php
						$product_type = $product->get_type();

						if ( $product_type === 'variable' ) {
							?>
							<input type="hidden" name="variation_id" class="variation_id" value="<?php echo $variation_id; ?>" />
							<?php
							$attributes = get_post_meta( $duplicate_of, '_product_attributes', true );
							$item_id    = $booking->get_item_id();

							if ( is_array( $attributes ) && count( $attributes ) > 0 ) {
								foreach ( $attributes as $a_name => $a_details ) {
									$attr_value = htmlspecialchars( wc_get_order_item_meta( $item_id, $a_name ), ENT_QUOTES );
									// print a hidden field for each of these
									print( "<input type='hidden' name='attribute_$a_name' value='$attr_value' />" );
								}
							}
						}

						$_product = wc_get_product( $product_id );
						// JS scripts
						bkap_load_scripts_class::include_frontend_scripts_js( $product_id );
						// localize the scripts
						$hidden_data = bkap_booking_process::bkap_localize_process_script( $product_id, true );
						// print the hidden fields
						// print the booking form
						$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

						wc_get_template(
							'bookings/bkap-bookings-box.php',
							array(
								'product_id'       => $duplicate_of,
								'product_obj'      => $_product,
								'booking_settings' => $booking_settings,
								'global_settings'  => $global_settings,
								'hidden_dates'     => $hidden_data,
							),
							'woocommerce-booking/',
							BKAP_BOOKINGS_TEMPLATE_PATH
						);
						?>
						<br><span id="bkap_price" class="price"></span>
						</div>
					<?php } ?>                        
					</div>
				</div>
			</div>
			<div><i><?php _e( 'This screen can be used to edit only the booking details and the product quantity.', 'woocommerce-booking' ); ?></i></div>
			<div class="clear"></div>
		</div>

		<?php
		$plugin_version_number = get_option( 'woocommerce_booking_db_version' );

		$order_url = get_admin_url() . 'post.php?post=' . $order_id . '&action=edit';
		$ajax_url  = get_admin_url() . 'admin-ajax.php';

		// if fixed blocks is enabled for multiple days, pass the block details.
		$booking_type   = get_post_meta( $duplicate_of, '_bkap_booking_type', true );
		$blocks_enabled = get_post_meta( $duplicate_of, '_bkap_fixed_blocks', true );

		$block_value = $booking->get_fixed_block();

		$selected_duration = $selected_duration_time = '';

		if ( 'duration_time' === $booking_type ) {
			$selected_duration      = $booking->get_selected_duration();
			$selected_duration_time = $booking->get_selected_duration_time();
		}

		wp_register_script( 'bkap-edit-post', bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-edit-booking-post.js', BKAP_FILE ), '', $plugin_version_number, false );
		wp_localize_script(
			'bkap-edit-post',
			'edit_post_param',
			array(
				'post_id'         => $post->ID,
				'ajax_url'        => $ajax_url,
				'order_url'       => $order_url,
				'confirm_msg'     => __( 'Are you sure you want to trash the booking?', 'woocommerce-booking' ),
				'booking_type'    => $booking_type,
				'hidden_date'     => $hidden_date,
				'hidden_checkout' => $hidden_checkout,
				'pastCheckout'    => $past_checkout,
				'time_slot'       => trim( $time_display ),
				'duration'        => $selected_duration,
				'duration_time'   => $selected_duration_time,
				'variation_id'    => $booking->get_variation_id(),
				'block_value'     => $block_value,
				'resource'        => $resource,
				'persons'         => $persons
			)
		);
		wp_enqueue_script( 'bkap-edit-post' );
	}
}
return new BKAP_Details_Meta_Box();
