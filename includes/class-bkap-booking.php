<?php 
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for booking posts, this handles all the data
 *
 * @author   Tyche Softwares
 * @package  BKAP/Core
 * @category Classes
 * @class    BKAP_Booking
 */

class BKAP_Booking {

	/** @public int */
	public $id;

	/** @public string */
	public $booking_date;

	/** @public string */
	public $start;

	/** @public string */
	public $end;

	/** @public bool */
	public $all_day;

	/** @public string */
	public $modified_date;

	/** @public object */
	public $post;

	/** @public int */
	public $product_id;

	/** @public object */
	public $product;

	/** @public int */
	public $order_id;

	/** @public object */
	public $order;

	/** @public int */
	public $customer_id;

	/** @public string */
	public $status;

	/** @public string */
	public $gcal_event_uid;
	
	/** @public array - contains all post meta values for this booking */
	public $custom_fields;

	/** @public bool */
	public $populated;

	/** @private array - used to temporarily hold order data for new bookings */
	private $order_data;

	/**
	 * Constructor, possibly sets up with post or id belonging to existing booking
	 * or supplied with an array to construct a new booking
	 *
	 * @param int/array/obj $booking_data
	 * @since 4.1.0
	 */
	public function __construct( $booking_data = false ) {
		$populated = false;

		if ( is_array( $booking_data ) ) {
			$this->order_data = $booking_data;
			$populated        = false;
		} elseif ( is_object( $booking_data ) && isset( $booking_data->ID ) ) {
			$this->post = $booking_data;
			$populated  = $this->populate_data( $booking_data->ID );
		} elseif ( is_int( intval( $booking_data ) ) && 0 < $booking_data ) {
			$populated = $this->populate_data( $booking_data );
		}

		$this->populated = $populated;
	}

	/**
	 * Populate the data with the id of the booking provided.
	 * Will query for the post belonging to this booking and store it
	 *
	 * @param integer $booking_id - Booking Post ID
	 * @return boolean True for success, else False
	 *
	 * @since 4.1.0
	 */
	public function populate_data( $booking_id ) {
		if ( ! isset( $this->post ) ) {
			$post = get_post( $booking_id );
		} else {
			$post = $this->post;
		}

		if ( is_object( $post ) ) {
			// We have the post object belonging to this booking, now let's populate
			$this->id            = $post->ID;
			$this->booking_date  = $post->post_date;
			$this->modified_date = $post->post_modified;
			$this->customer_id   = $post->post_author;
			$this->custom_fields = get_post_meta( $this->id );
			$this->status        = $post->post_status;
			$this->order_id      = $post->post_parent;

			// Define the data we're going to load: Key => Default value
			$load_data = array(
				'product_id'     => '',
				'qty'            => 1,
				'resource_id'    => '',
				'fixed_block'    => '',
				'persons'        => array(),
				'cost'           => '',
				'start'          => '',
				'customer_id'    => '',
				'end'            => '',
				'all_day'        => 0,
				'parent_id'      => 0,
				'variation_id'   => 0,
				'gcal_event_uid' => false,
			);

			// Load the data from the custom fields (with prefix for this plugin)
			$meta_prefix = '_bkap_';

			foreach ( $load_data as $key => $default ) {
				if ( isset( $this->custom_fields[ $meta_prefix . $key ][0] ) && $this->custom_fields[ $meta_prefix . $key ][0] !== '' ) {
					$this->$key = maybe_unserialize( $this->custom_fields[ $meta_prefix . $key ][0] );
				} else {
					$this->$key = $default;
				}
			}

			// Start and end date converted to timestamp
			$this->start = strtotime( $this->start );
			$this->end   = strtotime( $this->end );

			// Save the post object itself for future reference
			$this->post = $post;
			return true;
		}

		return false;
	}

	/**
	 * Create new booking post for the order.
	 *
	 * @param string - Status for new booking post
	 * @since 4.1.0
	 */
	public function create( $status = 'confirmed' ) {
		$this->new_booking( $status, $this->order_data );
	}

	/**
	 * Create Booking post

	 * @param string $status - The status for this new booking
	 * @param array  $order_data - Array with all the new booking data
	 * @since 4.1.0
	 */
	private function new_booking( $status, $order_data ) {
		global $wpdb;

		$order_data = wp_parse_args(
			$order_data,
			array(
				'user_id'         => 0,
				'resource_id'     => '',
				'fixed_block'     => '',
				'product_id'      => '',
				'order_item_id'   => '',
				'persons'         => array(),
				'cost'            => '',
				'start_date'      => '',
				'end_date'        => '',
				'all_day'         => 0,
				'parent_id'       => 0,
				'qty'             => 1,
				'variation_id'    => 0,
				'gcal_event_uid'  => false,
				'timezone_name'   => '',
				'timezone_offset' => '',
			)
		);

		$order_id = $order_data['parent_id'];

		$booking_data = array(
			'post_type'   => 'bkap_booking',
			'post_title'  => sprintf( __( 'Booking &ndash; %s', 'woocommerce-booking' ), strftime( _x( '%1$b %2$d, %Y @ %I:%M %p', 'Booking date parsed by strftime', 'woocommerce-booking' ) ) ),
			'post_status' => $status,
			'ping_status' => 'closed',
			'post_parent' => $order_id,
		);

		$this->id = wp_insert_post( $booking_data );

		// Setup the required data for the current user
		if ( ! $order_data['user_id'] ) {
			if ( is_user_logged_in() ) {
				$order_data['user_id'] = get_current_user_id();
			} else {
				$order_data['user_id'] = 0;
			}
		}

		$meta_args = array(
			'_bkap_order_item_id'   => $order_data['order_item_id'],
			'_bkap_product_id'      => $order_data['product_id'],
			'_bkap_resource_id'     => $order_data['resource_id'],
			'_bkap_fixed_block'     => $order_data['fixed_block'],
			'_bkap_persons'         => $order_data['persons'],
			'_bkap_cost'            => $order_data['cost'],
			'_bkap_start'           => $order_data['start'],
			'_bkap_end'             => $order_data['end'],
			'_bkap_all_day'         => intval( $order_data['all_day'] ),
			'_bkap_parent_id'       => $order_data['parent_id'],
			'_bkap_customer_id'     => $order_data['user_id'],
			'_bkap_qty'             => $order_data['qty'],
			'_bkap_variation_id'    => $order_data['variation_id'],
			'_bkap_gcal_event_uid'  => $order_data['gcal_event_uid'],
			'_bkap_duration'        => $order_data['duration'],
			'_bkap_timezone_name'   => $order_data['timezone_name'],
			'_bkap_timezone_offset' => $order_data['timezone_offset'],
		);

		foreach ( $meta_args as $key => $value ) {
			update_post_meta( $this->id, $key, $value );
		}

		do_action( 'bkap_new_booking', $this->id );
	}

	/**
	 * Returns the id of this booking
	 *
	 * @return Id of the booking or false if booking is not populated
	 * @since 4.1.0
	 */
	public function get_id() {
		if ( $this->populated ) {
			return $this->id;
		}

		return false;
	}

	/**
	 * Returns the status of this booking
	 *
	 * @param Bool to ask for pretty status name (if false)
	 * @return String of the booking status
	 * @since 4.1.0
	 */
	public function get_status( $raw = true ) {
		if ( $this->populated ) {
			if ( $raw ) {
				return $this->status;
			} else {
				$status_object = get_post_status_object( $this->status );
				return $status_object->label;
			}
		}

		return false;
	}

	/**
	 * Set the new status for this booking
	 *
	 * @param string $status - New Status
	 * @return boolean - True for success, else False
	 * @since 4.1.0
	 */
	public function update_status( $status ) {
		$current_status   = $this->get_status( true );
		$allowed_statuses = bkap_common::get_bkap_booking_statuses();

		if ( $this->populated ) {

			if ( array_key_exists( $status, $allowed_statuses ) ) {

				wp_update_post(
					array(
						'ID'          => $this->id,
						'post_status' => $status,
					)
				);

				// Trigger actions
				do_action( 'bkap_post_' . $current_status . '_to_' . $status, $this->id );
				do_action( 'bkap_post_' . $status, $this->id );

				// Note in the order
				if ( $order = $this->get_order() ) {
					$order->add_order_note( sprintf( __( 'Booking #%1$d status changed from "%2$s" to "%3$s"', 'woocommerce-booking' ), $this->id, $current_status, $status ) );
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the object of the order corresponding to this booking
	 *
	 * @return Order object or false if booking is not populated
	 * @since 4.1.0
	 */
	public function get_order() {
		
		if ( empty( $this->order ) ) {
			if ( $this->populated && ! empty( $this->order_id ) ) {
				$this->order = wc_get_order( $this->order_id );
			} else {
				return false;
			}
		}

		return $this->order;
	}


	/**
	 * Returns the Customer ID
	 *
	 * @return integer Customer ID
	 * @since 4.1.0
	 */
	public function get_customer_id() {

		if ( ! empty( $this->customer_id ) ) {
			return $this->customer_id;
		} else {
			return false;
		}
	}

	/**
	 * Returns the Order ID
	 *
	 * @return integer Order ID
	 * @since 4.1.0
	 */
	public function get_order_id() {

		if ( empty( $this->order_id ) ) {
			if ( ! empty( $this->order ) ) {
				$order_id = $this->order->get_id();
				return $order_id;
			}
		} else {
			return $this->order_id;
		}
	}

	/**
	 * Returns the Product ID
	 *
	 * @return Product ID
	 * @since 4.1.0
	 */
	public function get_product_id() {

		if ( empty( $this->product_id ) ) {
			if ( ! empty( $this->product ) ) {
				return $this->product->id;
			}
		} else {
			return $this->product_id;
		}
	}

	/**
	 * Returns the Product Object
	 *
	 * @return Product Object
	 * @since 4.1.0
	 */
	public function get_product() {

		if ( empty( $this->product ) ) {
			if ( ! empty( $this->product_id ) ) {
				return wc_get_product( $this->product_id );
			}
		} else {
			return $this->product;
		}
	}

	/**
	 * Returns the Order Date
	 *
	 * @return Order Date
	 * @since 4.1.0
	 */
	public function get_date_created() {

		if ( ! empty( $this->order_id ) ) {
			$order_post = wc_get_order( $this->order_id );
			if ( $order_post ) {
				$order_date = ! is_null( $order_post->get_date_created() ) ? $order_post->get_date_created()->getOffsetTimestamp() : '';
				$order_date = date_i18n( 'Y-m-d H:i:s', $order_date );
			} else {
				$order_date = __( 'Order date not available', 'woocommerce-booking' );
			}

			return $order_date;
		}
	}

	/**
	 * Returns the Zoom Meeting Link.
	 *
	 * @return string $meeting_link Zoom Meeting Link.
	 * @since 5.2.0
	 */
	public function get_zoom_meeting_link() {
		return $meeting_link = get_post_meta( $this->id, '_bkap_zoom_meeting_link', true );
	}

	/**
	 * Returns the Zoom Meeting data.
	 *
	 * @return string $meeting_link Zoom Meeting data.
	 * @since 5.2.0
	 */
	public function get_zoom_meeting_data() {
		return $meeting_link = get_post_meta( $this->id, '_bkap_zoom_meeting_data', true );
	}

	/**
	 * Returns the Customer Object
	 *
	 * @return Customer Object
	 * @since 4.1.0
	 */
	public function get_customer() {
		$name    = '';
		$email   = '';
		$user_id = 0;

		if ( $order = $this->get_order() ) {

			$user_id    = is_callable( array( $order, 'get_customer_id' ) ) ? $order->get_customer_id() : $order->customer_user;
			$first_name = is_callable( array( $order, 'get_billing_first_name' ) ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name  = is_callable( array( $order, 'get_billing_last_name' ) ) ? $order->get_billing_last_name() : $order->billing_last_name;

			if ( $first_name == '' && $last_name == '' ) {
				$user_info = get_userdata( $user_id );
				if ( $user_info ) {
					$name = trim( $user_info->display_name );
				}
			} else {
				$name = trim( $first_name . ' ' . $last_name );
			}

			$email = is_callable( array( $order, 'get_billing_email' ) ) ? $order->get_billing_email() : $order->billing_email;
			$name  = 0 !== absint( $user_id ) ? $name : sprintf( _x( '%s (Guest)', 'Guest string with name from booking order in brackets', 'woocommerce-booking' ), $name );
		} elseif ( $this->get_customer_id() ) {
			$user    = get_user_by( 'id', $this->get_customer_id() );
			$name    = $user->display_name;
			$email   = $user->user_email;
			$user_id = $this->get_customer_id();
		}
		return (object) array(
			'name'    => $name,
			'email'   => $email,
			'user_id' => $user_id,
		);
	}

	/**
	 * Returns the Start Date
	 *
	 * @return Start Date as YmdHis
	 * @since 4.1.0
	 */
	function get_start() {
		return $start = get_post_meta( $this->id, '_bkap_start', true );
	}

	/**
	 * Returns the End Date
	 *
	 * @return End Date as YmdHis
	 * @since 4.1.0
	 */
	function get_end() {
		return $end = get_post_meta( $this->id, '_bkap_end', true );
	}

	/**
	 * Returns the Time Slot
	 *
	 * @return Time Slot
	 * @since 4.1.0
	 */
	function get_time() {
		$global_settings = bkap_global_setting();
		$time_format     = $global_settings->booking_time_format;

		// Commenting since we need 24 hour format for comparision
		// $time_format = ( $time_format === '12' ) ? 'h:i A' : 'H:i';
		$time_format = 'H:i';
		$start_time  = date( $time_format, strtotime( $this->get_start() ) );
		$end_time    = date( $time_format, strtotime( $this->get_end() ) );

		if ( $end_time === '' || $end_time === '00:00' ) {
			return $start_time;
		}
		return "$start_time - $end_time";
	}

	/**
	 * Returns the Start Date
	 *
	 * @return Date in the format set in Booking->Settings->Date Format
	 * @since 4.2.0
	 */
	function get_start_date( $global_settings = array() ) {

		$start           = $this->get_start();
		$date_formats    = bkap_get_book_arrays( 'bkap_date_formats' );
		$global_settings = empty( $global_settings ) ? bkap_global_setting() : $global_settings;
		$date_format_set = $date_formats[ $global_settings->booking_date_format ];
		return date( $date_format_set, strtotime( $start ) );

	}

	/**
	 * Returns the End Date
	 *
	 * @return Date in the format set in Booking->Settings->Date Format
	 * @since 4.2.0
	 */
	function get_end_date( $global_settings = array() ) {

		$end_date = '';
		$start    = $this->get_start();
		$end      = $this->get_end();

		if ( $start !== $end ) {
			$date_formats    = bkap_get_book_arrays( 'bkap_date_formats' );
			$global_settings = empty( $global_settings ) ? bkap_global_setting() : $global_settings;
			$date_format_set = $date_formats[ $global_settings->booking_date_format ];
			$end_date        = date( $date_format_set, strtotime( $end ) );
		}

		return $end_date;
	}

	/**
	 * Returns Start Time
	 *
	 * @return Start Time as per format set in Booking->Settings->Time Format
	 * @since 4.2.0
	 */
	function get_start_time( $global_settings = array() ) {

		$start_time      = '';
		$global_settings = empty( $global_settings ) ? bkap_global_setting() : $global_settings;
		$time_format     = $global_settings->booking_time_format;
		$time_format     = ( $time_format === '12' ) ? 'h:i A' : 'H:i';

		if ( '000000' !== substr( $this->get_start(), 8 ) ) {
			$start_time = date( $time_format, strtotime( $this->get_start() ) );
		}

		return $start_time;
	}

	/**
	 * Returns Start Time
	 *
	 * @return Start Time as per format set in Booking->Settings->Time Format
	 * @since 4.2.0
	 */
	function get_end_time( $global_settings = array() ) {

		$end_time        = '';
		$global_settings = empty( $global_settings ) ? bkap_global_setting() : $global_settings;
		$time_format     = $global_settings->booking_time_format;
		$time_format     = ( $time_format === '12' ) ? 'h:i A' : 'H:i';

		if ( '000000' !== substr( $this->get_end(), 8 ) ) {
			$end_time = date( $time_format, strtotime( $this->get_end() ) );
		}

		return $end_time;
	}

	/**
	 * Returns the Booked Quantity
	 *
	 * @return integer $quantity
	 * @since 4.2.0
	 */
	function get_quantity() {
		return get_post_meta( $this->id, '_bkap_qty', true );
	}

	/**
	 * Returns the Booked Cost
	 *
	 * @return integer $cost
	 * @since 4.2.0
	 */
	function get_cost() {
		return get_post_meta( $this->id, '_bkap_cost', true );
	}

	/**
	 * Returns the Variation ID
	 *
	 * @return integer $variation_id
	 * @since 4.4.0
	 */
	function get_variation_id() {
		return get_post_meta( $this->id, '_bkap_variation_id', true );
	}

	/**
	 * Returns the Item ID for the booked product
	 *
	 * @return integer $item_id
	 * @since 4.4.0
	 */
	function get_item_id() {
		return get_post_meta( $this->id, '_bkap_order_item_id', true );
	}

	/**
	 * Returns resource
	 *
	 * @return integer $resource_id - Resource used in the booking
	 * @since 4.6.0
	 */
	function get_resource() {
		$resource = get_post_meta( $this->id, '_bkap_resource_id', true );

		return $resource;
	}

	/**
	 * Returns resource title
	 *
	 * @return string $resource_title - Title of the resource being used in the booking.
	 * @since 4.6.0
	 */
	function get_resource_title() {
		$resource_id = get_post_meta( $this->id, '_bkap_resource_id', true );

		$resource_title = '';
		if ( $resource_id != '' ) {
			$resource_title = get_the_title( $resource_id );
		}

		return $resource_title;
	}

	/**
	 * Returns persons data
	 *
	 * @since 5.11.0
	 */
	function get_persons() {

		$person = get_post_meta( $this->id, '_bkap_persons', true );

		return $person;
	}

	/**
	 * Returns persons information
	 *
	 * @since 5.11.0
	 */
	function get_persons_info() {

		$person      = get_post_meta( $this->id, '_bkap_persons', true );
		$person_info = '';
		if ( ! empty( $person ) ) {
			if ( isset( $person[0] ) ) {
				$person_info = Class_Bkap_Product_Person::bkap_get_person_label() . ' : ' . $person[0];
			} else {
				
				foreach ( $person as $p_key => $p_value ) {
					$person_info .= get_the_title( $p_key ) . ' : ' . $p_value . ' | '; 
				}

				if ( '' !== $person_info ) {
					$person_info = substr( $person_info, 0, -3 );
				}
			}
		}

		return $person_info;
	}

	/**
	 * Returns fixed block
	 *
	 * @return integer $fixed_block - Selected Fixed Block data.
	 * @since 5.7.1
	 */
	function get_fixed_block() {
		$fixed_block = get_post_meta( $this->id, '_bkap_fixed_block', true );

		return $fixed_block;
	}

	/**
	 * Returns value for selected duration
	 *
	 * @return string $bkap_duration - value of the duration booked
	 * @since 4.10.0
	 */
	function get_selected_duration() {
		$duration = get_post_meta( $this->id, '_bkap_duration', true );

		$bkap_duration = '';
		if ( $duration != '' ) {
			if ( is_array( $duration ) ){
				$duration_value = $duration;
			} else {
				$duration_value = explode( '-', $duration );
			}

			$bkap_duration = $duration_value[0];
		}
		return $bkap_duration;
	}

	/**
	 * Returns value for selected duration
	 *
	 * @return string $bkap_duration - value of the duration booked
	 * @since 4.10.0
	 */
	function get_selected_duration_time() {

		$bkap_start    = get_post_meta( $this->id, '_bkap_start', true );
		$duration_time = date( 'H:i', strtotime( $bkap_start ) );

		return $duration_time;
	}

	/**
	 * Returns value for Timezone name
	 *
	 * @return string $timezone_name - value of the client's timezone name
	 * @since 4.10.0
	 */

	function get_timezone_name() {

		$timezone_name = get_post_meta( $this->id, '_bkap_timezone_name', true );
		return $timezone_name;
	}

	/**
	 * Returns value for Timezone offset
	 *
	 * @return string $bkap_duration - value of the client's timezone offset
	 * @since 4.10.0
	 */

	function get_timezone_offset() {

		$timezone_offset = get_post_meta( $this->id, '_bkap_timezone_offset', true );
		return $timezone_offset;
	}

	/**
	 * Returns vendor id.
	 *
	 * @return string $vendor_id - Vendor ID.
	 * @since 5.10.0
	 */
	function get_vendor_id(){

		$vendor_id = get_post_meta( $this->id, '_bkap_vendor_id', true );
		return $vendor_id;
	}
}

