<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Resource Settings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Resources
 */

$bkap_resource_by_customer = __( 'Chosen by Customer', 'woocommerce-booking' );
$bkap_resource_automatic   = __( 'Automatically Assigned', 'woocommerce-booking' );

$bkap_resource_are = array(
	'bkap_customer_resource'  => $bkap_resource_by_customer,
	'bkap_automatic_resource' => $bkap_resource_automatic,
);

$all_resources_link = apply_filters( 'bkap_all_resources_link', admin_url( 'edit.php?post_type=bkap_resource' ) );
?>

<table class='form-table bkap-form-table'>
	<tr>
		<th>
			<label for="bkap_product_resource_lable">
				<?php esc_html_e( 'Label:', 'woocommerce-booking' ); ?>
			</label>
		</th>

		<td>
			<?php
			$resource_label          = bkap_get_post_meta_data( $product_id, '_bkap_product_resource_lable', $default_booking_settings, $defaults );
			$resource_selection      = bkap_get_post_meta_data( $product_id, '_bkap_product_resource_selection', $default_booking_settings, $defaults );
			$resource_max_booking    = bkap_get_post_meta_data( $product_id, '_bkap_product_resource_max_booking', $default_booking_settings, $defaults );
			$resource_menu_order     = bkap_get_post_meta_data( $product_id, '_bkap_product_resource_sorting', $default_booking_settings, $defaults );
			$resource_selection_type = bkap_get_post_meta_data( $product_id, '_bkap_product_resource_selection_type', $default_booking_settings, $defaults );
			$resource_selection_type = '' !== $resource_selection_type ? $resource_selection_type : 'single';
			?>

			<input id="bkap_product_resource_lable" name= "bkap_product_resource_lable" value="<?php echo esc_attr( $resource_label ); ?>" size="30" type="text" />

		</td>
		<td>
			<img class="help_tip" width="16" height="16" data-tip="<?php esc_html_e( 'Enter the name to be appear on the front end for selecting resource', 'woocommerce-booking' ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png"/>
		</td>
	</tr>

	<tr>
		<th>
			<label for="bkap_product_resource_selection">
				<?php esc_html_e( 'Resources are:', 'woocommerce-booking' ); ?>
			</label>
		</th>
		<td>
			<select id="bkap_product_resource_selection" name="bkap_product_resource_selection">
				<?php
				foreach ( $bkap_resource_are as $key => $value ) {
					$selected = ( $key == $resource_selection ) ? 'selected' : '';
					echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $value ) . '</option>';
				}
				?>

			</select>
		</td>

		<td>
			<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Customer selected will allow customer to choose resource on the front end booking form', 'woocommerce-booking' ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png"/>
		</td>
	</tr>

	<tr>
		<th>
			<label for="bkap_product_resource_selection_type">
				<?php esc_html_e( 'Resources Selection type', 'woocommerce-booking' ); ?>
			</label>
		</th>
		<td>
			<select id="bkap_product_resource_selection_type" name="bkap_product_resource_selection_type">
				<option value="single"<?php selected( $resource_selection_type, 'single' ); ?>><?php esc_html_e( 'Single Choice ( Dropdown )', 'woocommerce-booking' ); ?></option>
				<option value="multiple"<?php selected( $resource_selection_type, 'multiple' ); ?>><?php esc_html_e( 'Multiple Choice ( Checkbox )', 'woocommerce-booking' ); ?></option>
			</select>
		</td>

		<td>
			<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Define Resource Selection Display Type. Select \'Single\' option for dropdown or \'Multiple\' option for checkbox.', 'woocommerce-booking' ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png"/>
		</td>
	</tr>

	<tr>
		<th>
			<label for="bkap_product_resource_max_booking">
				<?php esc_html_e( 'Consider Product\'s Max Booking:', 'woocommerce-booking' ); ?>
			</label>
		</th>
		<?php
		$max_booking = '';
		if ( isset( $resource_max_booking ) && 'on' == $resource_max_booking ) {
			$max_booking = 'checked';
		}
		?>
		<td>
		<label class="bkap_switch">
			<input id="bkap_product_resource_max_booking" name= "bkap_product_resource_max_booking" type="checkbox" <?php echo $max_booking; ?>/>
		<div class="bkap_slider round"></div>
		</td>
		<td>
			<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enabling this option will override the Product\'s Max Booking over Resource\'s Available Quantity.', 'woocommerce-booking' ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png"/>
		</td>
	</tr>

	<tr>
		<th>
			<label for="bkap_product_resource_sorting">
				<?php esc_html_e( 'Sort Resources by:', 'woocommerce-booking' ); ?>
			</label>
		</th>
		<?php

		$resource_sorting_options = array(
			''           => array(
				'label' => __( 'Default', 'woocommerce-booking' ),
				'title' => __( 'Resources will appear as it appears in the below table.', 'woocommerce-booking' ),
			),
			'ascending'  => array(
				'label' => __( 'Ascending', 'woocommerce-booking' ),
				'title' => __( 'Resources will be sorted by Ascending order.', 'woocommerce-booking' ),
			),
			'menu_order' => array(
				'label' => __( 'Menu Order', 'woocommerce-booking' ),
				'title' => __( 'Resources will be sorted by the value set in Menu Order of Resource.', 'woocommerce-booking' ),
			),
			'price_low'  => array(
				'label' => __( 'Price - Low to High', 'woocommerce-booking' ),
				'title' => __( 'Resources will be sorted by price low to high.', 'woocommerce-booking' ),
			),
			'price_high' => array(
				'label' => __( 'Price - High to Low', 'woocommerce-booking' ),
				'title' => __( 'Resources will be sorted by price high to low.', 'woocommerce-booking' ),
			),
		);

		?>
		<td>
			<select id="bkap_product_resource_sorting" name= "bkap_product_resource_sorting">
			<?php
			foreach ( $resource_sorting_options as $key => $value ) {
				$selected = ( $resource_menu_order == $key ) ? ' selected="selected"' : '';
				printf( '<option value="%s" title="%s" %s>%s</option>', $key, $value['title'], $selected, $value['label'] );
			}
			?>
			</select>
		</td>
		<td>
			<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enabling this option will sort the resources by menu order on the front end.', 'woocommerce-booking' ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png"/>
		</td>
	</tr>
</table>
<hr/>

<p style="padding:1%;" class="notice notice-info">
	<i><?php esc_html_e( 'Resources are used if you have multiple bookable items, e.g. room types, instructors or ticket types. Availability for resources are global across all bookable products.', 'woocommerce-booking' ); ?></i>
</p>
<div id="bkap_resource_section">
	<?php
	if ( ! is_admin() ) {
		?>
	<input type="hidden" id="bkap_vendor_resource_url" value="<?php echo esc_attr( $all_resources_link ) . '?bkap-resource='; ?>">
		<?php
	}
	?>
	<table class="bkap_resource_info">
		<tr>
			<th><?php esc_html_e( 'Resource Title', 'woocommerce-booking' ); ?></th>
			<th><?php esc_html_e( 'Pricing', 'woocommerce-booking' ); ?></th>
			<th id="bkap_remove_resource"><i class="fa fa-trash" aria-hidden="true"></i></th>
			<th>
				<a href="<?php echo esc_attr( $all_resources_link ); ?>" target="_blank">
					<i class="fas fa-external-link-alt" aria-hidden="true"></i>
				</a>
			</th>
		</tr>

	<?php
		$all_resources             = Class_Bkap_Product_Resource::bkap_get_all_resources();
		$resources_of_product      = bkap_get_post_meta_data( $product_id, '_bkap_product_resources', $default_booking_settings, $defaults );
		$resources_cost_of_product = bkap_get_post_meta_data( $product_id, '_bkap_resource_base_costs', $default_booking_settings, $defaults );
		$loop                      = 0;

	if ( is_array( $resources_of_product ) && count( $resources_of_product ) > 0 ) {
		foreach ( $resources_of_product as $resource_id ) {

			if ( get_post_status( $resource_id ) ) {
				$resource           = new BKAP_Product_Resource( $resource_id );
				$resource_base_cost = isset( $resources_cost_of_product[ $resource_id ] ) ? $resources_cost_of_product[ $resource_id ] : '';
				$resource_url       = apply_filters( 'bkap_resource_link_booking_metabox', admin_url( 'post.php?post=' . $resource_id . '&action=edit' ), $resource_id );
				include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html-bkap-resource.php';
				$loop++;
			}
		}
	}
	?>
	</table>

	<div class="bkap_resource_add_section">
		<a href="<?php echo esc_attr( $all_resources_link ); ?>" target="_blank"><?php esc_html_e( 'All Resources', 'woocommerce-booking' ); ?></a>

		<button type="button" class="button button-primary bkap_add_resource"><?php esc_html_e( 'Add/link Resource', 'woocommerce-booking' ); ?></button>
		<select name="add_resource_id" class="bkap_add_resource_id" >
			<option value=""><?php esc_html_e( 'New resource', 'woocommerce-booking' ); ?></option>
			<?php
			if ( $all_resources ) {
				foreach ( $all_resources as $resource ) {
					echo '<option value="' . esc_attr( $resource->ID ) . '">#' . absint( $resource->ID ) . ' - ' . esc_html( $resource->post_title ) . '</option>';
				}
			}
			?>
		</select>
	</div>
</div>
