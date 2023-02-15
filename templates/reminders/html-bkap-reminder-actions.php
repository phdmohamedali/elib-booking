<ul class="reminder_actions submitbox">

<?php do_action( $prefix  . '_reminder_actions_start', $post_id ); ?>

	<li class="wide" id="actions">
		<label for="<?php echo $prefix ; ?>_reminder_action"><?php echo __( 'Status: ', 'woocommerce-booking' ); ?></label>
		<select name="<?php echo $prefix ; ?>_reminder_action">
			<?php foreach ( $reminder_actions as $action => $title ) { ?>
				<option value="<?php echo esc_attr( $action ); ?>" <?php echo ( $action == $reminder_status ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $title ); ?></option>
			<?php } ?>
		</select>
	</li>

	<li class="wide" style="padding-bottom: 16px;">
		<div id="delete-action">
		<?php
			if ( current_user_can( 'delete_post', $post_id ) ) {

				if ( ! EMPTY_TRASH_DAYS ) {
					$delete_text = __( 'Delete permanently', 'woocommerce' );
				} else {
					$delete_text = __( 'Move to Trash', 'woocommerce' );
				}
				?>
				<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post_id ) ); ?>"><?php echo esc_html( $delete_text ) ?></span></a>
				<?php
			}
			?>
		</div>
		<div id="publishing-action">
			<input type="submit" class="button save_reminder button-primary" name="<?php echo $prefix ; ?>_reminder" value="<?php echo 'auto-draft' === $post->post_status ? esc_attr__( 'Save Settings', 'woocommerce' ) : esc_attr__( 'Update Settings', 'woocommerce' ); ?>">
		</div>
	</li>

<?php do_action( $prefix . '_reminder_actions_end', $post_id ); ?>

</ul>