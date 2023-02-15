/**
 * This function allows to dismiss the notices which are shown from the plugin.
 *
 * @namespace bkap_notice_dismissible
 * @since 6.8
 * @since Update 5.12.0
 */
jQuery( document ).ready( function() {

	jQuery( '.notice.is-dismissible' ).each( function() {

		let $this = jQuery( this ),
			data;

		if ( $this.hasClass( 'bkap-meeting-notice' ) || $this.hasClass( 'bkap-tracker' ) || $this.hasClass( 'bkap-timeslot-notice' ) ) {

			$button = $this.find( 'button' );

			// Zoom Meeting notice.
			data = {
				notice: 'bkap-meeting-notice',
				action: 'bkap_dismiss_admin_notices'
			}

			// BKAP TS tracking notice.
			if ( $this.hasClass( 'bkap-tracker' ) ) {
				data = {
					action: 'bkap_admin_notices'
				}
			}

			// Dismiss Booking timeslot list view feature notice.
			if ( $this.hasClass( 'bkap-timeslot-notice' ) ) {
				data = {
					notice: 'bkap-timeslot-notice',
					action: 'bkap_dismiss_admin_notices'
				}
			}

			if ( $button.length > 0 ) {

				$button.each( function() {

					// Fix #4743: Remove previous event listeners that have been previously configured as they usually conflict.
					jQuery( this ).unbind();

					$button.on( 'click.notice-dismiss', function( event ) {
						event.preventDefault();
						$this.fadeTo( 100, 0, function() {
							jQuery( this ).slideUp( 100, function() {
								jQuery( this ).remove();
								jQuery.post( bkap_ts_dismiss_notice.ts_admin_url, data, function( response ) {} );
							} );
						} );
					} );
				} );
			}
		}
	} );
} );
