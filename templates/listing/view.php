<?php
/**
 * Listing View.
 *
 * @package BKAP/view
 */
?>

<!-- Root Element For Listing -->
<div id="<?php echo esc_attr( $id ); ?>" class="bkap_list_booking">
	<div class="tyche_loader">
		<img src="<?php echo esc_url( plugins_url( '/woocommerce-booking/assets/images/reschedule-save.gif' ) ); ?>" alt="Loading">
	</div>
</div>

<!-- Config Booking Listing -->
<script type="text/javascript">

	let max_date = '';

	jQuery( document ).ready(function($) {
		const calendarEl = document.getElementById( '<?php echo $id; ?>' );
		var <?php echo $id; ?> = new FullCalendar.Calendar(calendarEl, {
			noEventsMessage: bkap_data.no_bookable_slot,
			nextDayThreshold : '23:00:00',
			plugins: [ 'rrule', 'dayGrid','list' ],
			header: <?php echo wp_json_encode( $header ); ?>,
			defaultView: '<?php echo $default_view; ?>',
			loading: function (isLoading) {
				bkapListing.loading( isLoading, bkap_data.is_admin );
			},
			events: {
				url: '?bkap_events_feed=json&bkap_view=<?php echo $view; ?>',
				method: 'POST',
				extraParams: <?php echo wp_json_encode( $attributes ); ?>,
			},
			eventOrder: 'sort, start,-duration,allDay,title',
			eventRender: function(info) {

				if ( '' != info.event ) {
					if ( 'undefined' !== typeof info.event.extendedProps.last_event_date ) {
						max_date = info.event.extendedProps.last_event_date;
					}

					bkapListing.eventRender( info, bkap_data.is_admin );
				}
			},
			allDayText: bkap_data.full_day_text,
			datesRender: function( info ) {
				let start = info.view.currentStart;

				// Some little delay so as to ensure max_date is available.
				setTimeout( function() {

					if ( moment() >= start ) {
						$( ".fc-prev-button" ).prop( 'disabled', true );
						$( ".fc-prev-button" ).addClass( 'fc-state-disabled' );
					} else {
						$( ".fc-prev-button" ).removeClass( 'fc-state-disabled' );
						$( ".fc-prev-button" ).prop( 'disabled', false );
					}

					let next_month = moment( start ).add( 1, 'M' ).format( 'YYYY-MM-DD' ) + '  23:59:00';

					if ( '' !== max_date && next_month > max_date ) {
						$( ".fc-next-button" ).prop( 'disabled', true );
						$( ".fc-next-button" ).addClass( 'fc-state-disabled' );
					} else {
						$( ".fc-next-button" ).removeClass( 'fc-state-disabled' );
						$( ".fc-next-button" ).prop( 'disabled', false );
					}
				}, 5000 );
			},
			firstDay: parseInt( bkap_data.first_day ),
			eventClick: function( info ) { // Adding date param to url when Calendar view
				let cal_view     = [ 'dayGridMonth', 'dayGridWeek', 'dayGridDay' ];
				let view_type    = info.view.type;
				let clicked_date = moment( info.event.start ).format( 'YYYY-MM-DD' );
				if ( info.event.url ) {
					if( cal_view.includes( view_type ) ) {
						window.open( info.event.url + '?bkap_date=' + clicked_date );
					}
				}
			},
			locale: bkap_data.lang,
		});
		<?php echo $id; ?>.render();
	});
</script>
