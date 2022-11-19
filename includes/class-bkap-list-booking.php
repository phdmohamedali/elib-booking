<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Block Listing Bookings via block and shortcode
 *
 * @author      Tyche Softwares
 * @package     BKAP/List-Booking
 * @category    Classes
 */

if ( ! class_exists( 'BKAP_List_Booking' ) ) {

	/**
	 * Booking & Appointment Plugin for WooCommerce BKAP_Google_Events_View.
	 *
	 * Displaying bookables.
	 *
	 * @class    BKAP_list_booking
	 * @since    5.0.0
	 * @category Class
	 * @author   Tyche Softwares
	 */
	class BKAP_List_Booking {
		/**
		 * Default constructor.
		 *
		 * @since 5.0.0
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_block' ) );
			add_action( 'wp_loaded', array( $this, 'events_json' ) );
			add_action( 'bkap_booking_after_add_to_cart_end', array( $this, 'register_assets' ), 50 );
			add_filter( 'register_taxonomy_args', array( $this, 'enable_taxonomy_api' ), 10, 2 );
			add_filter( 'rest_product_query', array( $this, 'enable_product_metaquery' ), 10, 2 );
			add_shortcode( 'tyche-bookings', array( $this, 'shortcode' ) );
			add_action( 'init', array( $this, 'bkap_set_script_translations' ) );

			// Adding Price info to front end view.
			add_filter( 'bkap_date_lockout_data', array( $this, 'bkap_date_lockout_data_callback' ), 10, 6 );
			add_action( 'wp_enqueue_scripts', array( &$this, 'bkap_front_end_block_assets' ), 10 );
		}

		/**
		 * Calculating the price for adding as meta in the list view.
		 *
		 * @return array
		 */
		public function bkap_date_lockout_data_callback( $data, $product, $product_id, $variation_id, $resource_id, $date_check_in ) {

			if ( isset( $_POST['cal_price'] ) && 'true' === $_POST['cal_price'] ) {

				$product_type    = $product->get_type();
				$global_settings = bkap_global_setting();

				if ( isset( $_POST['timeslot_value'] ) ) { // 'Fixed Time'
					$_POST['special_booking_price'] = bkap_special_booking_price::get_price( $product_id, $date_check_in );
					$price                          = bkap_timeslot_price::get_price( $product_id, $variation_id, $product_type, $date_check_in, $_POST['timeslot_value'], 'product', $global_settings );
				} else { // 'Single Day'
					$price = bkap_special_booking_price::get_price( $product_id, $date_check_in );
					if ( '' == $price ) {
						$price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $date_check_in );
					}
				}

				if ( $resource_id != 0 ) {
					$resource       = new BKAP_Product_Resource( $resource_id, $product_id );
					$resource_price = $resource->get_base_cost();
					$price         += $resource_price;
				}
				$data['price'] = $price;
			}

			return $data;
		}

		/**
		 * Tells WordPress that JavaScript contains translations
		 *
		 * @return void
		 */
		public function bkap_set_script_translations() {

			global $wp_version;

			if ( version_compare( $wp_version, '5.0', '>=' ) ) {
				// WordPress version is greater than equals to 5.0
				wp_set_script_translations( 'tyche-bookable-listing', 'woocommerce-booking' );
				wp_set_script_translations( 'tyche-list-booking-editor', 'woocommerce-booking' );
			}
		}

		/**
		 * Register block
		 *
		 * @return void
		 */
		public function register_block() {

			// Attributes.
			$block_attributes = array(
				'view'                => array(
					'type'    => 'string',
					'default' => 'list',
				),
				'filter'              => array(
					'type'    => 'object',
					'default' => array(
						'value' => 'all',
						'label' => 'All Products',
					),
				),
				'type'                => array(
					'type'    => 'string',
					'default' => 'day',
				),
				'dayType'             => array(
					'type'    => 'string',
					'default' => 'only_day',
				),
				'timeType'            => array(
					'type'    => 'string',
					'default' => 'date_time',
				),
				'multipleType'        => array(
					'type'    => 'string',
					'default' => 'multidates',
				),
				'products'            => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'categories'          => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'resources'           => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'duration'            => array(
					'type'    => 'string',
					'default' => 'month',
				),
				'showQuantity'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTimes'           => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showNavigation'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sortingSingleEvents' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'greyOutBooked'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
			);

			$this->bkap_block_assets();

			// Block.
			if ( function_exists( 'register_block_type' ) ) {
				$tyche_styles = array(
					'render_callback' => array( $this, 'render' ),
					'attributes'      => $block_attributes,
				);

				if ( is_admin() ) {
					$tyche_styles['script']        = 'tyche-bookable-listing';
					$tyche_styles['editor_script'] = 'tyche-list-booking-editor';
					$tyche_styles['style']         = 'tyche-list-booking';
					$tyche_styles['editor_style']  = 'tyche-list-booking-editor';
				}
				register_block_type(
					'tyche/list-booking',
					$tyche_styles
				);
			}
		}

		/**
		 * Registering block assets
		 *
		 * @return void
		 */
		public function bkap_block_assets() {

			if ( ! bkap_check_woo_installed() ) {
				return;
			}

			// Stop execution if build asset folder is not available. Why? Some users who use our plugin do not have the build folder on their plugin installation and this throws an error when they are viewing the product page.
			if ( ! file_exists( BKAP_PLUGIN_PATH . '/build/index.asset.php' ) ) {
				return;
			}

			// Assets.
			$asset_file = include BKAP_PLUGIN_PATH . '/build/index.asset.php';

			// Scripts.
			wp_register_script(
				'tyche-moment',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/moment.min.js', BKAP_FILE ),
				array(),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-rrule',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/rrule.min.js', BKAP_FILE ),
				array(),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-fullcalendar',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/fullcalendar/fullcalendar-core.min.js', BKAP_FILE, true ),
				array( 'tyche-moment', 'tyche-rrule' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-fullcalendar-rrule',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/fullcalendar/fullcalendar-rrule.min.js', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-fullcalendar-moment',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/fullcalendar/fullcalendar-moment.min.js', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-fullcalendar-daygrid',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/fullcalendar/fullcalendar-daygrid.min.js', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-fullcalendar-daylist',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/fullcalendar/fullcalendar-list.min.js', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'accounting',
				WC()->plugin_url() . '/assets/js/accounting/accounting.min.js',
				array( 'jquery' ),
				$asset_file['version'],
				false
			);

			wp_register_script(
				'tyche-bookable-listing',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/booking-listing.js', BKAP_FILE ),
				array(
					'jquery',
					'tyche-fullcalendar',
					'tyche-fullcalendar-moment',
					'tyche-fullcalendar-rrule',
					'tyche-fullcalendar-daygrid',
					'tyche-fullcalendar-daylist',
					'accounting',
					'wp-blocks',
					'wp-element',
					'wp-i18n',
				),
				$asset_file['version'],
				false
			);

			$global_settings = bkap_global_setting();

			$bkap_data = apply_filters(
				'bkap_list_booking_bkap_data',
				array(
					'is_admin'           => is_admin(),
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'plugin_url'         => plugin_dir_url( BKAP_FILE ),
					'add_to_cart'        => get_option( 'bkap_add_to_cart' ),
					'resource'           => __( 'Resources', 'woocommerce-booking' ),
					'available_slots'    => __( 'Available Slots', 'woocommerce-booking' ),
					'price'              => __( 'Price', 'woocommerce-booking' ),
					'sold_out'           => __( 'Sold Out!', 'woocommerce-booking' ),
					'select_option'      => __( 'Select Options', 'woocommerce-booking' ),
					'no_bookable_slot'   => __( 'No Bookable Slots To Display', 'woocommerce-booking' ),
					'full_day_text'      => __( 'Full Day', 'woocommerce-booking' ),
					'first_day'          => (int) $global_settings->booking_calendar_day,
					'lang'               => $global_settings->booking_language,
					'bkap_currency_args' => wc_currency_arguments(),
				)
			);

			wp_localize_script(
				'tyche-bookable-listing',
				'bkap_data',
				$bkap_data
			);

			wp_register_script(
				'tyche-list-booking-editor',
				BKAP_PLUGIN_URL . '/build/index.js',
				$asset_file['dependencies'],
				$asset_file['version'],
				true
			);

			// Styles.
			wp_register_style(
				'tyche-fullcalendar',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/fullcalendar/fullcalendar-core.min.css', BKAP_FILE, true ),
				array(),
				$asset_file['version']
			);

			wp_register_style(
				'tyche-fullcalendar-daygrid',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/fullcalendar/fullcalendar-daygrid.min.css', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version']
			);

			wp_register_style(
				'tyche-fullcalendar-list',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/fullcalendar/fullcalendar-list.min.css', BKAP_FILE, true ),
				array( 'tyche-fullcalendar' ),
				$asset_file['version']
			);

			wp_register_style(
				'tyche-list-booking',
				bkap_load_scripts_class::bkap_asset_url( '/build/style.css', BKAP_FILE, false, false ),
				array( 'tyche-fullcalendar', 'tyche-fullcalendar-daygrid', 'tyche-fullcalendar-list' ),
				$asset_file['version']
			);

			wp_register_style(
				'tyche-list-booking-editor',
				bkap_load_scripts_class::bkap_asset_url( '/build/editor.css', BKAP_FILE, false, false ),
				array(),
				$asset_file['version']
			);
		}

		/**
		 * Enqueue block assets on front end.
		 *
		 * @return void
		 */
		public function bkap_front_end_block_assets() {
			if ( has_block( 'tyche/list-booking' ) || is_archive() ) {
				$this->bkap_block_assets();
				wp_enqueue_script( 'tyche-bookable-listing' );
				wp_enqueue_style( 'tyche-list-booking' );
			}
		}

		/**
		 * Register js/css assets
		 *
		 * @return void
		 */
		public function register_assets() {

			// Stop execution if build asset folder is not available. Why? Some users who use our plugin do not have the build folder on their plugin installation and this throws an error when they are viewing the product page.
			if ( ! file_exists( BKAP_PLUGIN_PATH . '/build/index.asset.php' ) ) {
				return;
			}

			$asset_file = include BKAP_PLUGIN_PATH . '/build/index.asset.php';

			wp_register_script(
				'tyche-bookable',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/bookable.js', BKAP_FILE ),
				array( 'jquery', 'tyche-moment' ),
				$asset_file['version'],
				true
			);

			if ( is_product() ) {
				wp_enqueue_script( 'tyche-bookable' );
			}
		}

		/**
		 * Add REST API support to product_cat.
		 *
		 * @param array  $args arguments.
		 * @param string $taxonomy_name taxonomy name.
		 *
		 * @return array args
		 */
		public function enable_taxonomy_api( $args, $taxonomy_name ) {

			if ( 'product_cat' === $taxonomy_name ) {
				$args['show_in_rest'] = true;
			}

			return $args;
		}

		/**
		 * Filter products rest api based on meta.
		 *
		 * @param array  $args arguments.
		 * @param object $request request object.
		 *
		 * @return array args
		 */
		public function enable_product_metaquery( $args, $request ) {
			$meta_key = $request->get_param( 'metaKey' );

			if ( $meta_key ) {
				$args['meta_key']   = $meta_key;
				$args['meta_value'] = $request->get_param( 'metaValue' );
			}

			return $args;
		}

		/**
		 * Render bookings
		 *
		 * @return void
		 */
		public function render( $attributes ) {
			// Config.
			$id            = uniqid( 'bkap_' );
			$view          = ( 'list' === $attributes['view'] ? 'list' : 'dayGrid' );
			$view_duration = ucfirst( $attributes['duration'] );
			$default_view  = "${view}${view_duration}";
			$with_nav      = array(
				'left'   => 'title',
				'center' => '',
				'right'  => 'prev,next',
			);
			$no_nav        = array(
				'left'   => 'title',
				'center' => '',
				'right'  => '',
			);
			$header        = $attributes['showNavigation'] ? $with_nav : $no_nav;

			// Note: Fix for fullcalendar extraParams not passing object properly.
			$attributes['filter']     = ! empty( $attributes['filter'] ) ? bkap_common::get_attribute_value( $attributes['filter'] ) : '';
			$attributes['products']   = ! empty( $attributes['products'] ) ? bkap_common::get_attribute_value( $attributes['products'] ) : '';
			$attributes['categories'] = ! empty( $attributes['categories'] ) ? bkap_common::get_attribute_value( $attributes['categories'] ) : '';
			$attributes['resources']  = ! empty( $attributes['resources'] ) ? bkap_common::get_attribute_value( $attributes['resources'] ) : '';

			// View.
			ob_start();
			include BKAP_PLUGIN_PATH . '/templates/listing/view.php';
			$html = ob_get_contents();
			ob_end_clean();

			return $html;
		}

		/**
		 * Register Listing Shortcode
		 *
		 * @param array $attributes attributes.
		 *
		 * @return string
		 */
		public function shortcode( $attributes ) {
			// Assets.
			wp_enqueue_script( 'tyche-bookable-listing' );
			wp_enqueue_style( 'tyche-list-booking' );

			// Below checks are for adding value in respective camelcase. This need to be removed after improvement in future release.
			if ( isset( $attributes['daytype'] ) ) {
				$attributes['dayType'] = $attributes['daytype'];
				unset( $attributes['daytype'] );
			}

			if ( isset( $attributes['timetype'] ) ) {
				$attributes['timeType'] = $attributes['timetype'];
				unset( $attributes['timetype'] );
			}

			if ( isset( $attributes['multipletype'] ) ) {
				$attributes['multipleType'] = $attributes['multipletype'];
				unset( $attributes['multipletype'] );
			}

			if ( isset( $attributes['showquantity'] ) ) {
				$attributes['showQuantity'] = $attributes['showquantity'];
				unset( $attributes['showquantity'] );
			}

			if ( isset( $attributes['showtimes'] ) ) {
				$attributes['showTimes'] = $attributes['showtimes'];
				unset( $attributes['showtimes'] );
			}

			if ( isset( $attributes['shownavigation'] ) ) {
				$attributes['showNavigation'] = $attributes['shownavigation'];
				unset( $attributes['shownavigation'] );
			}

			if ( isset( $attributes['sortingsingleevents'] ) ) {
				$attributes['sortingSingleEvents'] = $attributes['sortingsingleevents'];
				unset( $attributes['sortingsingleevents'] );
			}

			if ( isset( $attributes['greyoutbooked'] ) ) {
				$attributes['greyOutBooked'] = $attributes['greyoutbooked'];
				unset( $attributes['greyoutbooked'] );
			}

			// Attributes.
			$attributes = shortcode_atts(
				array(
					'view'                => 'list',
					'filter'              => 'all',
					'type'                => 'day',
					'dayType'             => 'only_day',
					'timeType'            => 'date_time',
					'multipleType'        => 'multidates',
					'products'            => '',
					'categories'          => '',
					'resources'           => '',
					'duration'            => 'month',
					'showQuantity'        => 'true',
					'showTimes'           => 'true',
					'showNavigation'      => 'true',
					'sortingSingleEvents' => 'true',
					'greyOutBooked'       => 'true',
				),
				$attributes,
				'tyche-bookings'
			);

			// Output.
			return $this->render( $attributes );
		}

		/**
		 * Get events json for fullcalendar
		 *
		 * @return string
		 */
		public function events_json() {
			if ( empty( $_REQUEST['bkap_events_feed'] ) ) {
				return;
			}

			$args                = $_POST;
			$args['numberposts'] = -1;
			$args['bkap_view']   = ! empty( $_REQUEST['bkap_view'] ) ? $_REQUEST['bkap_view'] : 'list';

			$events = BKAP_Bookable_Query::get_events( $args );
			wp_send_json( $events );
		}

	}

	return new BKAP_List_Booking();
}
