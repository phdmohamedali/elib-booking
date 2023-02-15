import { __ } from '@wordpress/i18n';
/**
 * Options data for various inputs
 */
const wooBookingOptions = {};

// Style
wooBookingOptions.views = [
	{ value: 'list', label: __( 'List', 'woocommerce-booking' ) },
	{ value: 'calendar', label: __( 'Calendar', 'woocommerce-booking' ) },
];

// Filter
wooBookingOptions.filters = [
	{ value: 'all', label: __( 'All Products', 'woocommerce-booking' )  },
	{ value: 'type', label: __( 'Booking Type', 'woocommerce-booking' ) },
	{ value: 'products', label: __( 'Specific Products', 'woocommerce-booking' ) },
	{ value: 'categories', label: __( 'Specific Categories', 'woocommerce-booking' ) },
	{ value: 'resources', label: __( 'Resource Based', 'woocommerce-booking' ) },
];

// Types
wooBookingOptions.types = [
	{ value: 'day', label: __( 'Day', 'woocommerce-booking' ) },
	{ value: 'time', label: __( 'Time', 'woocommerce-booking' ) },
	{ value: 'multiple', label: __( 'Multiple', 'woocommerce-booking' ) },
];

wooBookingOptions.dayTypes = [
	{ value: 'only_day', label: __( 'Single', 'woocommerce-booking' ) },
	{ value: 'multiple_days', label: __( 'Multiple', 'woocommerce-booking' ) },
];

wooBookingOptions.timeTypes = [
	{ value: 'date_time', label: __( 'Fixed', 'woocommerce-booking' ) },
	{ value: 'duration_time', label: __( 'Duration', 'woocommerce-booking' ) },
];

wooBookingOptions.multipleTypes = [
	{ value: 'multidates', label: __( 'Dates', 'woocommerce-booking' ) },
	{ value: 'multidates_fixedtime', label: __( 'Dates & Fixed Time', 'woocommerce-booking' ) },
];

// Duration
wooBookingOptions.durations = [
	{ value: 'day', label: __( 'Day', 'woocommerce-booking' ) },
	{ value: 'week', label: __( 'Week', 'woocommerce-booking' ) },
	{ value: 'month', label: __( 'Month', 'woocommerce-booking' ) },
];

// Single Day Event Position
wooBookingOptions.duration = [
	{ value: 'start', label: __( 'Start', 'woocommerce-booking' ) },
	{ value: 'end', label: __( 'End', 'woocommerce-booking' ) },
];

export default wooBookingOptions;
