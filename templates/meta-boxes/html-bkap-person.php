<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$person_id = absint( $person->get_id() );

?>
<tr class="bkap_person_row">
	
	<td class="bkap_person_title" width="25%">
		<input type="text" name="person_name[<?php echo $loop; ?>]" style="width:100%" value="<?php echo esc_attr( $person->get_title() ); ?>" />
		<input type="hidden" name="person_id[<?php echo $loop; ?>]" value="<?php echo esc_attr( $person->get_id() ); ?>" />

	</td>

	<td class="bkap_person_field" width="15%">
		<input type="number" class="" name="person_base_cost[<?php echo $loop; ?>]" style="width:100%" value="<?php echo $person_data['base_cost']; ?>" placeholder="0.00" min="0" step="0.01" />		
	</td>

	<td class="bkap_person_field" width="15%">
		<input type="number" class="" name="person_block_cost[<?php echo $loop; ?>]" style="width:100%" value="<?php echo $person_data['block_cost']; ?>" placeholder="0.00" min="0" step="0.01" />
	</td>

	<td class="bkap_person_field" width="10%">
		<input type="number" class="" name="person_min[<?php echo $loop; ?>]" style="width:100%" value="<?php echo $person_data['person_min']; ?>" placeholder="0" min="0" max="9999" step="1" />
	</td>

	<td class="bkap_person_field" width="10%">
		<input type="number" class="" name="person_max[<?php echo $loop; ?>]" style="width:100%" value="<?php echo $person_data['person_max']; ?>" placeholder="0" min="0" max="9999" step="1" />
	</td>

	<td class="bkap_person_field" width="25%">
		<input type="text" class="" name="person_desc[<?php echo $loop; ?>]" style="width:100%" value="<?php echo $person_data['person_desc']; ?>"/>
	</td>

	<td class="bkap_remove_person" id="bkap_remove_person_<?php echo esc_attr( $person_id ); ?>">
		<i class="fa fa-trash" aria-hidden="true"></i>
	</td>
</tr>
