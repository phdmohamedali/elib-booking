<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Customer Meta Box
 *
 * @author   Tyche Softwares
 * @package  BKAP/Meta-Boxes
 * @category Classes
 * @class    BKAP_Customer_Meta_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Customer_Meta_Box class.
 */
class BKAP_Customer_Meta_Box {

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
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'bkap-customer-data';
		$this->title      = __( 'Customer details', 'woocommerce-booking' );
		$this->context    = 'side';
		$this->priority   = 'default';
		$this->post_types = array( 'bkap_booking' );
	}

	/**
	 * Meta box content.
	 */
	public function meta_box_inner( $post ) {
		global $booking;

		if ( get_post_type( $post->ID ) === 'bkap_booking' ) {
			$booking = new BKAP_Booking( $post->ID );
		}
		$has_data = false;
		?>
		<table class="booking-customer-details">
			<?php
			if ( $booking->get_customer_id() && ( $user = get_user_by( 'id', $booking->get_customer_id() ) ) ) {
				?>
					<tr>
						<th><?php esc_html_e( 'Name:', 'woocommerce-booking' ); ?></th>
						<td><?php echo esc_html( $user->last_name && $user->first_name ? $user->first_name . ' ' . $user->last_name : '&mdash;' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email:', 'woocommerce-booking' ); ?></th>
						<td><?php echo make_clickable( sanitize_email( $user->user_email ) ); ?></td>
					</tr>
					<tr class="view">
						<th>&nbsp;</th>
						<td><a class="button button-small" target="_blank" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . absint( $user->ID ) ) ); ?>"><?php echo esc_html__( 'View User', 'woocommerce-booking' ); ?></a></td>
					</tr>
					<?php
					$has_data = true;
			}

			if ( $booking->get_order_id() && ( $order = wc_get_order( $booking->get_order_id() ) ) ) {
				?>
					<tr>
						<th valign='top'><?php esc_html_e( 'Address:', 'woocommerce-booking' ); ?></th>
						<td><?php echo wp_kses( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : __( 'No billing address set.', 'woocommerce-booking' ), array( 'br' => array() ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email:', 'woocommerce-booking' ); ?></th>
						<td><?php echo make_clickable( sanitize_email( is_callable( array( $order, 'get_billing_email' ) ) ? $order->get_billing_email() : $order->billing_email ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone:', 'woocommerce-booking' ); ?></th>
						<td><?php echo esc_html( is_callable( array( $order, 'get_billing_phone' ) ) ? $order->get_billing_phone() : $order->billing_phone ); ?></td>
					</tr>
					<tr class="view">
						<th>&nbsp;</th>
						<td><a class="button button-small" target="_blank" href="<?php echo esc_url( bkap_order_url( absint( $booking->get_order_id() ) ) ); ?>"><?php echo esc_html__( 'View Order', 'woocommerce-booking' ); ?></a></td>
					</tr>
					<?php
					$has_data = true;
			}

			if ( ! $has_data ) {
				?>
					<tr>
						<td colspan="2"><?php esc_html_e( 'N/A', 'woocommerce-booking' ); ?></td>
					</tr>
					<?php
			}
			?>
		</table>
		<?php
	}
}

return new BKAP_Customer_Meta_Box();
?>
