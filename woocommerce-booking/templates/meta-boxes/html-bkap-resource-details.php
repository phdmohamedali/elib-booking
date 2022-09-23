<?php
/**
 * This Template will display the Resource Availability Options in Meta Box.
 *
 * @package BKAP/Resource-Availability-Options
 */

?>

<div class="panel-wrap" id="bkap_resource_availability">
	<div class="options_group resource_options_panel">
		<p class="form-field _bkap_booking_qty_field ">
			<label for="_bkap_booking_qty"><?php esc_html_e( 'Available Quantity :', 'woocommerce-booking' ); ?></label>
			<input type="number" class="short" style="width: 100px;" name="_bkap_booking_qty" id="_bkap_booking_qty" value="<?php esc_attr_e( $resource_qty ); ?>"min="0" step="1" title="<?php esc_attr_e( 'The quantity of this resource available at any given time.', 'woocommerce-booking' ); ?>">
		</p>
		<p class="form-field _bkap_resource_menu_order_field ">
			<label for="_bkap_resource_menu_order"><?php esc_html_e( 'Menu Order :', 'woocommerce-booking' ); ?></label>
			<input type="number" class="short" style="width: 100px;" name="_bkap_resource_menu_order" id="_bkap_resource_menu_order" value="<?php esc_attr_e( $resource_menu_order ); ?>"min="0" step="1" title="<?php esc_attr_e( 'Setting value to this field will decide the appearance of resource in the list.', 'woocommerce-booking' ); ?>">
		</p>
		<?php
		if ( isset( $response->users ) ) {
			?>
			<p class="form-field _bkap_zoom_meeting_host_field ">
				<label for="_bkap_zoom_meeting_host"><?php esc_html_e( 'Meeting Host:', 'woocommerce-booking' ); ?> </label>
				<select name="_bkap_zoom_meeting_host" id="_bkap_zoom_meeting_host">
				<option value=''><?php esc_html_e( 'Select Host', 'woocommerce-booking' ); ?></option>
				<?php
				foreach ( $response->users as $user ) {
					$zoom_host_selected = ( $user->id === $zoom_host_id ) ? 'selected' : '';
					$zoom_first_name    = $user->first_name;
					$zoom_last_name     = $user->last_name;
					$zoom_email         = $user->email;
					$zoom_display       = $zoom_first_name . ' ' . $zoom_last_name . ' - ' . $zoom_email;
					printf( "<option value='%s' %s>%s</option>", esc_attr( $user->id ), esc_attr( $zoom_host_selected ), esc_html( $zoom_display ) );
				}
				?>
				</select>
			</p>
			<?php
		}
		?>
	</div>
	
	<div class="options_group bkap_availability_range">
		<table class="widefat">
			<thead>
				<tr>
					<th><b><?php esc_html_e( 'Range type', 'woocommerce-booking' ); ?></b></th>
					<th><b><?php esc_html_e( 'From', 'woocommerce-booking' ); ?></b></th>
					<th></th>
					<th><b><?php esc_html_e( 'To', 'woocommerce-booking' ); ?></b></th>
					<th><b><?php esc_html_e( 'Bookable', 'woocommerce-booking' ); ?></b></th>
					<th><b><?php esc_html_e( 'Priority', 'woocommerce-booking' ); ?></b></th>
					<th class="remove" width="1%">&nbsp;</th>
				</tr>
			</thead>
			<tfoot>
				<tr >
					<th colspan="4" style="text-align: left;font-size: 11px;font-style: italic;">
						<?php esc_html_e( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ).', 'woocommerce-booking' ); ?>
					</th>	
					<th colspan="3" style="text-align: right;">
						<a href="#" class="button button-primary bkap_add_row_resource" style="text-align: right;" data-row="
						<?php
							ob_start();
							require 'html_resource_availability_table.php';
							$html = ob_get_clean();
							echo esc_attr( $html );
						?>
						"><?php esc_html_e( 'Add Range', 'woocommerce-booking' ); ?></a>
					</th>
				</tr>
			</tfoot>

			<tbody id="bkap_availability_rows">
				<?php
				if ( ! empty( $resource_availability ) && is_array( $resource_availability ) ) {
					foreach ( $resource_availability as $availability ) {
						include 'html_resource_availability_table.php';
					}
				}
				?>
			</tbody>
		</table>
	</div>
</div>
