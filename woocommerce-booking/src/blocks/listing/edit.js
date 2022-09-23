/**
 * External dependencies
 */
import FullCalendar from '@fullcalendar/react';
import rrulePlugin from '@fullcalendar/rrule';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';

/**
 * Wordpress dependencies
 */
import { createRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Inspector from './inspector';

/**
 * Render Block UI For Editor
 *
 * @param {Object} props
 */
const ListingEdit = ( props ) => {
	const { attributes } = props;
	const calendarRef = createRef();

	const getAttributeValues = ( attribute ) => {
		const items = [];
		attribute.forEach( ( item ) => {
			items.push( item.value );
		} );

		return items.join();
	};

	const capitalizeFirstLetter = ( string ) => {
		return string.charAt( 0 ).toUpperCase() + string.slice( 1 );
	};

	// Note: Fix for fullcalendar extraParams not passing object properly.
	const params = {
		view: attributes.view,
		filter: attributes.filter.value,
		type: attributes.type,
		dayType: attributes.dayType,
		timeType: attributes.timeType,
		products: attributes.products && getAttributeValues( attributes.products ),
		categories: attributes.categories && getAttributeValues( attributes.categories ),
		resources: attributes.resources && getAttributeValues( attributes.resources ),
		duration: attributes.duration,
		showQuantity: attributes.showQuantity,
		showTimes: attributes.showTimes,
		showNavigation: attributes.showNavigation,
		sortingSingleEvents: attributes.sortingSingleEvents,
		greyOutBooked: attributes.greyOutBooked,
	};

	const withNav = {
		left: 'title',
		center: '',
		right: 'prev,next',
	};

	const noNav = {
		left: 'title',
		center: '',
		right: '',
	};

	const view = 'list' === attributes.view ? `list${ capitalizeFirstLetter( attributes.duration ) }` : `dayGrid${ capitalizeFirstLetter( attributes.duration ) }`;

	return (
		<div className="bkap_list_booking">
			<div className="tyche_loader">
				<img src={ `${ bkap_data.plugin_url }assets/images/reschedule-save.gif` } alt="Loading" />
			</div>
			<FullCalendar
				noEventsMessage= { bkap_data.no_bookable_slot }
				ref={ calendarRef }
				timeZone="UTC"
				plugins={ [ rrulePlugin, dayGridPlugin, listPlugin ] }
				header={ attributes.showNavigation ? withNav : noNav }
				defaultView={ view }
				loading={ ( isLoading ) => {
					if ( ! isLoading ) {
						if ( calendarRef.current != null ) {
							const calendarApi = calendarRef.current.getApi();
							calendarApi.changeView( view );
						}
					}
					bkapListing.loading( isLoading );
				} }
				events={ {
					url: `?bkap_events_feed=json&bkap_view=${ attributes.view }`,
					method: 'POST',
					extraParams: params,
				} }
				eventOrder="sort, start,-duration,allDay,title"
				eventRender={ ( info ) => {
					bkapListing.eventRender( info, bkap_data.is_admin );
				} }
				allDayText={ bkap_data.full_day_text }
				firstDay= { parseInt( bkap_data.first_day ) }
				locale={ bkap_data.lang }
			/>
			<Inspector { ... { ...props } } />
		</div>
	);
};

export default ListingEdit;
