<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Modal Popup template for allowing to edit Booking
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div id="bkap_edit_modal_<?php echo $bkap_cart_item_key; ?>" class="bkap-modal">

	<!-- Save Progress Loader -->
	<div id="bkap_save" class="bkap_save"></div>

	<!-- Modal content -->
	<div class="bkap-booking-contents">

		<div class="bkap-booking-header">
			<?php
			if ( ! empty( $bkap_order_id ) ) {
				$order             = wc_get_order( $bkap_order_id );
				$is_coupon_applied = count( $order->get_coupon_codes() );
			} else {
				$is_coupon_applied = 0;
			}
			?>
			<div class="bkap-header-title">
				<h1 class="product_title entry-title">
					<?php $edit_booking_label = apply_filters( 'bkap_edit_booking_label', 'Edit Bookings' ); ?>
					<?php echo $product_obj->get_name() . ' - ' . __( $edit_booking_label, 'woocommerce-booking' ); ?>
				</h1>
				<?php
				if ( $is_coupon_applied ) {
					$reschedule_notification = apply_filters(
						'bkap_reschedule_notification',
						__( 'Any coupon codes associated with this order will be applied once you click on Confirm Bookings', 'woocommerce-booking' )
					);
					echo '<p class="bkap-reschedule-notification">' . $reschedule_notification . '</p>';
				}
				?>
			</div>
			<div class="bkap-header-close" onclick='bkap_edit_booking_class.bkap_close_popup(<?php echo $product_id; ?>, "<?php echo $bkap_cart_item_key; ?>")'>&times;</div>

		</div>

		<div style="clear: both;"></div>

		<div id="modal-body-<?php echo $bkap_cart_item_key; ?>" class="modal-body">

			<?php

				bkap_load_scripts_class::include_frontend_scripts_js( $product_id );
				bkap_load_scripts_class::inlcude_frontend_scripts_css( $product_id );

				$duplicate_of = bkap_common::bkap_get_product_id( $product_id );
				$bookable     = bkap_common::bkap_get_bookable_status( $duplicate_of );

				if ( ! $bookable ) {
					return;
				}

				$booking_settings     = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
				$booking_settings_new = bkap_get_post_meta( $duplicate_of );
				$global_settings      = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				$product_type         = $product_obj->get_type();

				$hidden_dates = bkap_booking_process::bkap_localize_process_script( $product_id );

				if ( isset( $bkap_booking['hidden_date'] ) && '' !== $bkap_booking['hidden_date'] ) {
					$hidden_dates['hidden_date'] = date( 'j-n-Y', strtotime( $bkap_booking['hidden_date'] ) );
				} else {
					$hidden_dates['hidden_date'] = date( 'j-n-Y' );
				}

				$hidden_dates['hidden_checkout'] = '';
				if ( isset( $bkap_booking['hidden_date_checkout'] ) && '' !== $bkap_booking['hidden_date_checkout'] ) {
					$hidden_dates['hidden_checkout'] = date( 'j-n-Y', strtotime( $bkap_booking['hidden_date_checkout'] ) );
				}

				$hidden_dates['init_isblock'] = false;
				$booking_type                 = get_post_meta( $duplicate_of, '_bkap_booking_type', true );
				$order_bookings               = bkap_common::get_booking_ids_from_order_id( $bkap_order_id );
				if ( is_account_page() && 'multiple_days' === $booking_type && count( $order_bookings ) > 0 ) {
					$hidden_dates['init_isblock'] = apply_filters( 'bkap_initial_booking_is_locked', false );
				}

				wc_get_template(
					'bookings/bkap-bookings-box.php',
					array(
						'product_id'       => $duplicate_of,
						'product_obj'      => $product_obj,
						'booking_settings' => $booking_settings,
						'global_settings'  => $global_settings,
						'hidden_dates'     => $hidden_dates,
					),
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);

				?>
			<input type="hidden" class="variation_id" value="<?php echo $variation_id; ?>" />

			<!-- When Editing Bookings with Resource -->
			<?php
			if ( isset( $bkap_booking['resource_id'] ) ) :
				$resource_id = $bkap_booking['resource_id'];

				if ( is_array( $resource_id ) ) {
					$resource_id = implode( ',', $resource_id );
				}
				?>

				<div class="resource_id_container">
					<input type="hidden" name="chosen_resource_id" id="chosen_resource_id" class="rform_hidden" value="<?php echo $resource_id; ?>">
				</div>

			<?php endif; ?>

			<!-- When Editing Bookings with Persons -->
			<?php if ( isset( $bkap_booking['persons'] ) ) : ?>
				<div class="persons_container">
					<?php
					if ( isset( $bkap_booking['persons'][0] ) ) {
						?>
						<input type="hidden" name="chosen_person" id="chosen_person" class="rform_hidden" value="<?php echo $bkap_booking['persons'][0]; ?>">
						<?php
					} else {
						foreach ( $bkap_booking['persons'] as $p_key => $p_value ) {
							?>
							<input type="hidden" name="chosen_person_<?php echo $p_key; ?>" id="chosen_person_<?php echo $p_key; ?>" class="rform_hidden" value="<?php echo $p_value; ?>">
							<?php
						}
					}
					?>
			</div>

			<?php endif; ?>

			<!-- When Editing Bookings with Fixed Blocks -->
			<?php if ( isset( $bkap_booking['fixed_block'] ) && $bkap_booking['fixed_block'] != '' ) : ?>

				<div class="fixed_block_container">
					<input type="hidden" name="chosen_fixed_block" id="chosen_fixed_block" class="rform_hidden" value="<?php echo $bkap_booking['fixed_block']; ?>">
				</div>

			<?php endif; ?>

			<!-- When Editing Bookings with Gravity Forms -->
			<?php if ( isset( $bkap_addon_data['gf_options'] ) && $bkap_addon_data['gf_options'] !== '' ) : ?>

				<div class="ginput_container_total">
					<input type="hidden" name="gravity_forms_options" id="gravity_forms_options" class="gform_hidden" value="<?php echo $bkap_addon_data['gf_options']; ?>">
				</div>

			<?php endif; ?>

			<!-- When Editing Bookings with Product Addons -->
			<?php if ( isset( $bkap_addon_data['wpa_options'] ) && $bkap_addon_data['wpa_options'] !== '' ) : ?>

				<div id="product-addons-total" data-show-grand-total="1" data-type="simple" data-price="" data-raw-price="" data-addons-price="<?php echo $bkap_addon_data['wpa_options']; ?>"></div>

			<?php endif; ?>

			<!-- When Editing Bookings with Measurement Price -->
			<?php if ( isset( $bkap_addon_data['_measurement_needed'] ) && $bkap_addon_data['_measurement_needed'] !== '' ) : ?>

			<div class="measurement_needed_section">
				<input type="hidden" name="_measurement_needed" id="_measurement_needed" value="<?php echo $bkap_addon_data['_measurement_needed']; ?>">
			</div>

			<?php endif; ?>
			<div class="bkap-error"></div>
		</div>

		<div class="modal-footer">
			<?php $confirm_booking_label = apply_filters( 'bkap_confirm_booking_label', __( 'Confirm Bookings', 'woocommerce-booking' ) ); ?>
			<input
				type="button"
				name="confirm_bookings"
				id="confirm_bookings_<?php echo $bkap_cart_item_key; ?>"
				onclick='bkap_edit_booking_class.bkap_confirm_booking(<?php echo $product_id; ?>, "<?php echo $bkap_cart_item_key; ?>")'
				value="<?php echo esc_attr( $confirm_booking_label ); ?>"
				class="bkap_modal_button_class"
			/>

			<input
				type="button"
				name="cancel_modal"
				id="cancel_modal"
				onclick='bkap_edit_booking_class.bkap_close_popup(<?php echo $product_id; ?>, "<?php echo $bkap_cart_item_key; ?>")'
				value="<?php _e( 'Cancel', 'woocommerce-booking' ); ?>"
				class="bkap_modal_button_class"
			/>
		</div>

	</div>

</div>
