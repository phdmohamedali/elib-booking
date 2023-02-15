<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Adding Plugin Meta
 *
 * @author   Tyche Softwares
 * @package  BKAP/Plugin-Meta
 * @category Classes
 * @class    Bkap_Plugin_Meta
 */

if ( ! class_exists( 'Bkap_Plugin_Meta' ) ) {

	/**
	 * Class Bkap_Plugin_Meta.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Plugin_Meta {

		/**
		 * Bkap_Plugin_Meta constructor.
		 */
		public function __construct() {
			// Add plugin doc and forum link in description.
			add_filter( 'plugin_row_meta', array( &$this, 'bkap_plugin_row_meta' ), 10, 2 );
			add_action( 'admin_head', array( $this, 'bkap_add_star_styles' ) );
			// Settings link on plugins page.
			add_filter( 'plugin_action_links_' . plugin_basename( BKAP_FILE ), array( &$this, 'bkap_plugin_settings_link' ) );
		}

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @param   mixed $links Plugin Row Meta.
		 * @param   mixed $file  Plugin Base file.
		 * @return  array Links to be added along with the plugin information.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_plugin_row_meta( $links, $file ) {
			$plugin_base_name = plugin_basename( BKAP_FILE );

			if ( $file === $plugin_base_name ) {

				$bkap_svg_str = "<svg xmlns='http://www.w3.org/2000/svg'
									width='15'
									height='15'
									viewBox='0 0 24 24'
									fill='none'
									stroke='currentColor'
									stroke-width='2'
									stroke-linecap='round'
									stroke-linejoin='round'
									class='feather feather-star'>
									<polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/>
								</svg>";

				$bkap_svg = '';

				for ( $i = 0; $i < 5; $i++ ) {
					$bkap_svg .= $bkap_svg_str;
				}

				$row_meta = array(
					'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_booking_and_appointment_docs_url', 'https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/' ) ) . '" title="' . esc_attr( __( 'View Booking & Appointment Plugin Documentation', 'woocommerce-booking' ) ) . '">' . __( 'Docs', 'woocommerce-booking' ) . '</a>',
					'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_booking_and_appointment_support_url', 'https://tychesoftwares.freshdesk.com/support/tickets/new' ) ) . '" title="' . esc_attr( __( 'Submit Ticket', 'woocommerce-booking' ) ) . '">' . __( 'Submit Ticket', 'woocommerce-booking' ) . '</a>',
					'stars'   => "<a href='https://www.tychesoftwares.com/submit-review/'
										target='_blank'
										title='" . __( 'Submit a review', 'woocommerce-booking' ) . "'>
										<i class='bkap-ml-stars'>
										" . $bkap_svg . '
										</i>
									</a>',
				);

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		/**
		 * Adds styles to admin head to allow for stars animation and coloring.
		 */
		public function bkap_add_star_styles() {

			global $pagenow;

			if ( 'plugins.php' === $pagenow ) {?>
				<style>
					.bkap-ml-stars{display:inline-block;color:#ffb900;position:relative;top:3px}
					.bkap-ml-stars svg{fill:#ffb900}
					.bkap-ml-stars svg:hover{fill:#ffb900}
					.bkap-ml-stars svg:hover ~ svg{fill:none}
				</style>
				<?php
			}
		}

		/**
		 * Settings link on Plugins page
		 *
		 * @access public
		 * @param  array $links Exisiting Links present on Plugins information section.
		 * @return array Modified array containing the settings link added.
		 *
		 * @since 1.7.0
		 */
		public function bkap_plugin_settings_link( $links ) {
			$setting_link['settings'] = '<a href="' . esc_url( get_admin_url( null, 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=settings' ) ) . '">Settings</a>';
			$links                    = $setting_link + $links;
			return $links;
		}
	}
	$bkap_plugin_meta = new Bkap_Plugin_Meta();
}
