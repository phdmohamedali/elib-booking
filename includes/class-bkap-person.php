<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Persons - Appearance and Calculations.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Persons
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class_Bkap_Product_Person class.
 *
 * @class Class_Bkap_Product_Person
 * @since 5.11.0
 */
class Class_Bkap_Product_Person {

	/**
	 * Holds product id.
	 *
	 * @var int
	 */

	private $product_id;

	/**
	 * Constructor. Reference to the Resource.
	 *
	 * @since 4.6.0
	 * @param integer $product_id Product ID.
	 */
	public function __construct( $product_id = 0 ) {

		if ( $product_id != 0 ) {
			$this->$product_id = $product_id;
		}

        // add the Person tab in the Booking meta box.
		add_action( 'bkap_add_tabs', array( &$this, 'bkap_person_tab' ), 12, 2 );

		// add fields in the Resource tab in the Booking meta box.
		add_action( 'bkap_after_listing_enabled', array( &$this, 'bkap_person_settings' ), 12, 2 );

		// Ajax.
		add_action( 'admin_init', array( &$this, 'bkap_load_person_ajax_admin' ) );

		// Adding person fields on front end product page.
		add_action( 'bkap_before_booking_form', array( &$this, 'bkap_front_end_person_field' ), 6, 2 );
	}

	/**
	 * Ajax loads
	 *
	 * @hook admin_init
	 * @since 5.11.0
	 */
	public function bkap_load_person_ajax_admin() {
		add_action( 'wp_ajax_bkap_add_person', array( &$this, 'bkap_add_person' ) );
		add_action( 'wp_ajax_bkap_delete_person', array( &$this, 'bkap_delete_person' ) );
	}

	/**
	 * Deleting the person
	 *
	 * @since 5.11.0
	 */
	public static function bkap_delete_person() {

		$product_id  = intval( $_POST['post_id'] );
		$person_id   = intval( $_POST['delete_person'] );

		if ( $person_id ) {

			$bkap_person_data = get_post_meta( $product_id, '_bkap_person_data', true );
			$bkap_person_ids  = get_post_meta( $product_id, '_bkap_person_ids', true );

			$bkap_setting = bkap_setting( $product_id );
			$person_data  = $bkap_setting['bkap_person_data'];
			$person_ids   = $bkap_setting['bkap_person_ids'];

			if ( $bkap_person_data != '' ) {

				if ( array_key_exists( $person_id, $bkap_person_data ) ) {
					unset( $bkap_person_data[ $person_id ] );
					unset( $person_data[ $person_id ] );
					$bkap_setting['bkap_person_data'] = $person_data;
					update_post_meta( $product_id, '_bkap_person_data', $bkap_person_data );
					update_post_meta( $product_id, 'woocommerce_booking_settings', $bkap_setting );
				}
			}

			if ( $bkap_person_ids != '' ) {

				if ( in_array( $person_id, $bkap_person_ids ) ) {
					$key = array_search( $person_id, $bkap_person_ids );
					unset( $bkap_person_ids[ $key ] );
					unset( $person_ids[ $key ] );
					$bkap_setting['bkap_person_ids'] = $person_ids;
					update_post_meta( $product_id, '_bkap_person_ids', $bkap_person_ids );
					update_post_meta( $product_id, 'woocommerce_booking_settings', $bkap_setting );
				}
			}
		} else {
			$bkap_setting['bkap_person_data'] = array();
			$bkap_setting['bkap_person_ids']  = array();
			update_post_meta( $product_id, 'woocommerce_booking_settings', $bkap_setting );
			update_post_meta( $product_id, '_bkap_person_data', '' );
			update_post_meta( $product_id, '_bkap_person_ids', '' );
		}

		die();
	}

	/**
	 * Save the resource.
	 *
	 * @since 5.11.0
	 */
	public static function bkap_add_person() {

		$post_id     = intval( $_POST['post_id'] );
		$loop        = intval( $_POST['loop'] );
		$person_name = wc_clean( $_POST['person_name'] );

		$add_person_id = BKAP_Person::bkap_create_person( $person_name );

		if ( $add_person_id ) {

			$person      = new BKAP_Person( $add_person_id );
			$person_data = array(
				'base_cost'   => 0,
				'block_cost'  => 0,
				'person_min'  => 0,
				'person_max'  => 1,
				'person_desc' => '',
			);

			ob_start();

			include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html-bkap-person.php';

			wp_send_json( array( 'html' => ob_get_clean() ) );
		}

		wp_send_json( array( 'error' => __( 'Unable to add person', 'woocommerce-booking' ) ) );
	}

	/**
	 * Adds the resources tab in Add/Edit Product page
	 *
	 * @param integer $product_id - Product ID.
	 * @param array   $booking_settings Booking Settings.
	 *
	 * @hook bkap_add_tabs
	 *
	 * @since 5.11.0
	 */
	public static function bkap_person_tab( $product_id, $booking_settings ) {

		$selected_value = 'display:none;';
		if ( 0 == $product_id ) {
			$type = 'simple';
		} else {
			$type = bkap_common::bkap_get_product_type( $product_id );
		}

		$person_compatibility   = array( 'simple', 'variable', 'subscription', 'variable-subscription' );
		$display                = in_array( $type, $person_compatibility, true ) ? true : false;

		if ( ( isset( $booking_settings['bkap_person'] ) && $booking_settings['bkap_person'] == 'on' ) && $display ) {
			$selected_value = '';
		}

		?>
		<li class="tstab-tab" data-link="bkap_person_settings_page">
			<a id="person_tab_settings" style="<?php echo $selected_value; ?>" class="bkap_tab"><i class="fa fa-users" aria-hidden="true"></i><?php esc_html_e( 'Persons', 'woocommerce-booking' ); ?></a>
		</li>
		<?php
	}

	/**
	 * Loads the content in the Person tab in Add/Edit product page.
	 *
	 * @param integer $product_id - Product ID.
	 * @param array   $booking_settings - Booking Settings.
	 *
	 * @hook bkap_after_listing_enabled
	 *
	 * @since 5.11.0
	 */
	public static function bkap_person_settings( $product_id, $booking_settings ) {
		global $post;

		$post_type = get_post_type( $product_id );
		$post_slug = isset( $post->post_name ) && $post_type === 'page' ? $post->post_name : '';

		// Settings.
		$min_person = 1;
		$max_person = 1;
		if ( isset( $booking_settings['bkap_min_person'] ) && '' !== $booking_settings['bkap_min_person'] ) {
			$min_person = intval( $booking_settings['bkap_min_person'] );
		}

		if ( isset( $booking_settings['bkap_max_person'] ) && '' !== $booking_settings['bkap_max_person'] ) {
			$max_person = intval( $booking_settings['bkap_max_person'] );
		}

		$bkap_price_per_person = '';
		if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' == $booking_settings['bkap_price_per_person'] ) {
			$bkap_price_per_person = 'checked';
		}

		$bkap_each_person_booking = '';
		if ( isset( $booking_settings['bkap_each_person_booking'] ) && 'on' == $booking_settings['bkap_each_person_booking'] ) {
			$bkap_each_person_booking = 'checked';
		}

		$bkap_person_type = '';
		$bkap_show_person_type = 'display:none;';
		if ( isset( $booking_settings['bkap_person_type'] ) && 'on' == $booking_settings['bkap_person_type'] ) {
			$bkap_person_type      = 'checked';
			$bkap_show_person_type = '';
		}

		// Setting Tips.
		$min_person_tip          = __( 'Total number of persons can not be lesser than this value.', 'woocommerce-booking' );
		$max_person_tip          = __( 'Total number of persons will not exceed this value.', 'woocommerce-booking' );
		$price_per_person_tip    = __( 'All the cost will be multiplied by the number of persons.', 'woocommerce-booking' );
		$each_person_booking_tip = __( 'Enable this to count each person as booking until the Max Bookings per block is reached.', 'woocommerce-booking' );
		$person_type_tip         = __( 'Enable this to add different types of persons and its costs, e.g Adults and Children.', 'woocommerce-booking' );

		?>
		<div id="bkap_person_settings_page" class="tstab-content" style="position: relative; display: none;">

			<table class='form-table bkap-form-table'>
				<tr>
					<th>
						<label for="bkap_min_person">
							<?php _e( 'Min Persons:', 'woocommerce-booking' ); ?>
						</label>
					</th>
					<td>
						<input type="number" id="bkap_min_person" name= "bkap_min_person" min="0" max="9999" value="<?php echo $min_person; ?>" />
					</td>
					<td>
						<?php echo bkap_help_tip_html( $min_person_tip ); ?>
					</td>
				</tr>

				<tr>
					<th>
						<label for="bkap_max_person">
							<?php _e( 'Max Persons:', 'woocommerce-booking' ); ?>
						</label>
					</th>
					<td>
						<input type="number" id="bkap_max_person" name= "bkap_max_person" min="0" max="9999" value="<?php echo $max_person; ?>" />
					</td>
					<td>
						<?php echo bkap_help_tip_html( $max_person_tip ); ?>
					</td>
				</tr>

				<tr>
					<th>
						<label for="bkap_price_per_person">
							<?php _e( 'Multiply price by persons count:', 'woocommerce-booking' ); ?>
						</label>
					</th>
					<td>
					<label class="bkap_switch">
							<input type="checkbox" name="bkap_price_per_person" id="bkap_price_per_person" <?php echo $bkap_price_per_person; ?>>
							<div class="bkap_slider round"></div>
						</label>
					</td>
					<td>
						<?php echo bkap_help_tip_html( $price_per_person_tip ); ?>
					</td>
				</tr>

				<tr>
					<th>
						<label for="bkap_each_person_booking">
							<?php _e( 'Consider each person as booking:', 'woocommerce-booking' ); ?>
						</label>
					</th>
					<td>
					<label class="bkap_switch">
							<input type="checkbox" name="bkap_each_person_booking" id="bkap_each_person_booking" <?php echo $bkap_each_person_booking; ?>>
							<div class="bkap_slider round"></div>
						</label>
					</td>
					<td>
						<?php echo bkap_help_tip_html( $each_person_booking_tip ); ?>
					</td>
				</tr>

				<tr>
					<th>
						<label for="bkap_person_type">
							<?php _e( 'Enable Person Type:', 'woocommerce-booking' ); ?>
						</label>
					</th>
					<td>
					<label class="bkap_switch">
							<input type="checkbox" name="bkap_person_type" id="bkap_person_type" <?php echo $bkap_person_type; ?>>
							<div class="bkap_slider round"></div>
						</label>
					</td>
					<td>
						<?php echo bkap_help_tip_html( $person_type_tip ); ?>
					</td>
				</tr>
			</table>

			<div id="bkap_person_type_section" style="<?php echo $bkap_show_person_type; ?>">
				<hr/>
				<h4><?php esc_html_e( 'Person Types', 'woocommerce-booking' ); ?></h4>

				<table class="bkap_person_info">
					<tr>
						<th width="25%"><?php echo __( 'Person Type', 'woocommerce-booking' ); ?></th>
						<th width="15%"><?php echo __( 'Base Cost', 'woocommerce-booking' ); ?></th>
						<th width="15%"><?php echo __( 'Block Cost', 'woocommerce-booking' ); ?></th>
						<th width="10%"><?php echo __( 'Minimum', 'woocommerce-booking' ); ?></th>
						<th width="10%"><?php echo __( 'Maximum', 'woocommerce-booking' ); ?></th>
						<th width="25%"><?php echo __( 'Description', 'woocommerce-booking' ); ?></th>
						<th id="bkap_remove_person" width="10%"><i class="fa fa-trash" aria-hidden="true"></i></th>
					</tr>
					<?php
					$loop = 0;
					$persons_of_product = isset( $booking_settings['bkap_person_data'] ) ? $booking_settings['bkap_person_data'] : array();/* self::get_persons( $product_id ); */
					if ( is_array( $persons_of_product ) && count( $persons_of_product ) > 0 ) {
						foreach ( $persons_of_product as $person_id => $person_data ) {

							if ( get_post_status( $person_id ) ) {
								$person           = new BKAP_Person( $person_id );
								include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html-bkap-person.php';
								$loop++;
							}
						}
					}
					?>

				</table>
				<div class="bkap_person_add_section">
					<button type="button" class="button button-primary bkap_add_person"><?php _e( 'Add Person Type', 'woocommerce-booking' ); ?></button>
				</div>
			</div>
				<hr />
				<?php
				if ( isset( $post_type ) && ( 'product' === $post_type || 'page' === $post_type && isset( $post_slug ) && 'store-manager' === $post_slug ) ) {
					bkap_booking_box_class::bkap_save_button( 'bkap_save_person' );
				}
				?>
			<div id='person_update_notification' style='display:none;'></div>
		</div>
		<?php
	}

	/**
	 * This function is used to create person fields in the Booking form.
	 *
	 * @param integer $product_id Product ID.
	 * @param array   $booking_settings - Booking Settings.
	 *
	 * @since 5.11.0
	 */
	public static function bkap_front_end_person_field( $product_id, $booking_settings ) {

		if ( ! isset( $booking_settings['bkap_person'] ) || ( isset( $booking_settings['bkap_person'] ) && 'on' !== $booking_settings['bkap_person'] ) ) {
			return;
		}

		$type                 = bkap_common::bkap_get_product_type( $product_id );
		$person_compatibility = array( 'simple', 'subscription', 'variable', 'variable-subscription' );
		$display              = in_array( $type, $person_compatibility ) ? true : false;

		if ( ! $display ) {
			return;
		}

		?>
		<div class="bkap_persons_container">
		<?php
		if ( 'on' === $booking_settings['bkap_person_type'] && count( $booking_settings['bkap_person_data'] ) > 0 ) {

			$person_data = $booking_settings['bkap_person_data'];

			foreach ( $person_data as $key => $value ) {

				$min_person  = $value['person_min'];
				$max_person  = $value['person_max'];
				$person_desc = $value['person_desc'];
				?>
				<p class="bkap_field_persons" id="bkap_persons_type_<?php echo esc_attr( $key ); ?>">
					<label for="bkap_field_persons_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( get_the_title( $key ) ); ?>:</label>
					<input type="number" data-person-id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $min_person ); ?>" step="1" min="<?php echo esc_attr( $min_person ); ?>" max="<?php echo esc_attr( $max_person ); ?>" name="bkap_field_persons_<?php echo esc_attr( $key ); ?>" id="bkap_field_persons_<?php echo esc_attr( $key ); ?>"><span class="bkap_person_description"><?php echo esc_html( $person_desc ); ?></span>
				</p>
				<?php
			}

		} else {
			$min_person  = $booking_settings['bkap_min_person'];
			$max_person  = $booking_settings['bkap_max_person'];
			$person_desc = apply_filters( 'bkap_default_person_field_description', '', $product_id, $booking_settings );
			?>
			<p class="bkap_field_persons">
				<label for="bkap_field_persons"><?php echo self::bkap_get_person_label( $product_id ); ?></label>
				<input type="number" value="<?php echo esc_attr( $min_person ); ?>" step="1" min="<?php echo esc_attr( $min_person ); ?>" max="<?php echo esc_attr( $max_person ); ?>" name="bkap_field_persons" id="bkap_field_persons"><span class="bkap_person_description"><?php echo esc_html( $person_desc ); ?></span>
			</p>
		<?php
		}
		?></div><?php
	}

	/**
	 * Get Person Data.
	 *
	 * @param integer $product_id - Product ID.
	 * @return array
	 *
	 * @since 5.11.0
	 */
	public static function bkap_get_person_data( $product_id ) {

		$bkap_person_data = get_post_meta( $product_id, '_bkap_person_data', true );

		return $bkap_person_data;
	}

	/**
	 * Get Person Ids.
	 *
	 * @param integer $product_id - Product ID.
	 * @return array
	 * @since 5.11.0
	 */
	public static function bkap_get_person_ids( $product_id ) {

		$person_ids = get_post_meta( $product_id, '_bkap_person_ids', true );

		return apply_filters( 'bkap_person_ids', $person_ids, $product_id );
	}

	/**
	 * Get person lable.
	 *
	 * @param integer $product_id - Product ID
	 * @return string
	 *
	 * @since 5.11.0
	 */
	public static function bkap_get_person_label( $product_id = 0 ) {

		return apply_filters( 'bkap_persons_label', __( 'Person', 'woocommerce-booking' ), $product_id );
	}

	/**
	 * Get resource option.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 5.11.0
	 */
	public static function bkap_person( $product_id ) {

		$bkap_person = get_post_meta( $product_id, '_bkap_person', true );

		return $bkap_person;
	}

	/**
	 * Get person type.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 5.11.0
	 */
	public static function bkap_person_type( $product_id ) {

		$bkap_person = get_post_meta( $product_id, '_bkap_person_type', true );

		return $bkap_person;
	}

	/**
	 * Get status of resource whether the product has resource enable and any resources are added to it or not.
	 *
	 * @param integer $product_id - Product ID.
	 * @return bool true if resource is enabled and have atleast one resource else false
	 * @since 5.11.0
	 */
	public static function bkap_person_status( $product_id ) {

		$person     = self::bkap_person( $product_id );
		$person_ids = self::bkap_get_person_data( $product_id );
		$r_status   = false;

		if ( '' == $resource ) {
			return $r_status;
		} elseif ( '' != $resource && ! ( is_array( $resource_ids ) ) ) {
			return $r_status;
		} elseif ( '' != $resource && empty( $resource_ids ) ) {
			return $r_status;
		}

		return true;
	}
}

$class_bkap_product_person = new Class_Bkap_Product_Person();
?>
