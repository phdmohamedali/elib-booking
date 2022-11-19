<?php
/**
 *  Dokan Dashboard View Bookings Template
 *
 *  Load all Bookings template
 *
 *  @since 4.6.0
 *
 *  @package woocommerce-booking
 */

?>
<div class="bkap-view-booking">

	<div class="dokan-bkap-view-content">

		<article class="dokan-booking-area">

			<?php if ( is_array( $booking_posts ) && count( $booking_posts ) > 0 && $booking_posts != false ) : ?>

				<?php
				if ( $page_count > 1 ) :

					echo '<div class="pagination-wrap dokan-right">';
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

					echo "<ul class='pagination'>\n\t<li>";
					echo join( "</li>\n\t<li>", $page_links );
					echo "</li>\n</ul>\n";
					echo '</div>';

				endif;
				?>

				<table class="dokan-table dokan-table-striped dokan-bookings-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Status', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Booked Product', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Booked By', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Order', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Start Date', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'End Date', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Persons', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Zoom Meeting', 'woocommerce-booking' ); ?></th>
							<th><?php esc_html_e( 'Action', 'woocommerce-booking' ); ?></th>
							<?php

								/**
								 *  bkap_dokan_add_columns_header_booking Hook
								 *
								 *  @since 4.6.0
								 */
								do_action( 'bkap_dokan_add_columns_header_booking' );
							?>
						</tr>
					</thead>
					<tbody>

						<?php
						foreach ( $booking_posts as $booking_id => $post_data ) :
							$product_id      = $post_data['product_id'];
							$booking_details = array(
								'date'        => $post_data['start'],
								'hidden_date' => $post_data['hidden_start'],
								'price'       => $post_data['amount'],
							);

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

							$persons = $post_data['persons'];

							$zoom_meeting_link = '';
							if ( 'bkap_booking' === get_post_type( $booking_id ) ) {
								$_booking     = new BKAP_Booking( $booking_id );
								$meeting_link = $_booking->get_zoom_meeting_link();
								if ( '' !== $meeting_link ) {
									$zoom_meeting_link = sprintf( '<a href="%s" target="_blank"><span class="dashicons dashicons-video-alt2"></span></a>', $meeting_link );
								}
							}

							$order_id   = $post_data['order_id'];
							$sub_orders = dokan_get_suborder_ids_by( $order_id );
							if ( ! is_null( $sub_orders ) ) {
								foreach ( $sub_orders as $key => $value ) {
									$sub_order_id    = $value->ID;
									$new_order       = wc_get_order( $value->ID );
									$order_seller_id = dokan_get_seller_id_by_order( $new_order );

									if ( $vendor_id == $order_seller_id ) {
										$orderurl = add_query_arg( '_wpnonce', wp_create_nonce( 'dokan_view_order' ), add_query_arg( [ 'order_id' => $value->ID ], dokan_get_navigation_url( 'orders' ) ) );
										$order_id = $value->ID;
										break;
									}
								}
							} else {
								$orderurl = add_query_arg( '_wpnonce', wp_create_nonce( 'dokan_view_order' ), add_query_arg( [ 'order_id' => $order_id ], dokan_get_navigation_url( 'orders' ) ) );
							}
							?>
							<tr>
								<td class="dokan-booking-id column-primary" data-title="Booking ID"><?php printf( __( '<strong>Booking #%s</strong>', 'woocommerce-booking' ), $booking_id ); ?><button type="button" class="toggle-row"></button></td>
								<td class="dokan-booking-status" data-title="Booking Status"><?php echo apply_filters( 'bkap_dokan_booking_status', $post_data['status'] ); ?></td>
								<td class="dokan-booking-product" data-title="Product Name"><?php echo $post_data['product_name'] . ' x ' . $post_data['qty']; ?></td>
								<td class="dokan-booking-cutomer" data-title="Customer Name"><?php echo $post_data['customer_name']; ?></td>
								<td class="dokan-booking-order" data-title="Order ID"><?php echo "<a href='" . $orderurl . "'><strong>#" . $order_id . '</strong></a> - ' . $post_data['order_status'] . '<br>' . $post_data['order_date']; ?></td>
								<td class="dokan-booking-start" data-title="Start Date"><?php echo $post_data['start']; ?></td>
								<td class="dokan-booking-end" data-title="End Date"><?php if( isset( $post_data['end'] ) ) echo $post_data['end']; ?></td>
								<td class="dokan-booking-persons" data-title="Persons"><?php echo $post_data['persons']; ?></td>
								<td class="dokan-booking-amount" data-title="Amount"><?php echo $post_data['amount']; ?></td>
								<td class="dokan-booking-zoom" data-title="Zoom Meeting"><?php echo $zoom_meeting_link; // phpcs:ignore ?></td>
								<td class="dokan-booking-action" data-title="Actions">
									<button 
										class="dokan-btn dokan-btn-default dokan-btn-sm tips bkap-dokan-btn" 
										data-toggle="tooltip" 
										data-placement="top" 
										title="<?php esc_html_e( 'View & Edit', 'woocommerce-booking' ); ?>"
										onclick="bkap_dokan_class.bkap_dokan_view_booking( '<?php echo $post_data['product_id']; ?>', '<?php echo $post_data['order_item_id'] . $item_no; ?>' )"
									>
										<i class="fa fa-eye">&nbsp;</i>
									</button>

									<?php if ( 'pending-confirmation' === $post_data['status'] || 'cancelled' === $post_data['status'] ) : ?>

										<button 
											class="dokan-btn dokan-btn-default dokan-btn-sm tips bkap-dokan-btn" 
											data-toggle="tooltip" 
											data-placement="top" 
											title="<?php esc_html_e( 'Confirm', 'woocommerce-booking' ); ?>"
											onclick="bkap_dokan_class.bkap_dokan_change_status( <?php echo $post_data['order_item_id']; ?>, 'confirmed' )"
										>
											<i class="fa fa-check">&nbsp;</i>
										</button>
									<?php endif; ?>

									<?php if ( $post_data['status'] !== 'cancelled' ) : ?>

										<button 
											class="dokan-btn dokan-btn-default dokan-btn-sm tips bkap-dokan-btn" 
											data-toggle="tooltip" 
											data-placement="top" 
											title="<?php esc_html_e( 'Cancel', 'woocommerce-booking' ); ?>"
											onclick="bkap_dokan_class.bkap_dokan_change_status( <?php echo $post_data['order_item_id']; ?>, 'cancelled' )"
										>
											<i class="fa fa-times">&nbsp;</i>
										</button>
									<?php endif; ?>
									<?php do_action( 'bkap_dokan_booking_list', $booking_id, $post_data, $booking_details, $item_no ); ?>
								</td>

								<?php

									/**
									 * bkap_dokan_add_columns_booking Hook
									 *
									 * @since 4.6.0
									 */
									do_action( 'bkap_dokan_add_columns_booking', $booking_id, $post_data );
								?>
							</tr>

						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				if ( $page_count > 1 ) :

					echo '<div class="pagination-wrap dokan-right">';
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

					echo "<ul class='pagination'>\n\t<li>";
					echo join( "</li>\n\t<li>", $page_links );
					echo "</li>\n</ul>\n";
					echo '</div>';

				endif;
				?>

			<?php else : ?>

				<p class="dokan-info"><?php esc_html_e( 'No Bookings found!', 'woocommerce-booking' ); ?></p>

			<?php endif; ?>

		</article>


		<?php

			/**
			 * dokan_order_content_inside_after hook
			 *
			 * @since 4.6.0
			 */
			do_action( 'bkap_dokan_booking_list_after' );
		?>

	</div> <!-- #primary .content-area -->

	<?php

		/**
		 * dokan_dashboard_content_after hook
		 * dokan_order_content_after hook
		 *
		 * @since 4.6.0
		 */
		do_action( 'bkap_dokan_booking_table_after' );

	?>

</div><!-- .dokan-dashboard-wrap -->
