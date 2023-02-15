<?php

?>
<div id="bkap-create-booking" class="wrap woocommerce">
	<h2 class='bkap-page-heading'><?php esc_html_e( 'Create Booking', 'woocommerce-booking' ); ?></h2>
	<p><?php esc_html_e( 'You can create a new booking for a customer here. This form will create a booking for the user, and optionally an associated order. Created orders will be marked as processing.', 'woocommerce-booking' ); ?></p>
	<?php
	$bkap_admin_bookings->show_errors();
	?>
	<form method="POST">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="customer_id"><?php esc_html_e( 'Customer', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<select id="customer_id" name="customer_id" class="bkap-customer-search">
							<option value="0"><?php esc_html_e( 'Guest', 'woocommerce-booking' ); ?></option> 
							<?php
							foreach ( $bkap_customers as $c_id => $c_data ) {
								echo '<option value="' . esc_attr( $c_id ) . '">' . sanitize_text_field( $c_data ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="bkap_product_id"><?php esc_html_e( 'Bookable Product', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<select id="bkap_product_id" name="bkap_product_id" class="chosen_select" style="width: 300px">
							<option value=""><?php esc_attr_e( 'Select a bookable product...', 'woocommerce-booking' ); ?></option>
							<?php
							foreach ( $bkap_all_bookable_products as $product ) :
								// Do not add Grouped Products and subscription products to the dropdown.
								$_product     = wc_get_product( $product[1] );
								$product_type = ( $php_version ) ? $product->type : $_product->get_type();

								if ( in_array( $product_type, array( 'subscription', 'grouped', 'composite', 'bundle' ), true ) ) {
									continue;
								}
								?>
								<option value="<?php echo $product[1]; ?>"><?php echo sprintf( '%s', $product[0] ); // phpcs:ignore ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="bkap_create_order"><?php esc_html_e( 'Create Order', 'woocommerce-booking' ); ?></label>
					</th>
					<td>
						<p>
							<label>
								<input type="radio" name="bkap_order" value="new" class="checkbox" checked/>
								<?php esc_html_e( 'Create a new corresponding order for this new booking. Please note - the booking will not be active until the order is processed/completed.', 'woocommerce-booking' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="radio" name="bkap_order" value="existing" class="checkbox" />
								<?php esc_html_e( 'Assign this booking to an existing order with this ID:', 'woocommerce-booking' ); ?>
								<input type="number" name="bkap_order_id" value="" class="text" size="3" style="width: 80px;" />
							</label>
						</p>
					</td>
				</tr>
				<?php do_action( 'bkap_after_create_booking_page' ); ?>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" name="bkap_create_booking" class="button-primary" value="<?php esc_attr_e( 'Next', 'woocommerce-booking' ); ?>" />
						<?php wp_nonce_field( 'bkap_create_notification' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
