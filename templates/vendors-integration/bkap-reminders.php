<?php
/**
 * The template is for listing the Reminder on front end.
 *
 * @package BKAP/Wcfm-Marketplace-List-Reminder
 * @version 1.1.0
 */

global $wp;

$base_url = isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ( '/' . $wp->request . '/' );

// Get the vendor ID.
$bkap_vendors = new BKAP_Vendors();
$vendor_id    = get_current_user_id();

$rec_per_page = 20;
$cur_page     = 1;
$pagenum      = isset( $_GET['pagenum'] ) ? (int) $_GET['pagenum'] : 1; // phpcs:ignore WordPress.Security.NonceVerification
$search_term  = isset( $_POST['reminder-search'] ) && '' !== $_POST['reminder-search'] ? sanitize_text_field( wp_unslash( $_POST['reminder-search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

// Get the number of pages.
$page_count = $bkap_vendors->get_number_of_pages( $vendor_id, $rec_per_page, 'bkap_reminder', array( 's' => $search_term ) );
if ( 1 < $pagenum ) {
	$cur_page = $pagenum;
}

// Remove the pagenum query variable base url if it already exists.
$pagenum_pos = strpos( $base_url, 'pagenum' );
if ( false !== $pagenum_pos ) {
	$base_url = substr( $base_url, 0, ( $pagenum_pos - 1 ) );
}

if ( 0 < $page_count ) {

	$add_reminder_link = $manage_reminder_url . '?bkap-reminder=new';
	$add_manual_reminder_link = $manage_reminder_url . '?bkap-manual-reminder=true';
	$add_sms_reminder_link = $manage_reminder_url . '?bkap-sms-reminder=true';
	?>
	<div id="bkap_all_reminders">
		<div class="search-box">
		<a href="<?php esc_attr_e( $add_reminder_link ); //phpcs:ignore ?>" class="button"><?php esc_html_e( 'Add Reminder', 'woocommerce-booking' ); ?></a>
		<a style="float:right;margin-left: 8px;" href="<?php esc_attr_e( $add_sms_reminder_link ); //phpcs:ignore ?>" class="button"><?php esc_html_e( 'SMS Settings', 'woocommerce-booking' ); ?></a>
		<a style="float:right;" href="<?php esc_attr_e( $add_manual_reminder_link ); //phpcs:ignore ?>" class="button"><?php esc_html_e( 'Manual Reminder', 'woocommerce-booking' ); ?></a>
		</div>
		<table id="bkap_reminder_data" class="bkap_table_data">
			<thead>
				<tr>
					<th scope="col" width="40%"><span class=""><?php esc_html_e( 'Reminder Title', 'woocommerce-booking' ); ?></span></th>
					<th scope="col" width="20%"><?php esc_html_e( 'Status', 'woocommerce-booking' ); ?></th>
					<th scope="col" width="30%"><?php esc_html_e( 'Send time after/before Booking Date', 'woocommerce-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'woocommerce-booking' ); ?></th>
				<tr>
			</thead>
			<tbody>

			<?php
			$reminder_posts = $bkap_vendors->get_reminder_data( $vendor_id, $cur_page, $rec_per_page, array( 's' => $search_term ) );

			if ( is_array( $reminder_posts ) && count( $reminder_posts ) > 0 && false !== $reminder_posts ) {
				foreach ( $reminder_posts as $key => $reminder_post ) {
					$reminder_id        = $reminder_post->ID;
					$reminder           = new BKAP_Reminder( $reminder_id );
					$reminder_status    = $reminder->get_status_name();
					$time_before_after_booking = $reminder->get_reminder_time_before_after_booking();
					$reminder_name      = get_the_title( $reminder_id );
					$reminder_link      = apply_filters( 'bkap_reminder_link_on_front_end', get_edit_post_link( $reminder_id ), $reminder_id, $manage_reminder_url );
					
					$reminder_edit      = '<a href="' . $reminder_link . '" >#' . $reminder_id . ' - ' . $reminder_name . '</a>';
					$reminder_edit_icon = '<a href="' . $reminder_link . '" title="' . esc_attr__( 'Edit Reminder', 'woocommerce-booking' ) . '"><i class="fas fa-edit"></i></a>';
					$reminder_date      = get_the_date( '', $reminder_id );
					?>
					<tr>
						<td scope="row" data-label="<?php esc_html_e( 'Reminders', 'woocommerce-booking' ); ?>"><span class=""><?php echo $reminder_edit; //phpcs:ignore ?></span></td>
						<td data-label="<?php esc_html_e( 'Status', 'woocommerce-booking' ); ?>"><?php esc_html_e( $reminder_status ); //phpcs:ignore?></td>
						<td data-label="<?php esc_html_e( 'Send time after/before Booking Date', 'woocommerce-booking' ); ?>"><?php esc_html_e( $time_before_after_booking ); //phpcs:ignore?></td>
						<td data-label="<?php esc_html_e( 'Actions', 'woocommerce-booking' ); ?>"><?php echo $reminder_edit_icon; //phpcs:ignore?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<h3><?php esc_html_e( 'No Reminders found.', 'woocommerce-booking' ); ?></h3>
				<?php
			}
			?>
			</tbody>
		</table>
	<?php

	// Bottom pagination.
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
		echo join( "</li>\n\t<li>", $page_links ); //phpcs:ignore
		echo "</li>\n</ul>\n";
		echo '</div>';
	endif;
	?>
	</div>
	<?php
} else {
	?>
	<h3><?php esc_html_e( 'No Reminder found.', 'woocommerce-booking' ); ?></h3>
	<?php
}
