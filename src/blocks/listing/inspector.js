/**
 * External dependencies
 */
import Select from 'react-select';

/**
 * Wordpress dependencies
 */
import { withSelect } from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	PanelBody,
	ButtonGroup,
	Button,
	IconButton,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import wooBookingOptions from './options';

/**
 * Render Inspector UI
 */
const Inspector = ( { attributes, setAttributes, bookables, bookableResources, productCategories } ) => {
	const {
		view,
		filter,
		type,
		dayType,
		timeType,
		multipleType,
		products,
		categories,
		resources,
		duration,
		showQuantity,
		showTimes,
		showNavigation,
		sortingSingleEvents,
		greyOutBooked,
	} = attributes;

	const saveAttribute = ( name, value ) => {
		setAttributes( {
			[ name ]: value,
		} );
	};

	const getOptions = ( data ) => {
		let options = [];
		if ( data ) {
			options = data.map(
				( { id, title, name } ) => {
					return {
						value: id,
						label: title ? title.rendered : name,
					};
				}
			);
		}

		return options;
	};

	return (
		<InspectorControls>
			<PanelBody className="bkap_settings" title="Filter Settings">

				<BaseControl id="booking-filter" label="Filter" className="wrapper-react-select wrs-z-3">
					<Select
						isSearchable={ false }
						options={ wooBookingOptions.filters }
						value={ filter }
						onChange={ ( value ) => saveAttribute( 'filter', value ) } />
				</BaseControl>

				{
					'type' === filter.value &&
					<BaseControl id="booking-type" label="Booking Type">
						<ButtonGroup aria-label="Booking Type">
							{
								wooBookingOptions.types.map( ( bookingType ) => {
									return (
										<Button
											key={ bookingType.label }
											isLarge
											isPrimary={ type === bookingType.value }
											aria-pressed={ type === bookingType.value }
											onClick={ () => saveAttribute( 'type', bookingType.value ) }>
											{ bookingType.label }
										</Button>
									);
								} )
							}
						</ButtonGroup>
					</BaseControl>
				}

				{
					'type' === filter.value && 'day' === type &&
					<BaseControl id="day-type" label="Day Type">
						<ButtonGroup aria-label="Day Type">
							{
								wooBookingOptions.dayTypes.map( ( dType ) => {
									return (
										<Button
											key={ dType.label }
											isLarge
											isPrimary={ dayType === dType.value }
											aria-pressed={ dayType === dType.value }
											onClick={ () => saveAttribute( 'dayType', dType.value ) }>
											{ dType.label }
										</Button>
									);
								} )
							}
						</ButtonGroup>
					</BaseControl>
				}

				{
					'type' === filter.value && 'time' === type &&
					<BaseControl id="date-type" label="Time Type">
						<ButtonGroup aria-label="Time Type">
							{
								wooBookingOptions.timeTypes.map( ( tType ) => {
									return (
										<Button
											key={ tType.label }
											isLarge
											isPrimary={ timeType === tType.value }
											aria-pressed={ timeType === tType.value }
											onClick={ () => saveAttribute( 'timeType', tType.value ) }>
											{ tType.label }
										</Button>
									);
								} )
							}
						</ButtonGroup>
					</BaseControl>
				}

{
					'type' === filter.value && 'multiple' === type &&
					<BaseControl id="multiple-type" label="Multiple Type">
						<ButtonGroup aria-label="Multiple Type">
							{
								wooBookingOptions.multipleTypes.map( ( mType ) => {
									return (
										<Button
											key={ mType.label }
											isLarge
											isPrimary={ multipleType === mType.value }
											aria-pressed={ multipleType === mType.value }
											onClick={ () => saveAttribute( 'multipleType', mType.value ) }>
											{ mType.label }
										</Button>
									);
								} )
							}
						</ButtonGroup>
					</BaseControl>
				}

				{
					'products' === filter.value &&
					<BaseControl id="booking-products" label="Products" className="wrapper-react-select">
						<Select
							isMulti
							isClearable
							options={ getOptions( bookables ) }
							value={ products }
							onChange={ ( value ) => saveAttribute( 'products', value ) } />
					</BaseControl>
				}

				{
					'categories' === filter.value &&
					<BaseControl id="booking-categories" label="Categories" className="wrapper-react-select">
						<Select
							isMulti
							isClearable
							options={ getOptions( productCategories ) }
							value={ categories }
							onChange={ ( value ) => saveAttribute( 'categories', value ) } />
					</BaseControl>
				}

				{
					'resources' === filter.value &&
					<BaseControl id="booking-resources" label="Resource Type" className="wrapper-react-select">
						<Select
							isMulti
							isClearable
							options={ getOptions( bookableResources ) }
							value={ resources }
							onChange={ ( value ) => saveAttribute( 'resources', value ) } />
					</BaseControl>
				}

			</PanelBody>
			
			<PanelBody className="bkap_settings" title="Display Settings">
				<BaseControl id="display-type" label="Style">
					<ButtonGroup aria-label="Display Type">
						{
							wooBookingOptions.views.map( ( listingView ) => {
								return (
									<IconButton
										key={ listingView.label }
										label={ listingView.label }
										icon={ 'list' === listingView.value ? 'list-view' : 'calendar-alt' }
										isLarge
										isPrimary={ view === listingView.value }
										aria-pressed={ view === listingView.value }
										onClick={ () => saveAttribute( 'view', listingView.value ) }
									/>
								);
							} )
						}
					</ButtonGroup>
				</BaseControl>

				<BaseControl id="listing-duration" label="Duration">
					<ButtonGroup aria-label="Duration">
						{
							wooBookingOptions.durations.map( ( bDuration ) => {
								return (
									<Button
										key={ bDuration.label }
										isLarge
										isPrimary={ duration === bDuration.value }
										aria-pressed={ duration === bDuration.value }
										onClick={ () => saveAttribute( 'duration', bDuration.value ) }>
										{ bDuration.label }
									</Button>
								);
							} )
						}
					</ButtonGroup>
				</BaseControl>

				<ToggleControl
					name="Sort Single Day"
					label="Sort Single Day"
					checked={ !! sortingSingleEvents }
					onChange={ ( value ) => saveAttribute( 'sortingSingleEvents', value ) } />

				{
					'list' === view &&
						<ToggleControl
							name="showQuantity"
							label="Show Quantity"
							checked={ !! showQuantity }
							onChange={ ( value ) => saveAttribute( 'showQuantity', value ) } />
				}

				<ToggleControl
					name="showTimes"
					label="Show Times"
					checked={ !! showTimes }
					onChange={ ( value ) => saveAttribute( 'showTimes', value ) } />

				<ToggleControl
					name="showNavigation"
					label="Show Navigation"
					checked={ !! showNavigation }
					onChange={ ( value ) => saveAttribute( 'showNavigation', value ) } />

				<ToggleControl
					name="greyOutBooked"
					label="Grey Out Booked Date"
					checked={ !! greyOutBooked }
					onChange={ ( value ) => saveAttribute( 'greyOutBooked', value ) }
					help="no editor preview" />
			</PanelBody>

		</InspectorControls>
	);
};

/**
 * Export with forms data
 */
export default withSelect( ( select ) => {
	return {
		bookables: select( 'core' ).getEntityRecords( 'postType', 'product', {
			per_page: -1,
			metaKey: '_bkap_enable_booking',
			metaValue: 'on',
		} ),
		bookableResources: select( 'core' ).getEntityRecords( 'postType', 'bkap_resource', { per_page: -1 } ),
		productCategories: select( 'core' ).getEntityRecords( 'taxonomy', 'product_cat', { per_page: -1 } ),
	};
} )( Inspector );
