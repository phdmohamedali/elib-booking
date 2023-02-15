<?php

/**
 * Search Widget Form
 *
 * Template for Search Widget Form. This template will show the Search Widget Form on the front end of the website
 *
 * @author      Tyche Softwares
 * @package     Bookings & Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<div id="wrapper" class="sbkap_table">
	<form role="search" method="get" id="searchform" action="<?php echo $action; ?>">
		<input 	type="hidden"
				id="w_allow_category"
				name="w_allow_category"
				value="<?php echo $allow_category_search; ?>">
		<?php echo $bkap_language; ?>
		<input 	type="hidden"
				id="w_allow_resource"
				name="w_allow_resource"
				value="<?php echo $allow_resource_search; ?>">
		<?php echo $bkap_language; ?>
		<div class="sbkap_row">
			<div class="sbkap_cell">
				<p><?php echo $start_date; ?>&nbsp;</p>
			</div>
			<div class="sbkap_cell">
				<p>
					<input id="w_check_in" name="w_check_in" style="width:160px" value="<?php echo $start_date_value; ?>" type="text" readonly/>
					<input type="hidden" id="w_checkin" name="w_checkin" value="<?php echo $session_start_date; ?>">
				</p>
			</div>
		</div>
		<div class="sbkap_row" style= "display:<?php echo $hide_checkout_field; ?>">
			<div class="sbkap_cell">
				<p><?php echo $end_date; ?>&nbsp;</p>
			</div>
			<div class="sbkap_cell">
				<p>
					<input id="w_check_out" name="w_check_out" style="width:160px" value="<?php echo $end_date_value; ?>" type="text"  readonly/>
					<input type="hidden" id="w_checkout" name="w_checkout" value="<?php echo $session_end_date; ?>">
				</p>
			</div>
		</div>
		<div class="sbkap_row" style="display: <?php echo $hide_resource_filter; ?>">
			<div class="sbkap_cell">
				<p><?php echo $resource_label; ?></p>
			</div>
			<div class="sbkap_cell">
				<p><?php echo $resource_contents; ?></p>
			</div>
		</div>
		<div class="sbkap_row" style="display: <?php echo $hide_category_filter; ?>">
			<div class="sbkap_cell">
				<p><?php echo $category_label; ?></p>
			</div>
			<div class="sbkap_cell">
				<p><?php echo $contents; ?></p>
			</div>
		</div>
		<div class="" style= "text-align: center;">
			<div class="sbkap_cell">
				<p><input type="submit" id="bkap_search" value="<?php echo $search_label; ?>" disabled="disabled" /></p>
			</div>
			<div class="sbkap_cell">
				<p><input type="button" id="bkap_clear" value="<?php echo $clear_label; ?>" style="display: <?php echo $clear_button_css; ?>" /></p>
			</div>
		</div>
		<?php echo $text_information; ?>
	</form>
</div>
