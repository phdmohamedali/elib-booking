<?php

/**
 *  WC VEndors Dashboard View Bookings Template
 *
 *  Load all Bookings
 *
 *  @since 4.6.0
 *
 *  @package woocommerce-booking
 */

// setup the links for pagination.
if ( $page_count > 1 ) :

	$page_links = paginate_links(
		array(
			'current'  => $cur_page,
			'total'    => $page_count,
			'base'     => $base_url . '%_%',
			'format'   => ( isset( $_REQUEST['filter-bookings'] ) ? '&' : '?' ) . 'pagenum=%#%',
			'add_args' => false,
			'type'     => 'array',
		)
	);

	echo '<div class="pagination-wrap">';
	echo "<ul class='pagination'>\n\t<li>";
	echo join( "</li>\n\t<li>", $page_links );
	echo "</li>\n</ul>\n";
	echo '</div>';

	endif;

if ( $total_count > 0 ) {
	?>

		<table id="bkap_bookings_data" class="bkap_table_data wcvendors-table wcvendors-table-order wcv-table">
			<tr>
			<th scope="col"><?php esc_html_e( 'Status', 'woocommerce-booking' ); ?></span></th>
				<th scope="col"><?php esc_html_e( 'ID', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Booked Product', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Booked by', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Order', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Start Date', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'End Date', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Zoom Meeting', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Amount', 'woocommerce-booking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'woocommerce-booking' ); ?></th>
			<tr>

		<?php

		if ( is_array( $booking_posts ) && count( $booking_posts ) > 0 && $booking_posts != false ) {
			foreach ( $booking_posts as $booking_id => $post_data ) {

				$bkap_status             = $post_data['status'];
				$active_statuses         = bkap_common::get_bkap_booking_statuses();
				$status_label            = array_key_exists( $bkap_status, $active_statuses ) ? $active_statuses[ $bkap_status ] : ucwords( $bkap_status );
				$status_label_translated = bkap_common::get_bkap_translated_status_label( $status_label );
				$can_edit_approved       = WC_Vendors::$pv_options->get_option( 'can_edit_published_products' );

				if ( $can_edit_approved ) {
					// try to link to the edit product page in the dashboard.
					$product_name = $post_data['product_name'];
				} else {
					$product_name = $post_data['product_name'];
				}

				$product_id = $post_data['product_id'];

				$booking_details = array(
					'date'        => $post_data['start'],
					'hidden_date' => $post_data['hidden_start'],
					'price'       => $post_data['amount'],
				);

				if ( isset( $post_data['end'] ) && '' !== $post_data['end'] ) {
					$booking_details['date_checkout']        = $post_data['end'];
					$booking_details['hidden_date_checkout'] = $post_data['hidden_end'];
				}

				if ( isset( $post_data['time_slot'] ) ) {
					$booking_details['time_slot'] = $post_data['time_slot'];
				}

				if ( isset( $post_data['selected_duration'] ) ) {
					$booking_details['selected_duration']  = $post_data['selected_duration'];
					$booking_details['duration_time_slot'] = $post_data['duration_time_slot'];
				}

				if ( in_array( bkap_type( $product_id ), array( 'multidates', 'multidates_fixedtime' ), true ) ) {
					$booking_labels      = bkap_booking_fields_label();
					$item_obj            = new WC_Order_Item_Product( $post_data['order_item_id'] );
					$all_booking_details = bkap_cancel_order::bkap_get_booking_item_meta( $post_data['order_item_id'], $item_obj, $booking_labels );
					foreach ( $all_booking_details as $key => $value ) {
						if ( isset( $booking_details['time_slot'] ) ) {
							if ( $booking_details['time_slot'] == $value['time_slot'] ) {

								if ( $booking_details['hidden_date'] == date( 'd-m-Y', strtotime( $value['hidden_date'] ) ) ) {
									$item_number = $key;
									break;
								}
							}
						} else {
							if ( $booking_details['hidden_date'] == date( 'd-m-Y', strtotime( $value['hidden_date'] ) ) ) {
								$item_number = $key;
								break;
							}
						}
					}
				}

				$item_no = '';
				if ( isset( $item_number ) ) {
					$item_no = '_' . $item_number;
				}

				$actions = '<button class="bkap-button wcv-tooltip bkap_edit" data-tip-text="' . esc_attr__( 'Edit Booking', 'woocommerce-booking' ) . '" onclick="bkap_edit_booking_class.bkap_edit_bookings( \'' . $post_data['product_id'] . '\', \'' . $post_data['order_item_id'] . $item_no . '\' )"><i class="fas fa-edit"></i></button>';

				if ( 'pending-confirmation' === $bkap_status ) {
					$actions .= "<a href='?custom=bkap-booking&action=bkap-confirm&booking_id=$booking_id' class='bkap-button wcv-tooltip bkap_confirm' data-tip-text='" . esc_attr__( 'Confirm Booking', 'woocommerce-booking' ) . "'></a>&nbsp;";
				}

				$actions .= "<a href='?custom=bkap-booking&action=bkap-cancel&booking_id=$booking_id' class='bkap-button wcv-tooltip bkap_cancel' data-tip-text='" . esc_attr__( 'Cancel Booking', 'woocommerce-booking' ) . "'></a>";

				$zoom_meeting_link = wp_is_mobile() ? 'N/A' : '';
				if ( 'bkap_booking' === get_post_type( $booking_id ) ) {
					$_booking     = new BKAP_Booking( $booking_id );
					$meeting_link = $_booking->get_zoom_meeting_link();
					if ( '' !== $meeting_link ) {
						$zoom_meeting_link = sprintf( '<a href="%s" target="_blank"><span class="dashicons dashicons-video-alt2"></span></a>', $meeting_link );
					}
				}
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Status', 'woocommerce-booking' ); ?>"><span class="bkap_wcv_status status-<?php echo esc_attr( $bkap_status ); ?> wcv-tooltip" data-tip-text="<?php echo esc_attr( $status_label_translated ); ?>" ><?php echo esc_html( $status_label_translated ); ?></span></td>
					<td data-label="<?php esc_attr_e( 'ID', 'woocommerce-booking' ); ?>"><?php echo '#' . $booking_id; ?></td>
					<td data-label="<?php esc_attr_e( 'Booked Product', 'woocommerce-booking' ); ?>"><strong><?php echo $product_name . ' x ' . $post_data['qty']; ?></strong></td>
					<td data-label="<?php esc_attr_e( 'Booked by', 'woocommerce-booking' ); ?>"><?php echo $post_data['customer_name']; ?></td>
					<td data-label="<?php esc_attr_e( 'Order', 'woocommerce-booking' ); ?>"><strong><?php echo '#' . $post_data['order_id'] . ' - ' . $post_data['order_status'] . '</strong><br>' . $post_data['order_date']; ?></td>
					<td data-label="<?php esc_attr_e( 'Start Date', 'woocommerce-booking' ); ?>"><?php echo $post_data['start']; ?></td>
					<td data-label="<?php esc_attr_e( 'End Date', 'woocommerce-booking' ); ?>"><?php if( isset( $post_data['end'] ) ) echo $post_data['end']; ?></td>
					<td data-label="<?php esc_attr_e( 'Zoom Meeting', 'woocommerce-booking' ); ?>"><?php echo $zoom_meeting_link; // phpcs:ignore ?></td>
					<td data-label="<?php esc_attr_e( 'Amount', 'woocommerce-booking' ); ?>"><?php echo $post_data['amount']; ?></td>
					<td data-label="<?php esc_attr_e( 'Actions', 'woocommerce-booking' ); ?>">
						<?php
						do_action( 'bkap_wc_vendors_booking_list', $booking_id, $post_data, $booking_details, $item_no );
						echo $actions;
						?>
					</td>
				</tr>
				<?php
			}
		} else {
			?>
			<h6><?php esc_html_e( 'No Bookings found.', 'woocommerce-booking' ); ?></h6>
			<?php
		}
		?>
		</table>
		<?php
} else {
	?>
	<h6><?php esc_html_e( 'No Bookings found.', 'woocommerce-booking' ); ?></h6>
	<?php
}

if ( $page_count > 1 ) {

	echo '<div class="pagination-wrap">';
	echo "<ul class='pagination'>\n\t<li>";
	echo join( "</li>\n\t<li>", $page_links );
	echo "</li>\n</ul>\n";
	echo '</div>';
}
?>
</div>
