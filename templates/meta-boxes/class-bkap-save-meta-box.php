<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Booking Save Meta Box
 *
 * @author   Tyche Softwares
 * @package  BKAP/Meta-Boxes
 * @category Classes
 * @class    BKAP_Save_Meta_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Save_Meta_Box.
 */
class BKAP_Save_Meta_Box {

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
		$this->id         = 'bkap-booking-save';
		$this->title      = __( 'Booking actions', 'woocommerce-booking' );
		$this->context    = 'side';
		$this->priority   = 'high';
		$this->post_types = array( 'bkap_booking' );
	}

	/**
	 * Render inner part of meta box.
	 */
	public function meta_box_inner( $post ) {

		wp_nonce_field( 'bkap_save_booking_meta_box', 'bkap_save_booking_meta_box_nonce' );

		$bkap_actions = array(
			'send_reminder_email' => __( 'Send a reminder email to customer', 'woocommerce-booking' ),
		);
		?>
<!-- 	Should be uncommented when we stop using the custom plugin tables	
		<div id="delete-action"><a class="submitdelete deletion" href="<?php // echo esc_url( get_delete_post_link( $post->ID ) ); ?>"><?php // _e( 'Move to trash', 'woocommerce-booking' ); ?></a></div>  -->
		<ul class="bkap_actions submitbox">

			<li class="wide" style="display:flex; border-bottom: 1px solid #ddd; padding: 10px 0;">
				<input type="button" id="button_reminder" class="button" value="<?php _e( 'Send Reminder', 'woocommerce-booking' ); ?>" data-bookid="<?php echo $post->ID; ?>"  />
				<div id="ajax_img" name="ajax_img" style="float:right"> 
					<img src="<?php echo plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif'; ?>"> 
				</div>
			</li>

			<li class="wide" style="margin-top: 10px;">
				<div id="delete-action"><a class="submitdelete deletion" href="javascript:void(0)" id="bkap_delete"><?php _e( 'Move to trash', 'woocommerce-booking' ); ?></a></div>
				 <input type="submit" style='margin-left:40px;' class="button save_order button-primary tips" name="bkap_save" value="<?php _e( 'Save Booking', 'woocommerce-booking' ); ?>" data-tip="<?php _e( 'Save/update the booking', 'woocommerce-booking' ); ?>" />	
			
			</li>
			<!--<input type="button" class="button bkap_cancel button-primary tips" name="bkap_cancel" value="<?php _e( 'Cancel', 'woocommerce-booking' ); ?>" data-tip="<?php _e( 'Cancel', 'woocommerce-booking' ); ?>" /> -->
		</ul>
		
	   
		<?php
	}
}
return new BKAP_Save_Meta_Box();
