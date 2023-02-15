<?php
/**
 * The template is for listing the Resources on front end.
 *
 * @package BKAP/Wcfm-Marketplace-List-Resource
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
$search_term  = isset( $_POST['resource-search'] ) && '' !== $_POST['resource-search'] ? sanitize_text_field( wp_unslash( $_POST['resource-search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

// Get the number of pages.
$page_count = $bkap_vendors->get_number_of_pages( $vendor_id, $rec_per_page, 'bkap_resource', array( 's' => $search_term ) );
if ( 1 < $pagenum ) {
	$cur_page = $pagenum;
}

// Remove the pagenum query variable base url if it already exists.
$pagenum_pos = strpos( $base_url, 'pagenum' );
if ( false !== $pagenum_pos ) {
	$base_url = substr( $base_url, 0, ( $pagenum_pos - 1 ) );
}

if ( 0 < $page_count ) {

	$add_resource_link = $manage_resource_url . '?bkap-resource=new';
	?>
	<div id="bkap_all_resources">
		<div class="search-box">
		<a href="<?php esc_attr_e( $add_resource_link ); //phpcs:ignore ?>" class="button"><?php esc_html_e( 'Add Resource', 'woocommerce-booking' ); ?></a>
			<form method="POST" style="float:right;">
				<label class="screen-reader-text" for="resource-search"><?php esc_html_e( 'Search Resource:', 'woocommerce-booking' ); ?></label>
				<input type="text" id="resource-search" name="resource-search" value="<?php esc_attr_e( $search_term ); //phpcs:ignore ?>">
				<input type="submit" id="resource-search-submit" name="resource-search-submit" class="button" value="Search Resource">
			</form>
		</div>
		<table id="bkap_resource_data" class="bkap_table_data">
			<thead>
				<tr>
					<th scope="col" width="60%"><span class=""><?php esc_html_e( 'Resources', 'woocommerce-booking' ); ?></span></th>
					<th scope="col"><?php esc_html_e( 'Date', 'woocommerce-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'woocommerce-booking' ); ?></th>
				<tr>
			</thead>
			<tbody>

			<?php
			$resource_posts = $bkap_vendors->get_resource_data( $vendor_id, $cur_page, $rec_per_page, array( 's' => $search_term ) );
			if ( is_array( $resource_posts ) && count( $resource_posts ) > 0 && false !== $resource_posts ) {
				foreach ( $resource_posts as $key => $resource_data ) {
					$resource_id        = $resource_data->ID;
					$resource_name      = get_the_title( $resource_id );
					$resource_link      = apply_filters( 'bkap_resource_link_on_front_end', get_edit_post_link( $resource_id ), $resource_id, $manage_resource_url );
					$resource_edit      = '<a href="' . $resource_link . '" >#' . $resource_id . ' - ' . $resource_name . '</a>';
					$resource_edit_icon = '<a href="' . $resource_link . '" title="' . esc_attr__( 'Edit Resource', 'woocommerce-booking' ) . '"><i class="fas fa-edit"></i></a>';
					$resource_date      = get_the_date( '', $resource_id );
					?>
					<tr>
						<td scope="row" data-label="<?php esc_html_e( 'Resources', 'woocommerce-booking' ); ?>"><span class=""><?php echo $resource_edit; //phpcs:ignore ?></span></td>
						<td data-label="<?php esc_html_e( 'Date', 'woocommerce-booking' ); ?>"><?php esc_html_e( $resource_date ); //phpcs:ignore?></td>
						<td data-label="<?php esc_html_e( 'Actions', 'woocommerce-booking' ); ?>"><?php echo $resource_edit_icon; //phpcs:ignore?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<h3><?php esc_html_e( 'No Resources found.', 'woocommerce-booking' ); ?></h3>
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
	<h3><?php esc_html_e( 'No Resources found.', 'woocommerce-booking' ); ?></h3>
	<?php
}
