<?php

?>
<section class="bkap-automatic">
	<div class="wrap">
		<h2><?php esc_html_e( $bkap_heading ); ?></h2>
		<div id="content">
			<form method="post" action="options.php">
					<?php
						settings_errors();
						settings_fields( 'bkap_reminder_settings' );
						do_settings_sections( 'booking_reminder_page' );
						submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save_reminder', true );
					?>
			</form>
		</div>
	</div>
</section>
<hr>
