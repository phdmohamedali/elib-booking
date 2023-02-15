<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * BKAP Product Settings Class.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Product
 * @category    Classes
 * @since       5.15.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_Product' ) ) {

	/**
	 * BKAP Product Settings.
	 *
	 * @since 5.15.0
	 */
	class BKAP_Product {

		/**
		 * Initializes the BKAP_Product class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 5.15.0
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new self();
			}

			return $instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 5.14.0
		 */
		public function __construct() {
			add_filter( 'woocommerce_is_purchasable', array( &$this, 'set_bookable_products_as_purchasable' ), PHP_INT_MAX, 2 );
		}

		/**
		 * Sets the bookable Product as purchasable when a price has not been set for the Product.
		 *
		 * @param bool   $status Product Purchasable Status.
		 * @param object $product Product Object.
		 * @since 5.15.0
		 */
		public static function set_bookable_products_as_purchasable( $status, $product ) {

			// Proceed only when the product is not purchasable.
			if ( ! $status ) {
				$product_id = bkap_common::bkap_get_product_id( $product->get_id() );
				return self::is_booking_price_set( $product_id );
			}

			return $status;
		}

		/**
		 * Check if Booking Price has been set for the Bookable Product.
		 *
		 * @param int $product_id Product ID.
		 * @return bool Returns false if no price is found.
		 * @since 5.15.0
		 */
		public static function is_booking_price_set( $product_id ) {

			// Skip if Product is not bookable.
			if ( ! bkap_common::bkap_get_bookable_status( $product_id ) ) {
				return false;
			}

			$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );

			// Persons.
			if ( isset( $booking_settings['bkap_person'] ) && 'on' === $booking_settings['bkap_person'] ) {

				$person_ids  = $booking_settings['bkap_person_ids]'];
				$person_data = $booking_settings['bkap_person_data'];

				if ( is_array( $person_ids ) && count( $person_ids ) > 0 && is_array( $person_data ) && count( $person_data ) > 0 ) {
					foreach ( $person_ids as $person_id ) {
						if (
							isset( $person_data[ $person_id ] ) &&
							'' !== $person_data[ $person_id ] &&
							isset( $person_data[ $person_id ]['base_cost'] ) &&
							'' !== $person_data[ $person_id ]['base_cost'] &&
							(float) ( $person_data[ $person_id ]['base_cost'] ) > 0
						) {
							return true;
						}
					}
				}
			}

			// Resources.
			if ( isset( $booking_settings['_bkap_resource'] ) && 'on' === $booking_settings['_bkap_resource'] ) {

				$resource_products   = $booking_settings['_bkap_product_resources'];
				$resource_base_costs = $booking_settings['_bkap_resource'];

				if ( is_array( $resource_products ) && count( $resource_products ) > 0 && is_array( $resource_base_costs ) && count( $resource_base_costs ) > 0 ) {
					foreach ( $resource_products as $resource ) {
						if (
							isset( $resource_base_costs[ $resource ] ) &&
							'' !== $resource_base_costs[ $resource ] &&
							(float) ( $resource_base_costs[ $resource ] ) > 0
						) {
							return true;
						}
					}
				}
			}

			// Special Prices.
			$special_prices = get_post_meta( $product_id, '_bkap_special_price', true );
			if ( '' !== $special_prices && is_array( $special_prices ) && count( $special_prices ) > 0 ) {

				foreach ( $special_prices as $value ) {
					if (
						( ( isset( $value['booking_special_weekday'] ) && '' !== $value['booking_special_weekday'] ) || ( isset( $value['booking_special_date'] ) && '' !== $value['booking_special_date'] ) ) &&
						( isset( $value['booking_special_price'] ) && '' !== $value['booking_special_price'] ) &&
						(float) ( $value['booking_special_price'] ) > 0
					) {
						return true;
					}
				}
			}

			// Block Prices apply to only Multiple Night Booking Types.
			if ( 'multiple_days' === $booking_type ) {

				// Block Pricing - Fixed.
				$block_pricing_fixed = get_post_meta( $product_id, '_bkap_fixed_blocks', true );
				if ( 'booking_fixed_block_enable' === $block_pricing_fixed ) {

					$block_prices = get_post_meta( $product_id, '_bkap_fixed_blocks_data', true );
					if ( '' !== $block_prices && is_array( $block_prices ) && count( $block_prices ) > 0 ) {

						foreach ( $block_prices as $value ) {
							if (
								isset( $value['price'] ) &&
								'' !== $value['price'] &&
								(float) ( $value['price'] ) > 0
							) {
								return true;
							}
						}
					}
				}

				// Block Pricing - Range.
				$block_pricing_range = get_post_meta( $product_id, '_bkap_price_ranges', true );
				if ( 'booking_block_price_enable' === $block_pricing_range ) {

					$range_prices = get_post_meta( $product_id, '_bkap_price_range_data', true );
					if ( '' !== $range_prices && is_array( $range_prices ) && count( $range_prices ) > 0 ) {

						foreach ( $range_prices as $value ) {
							if (
								( isset( $value['per_day_price'] ) && '' !== $value['per_day_price'] && (float) ( $value['per_day_price'] ) > 0 ) ||
								( isset( $value['fixed_price'] ) && '' !== $value['fixed_price'] && (float) ( $value['fixed_price'] ) > 0 )
							) {
								return true;
							}
						}
					}
				}
			}

			// Timeslots apply to only Fixed Time & Dates/Fixed Time Booking Types.
			if ( 'date_time' === $booking_type || 'multidates_fixedtime' === $booking_type ) {
				$time_settings = get_post_meta( $product_id, '_bkap_time_settings', true );
				if ( '' !== $time_settings && is_array( $time_settings ) && count( $time_settings ) > 0 ) {

					return array_walk(
						$time_settings,
						function( $value, $key ) {
							foreach ( $value as $setting ) {
								if (
									isset( $setting['slot_price'] ) &&
									'' !== $setting['slot_price'] &&
									(float) ( $setting['slot_price'] ) > 0
								) {
									return true;
								}
							}
						}
					);
				}
			}

			// Duration Based Time.
			if ( 'duration_time' === $booking_type ) {
				$duration_time_settings = get_post_meta( $product_id, '_bkap_duration_settings', true );

				if (
					'' !== $duration_time_settings &&
					is_array( $duration_time_settings ) &&
					count( $duration_time_settings ) > 0 &&
					isset( $duration_time_settings['duration_price'] ) &&
					'' !== $duration_time_settings['duration_price'] &&
					(float) ( $duration_time_settings['duration_price'] ) > 0
				) {
					return true;
				}
			}

			return false;
		}
	}
}

/**
 * Returns a single instance of the class.
 *
 * @since 5.15.0
 * @return object
 */
function bkap_product() {
	return BKAP_Product::init();
}

bkap_product();
