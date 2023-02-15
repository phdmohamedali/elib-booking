/**
 * Wordpress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ListingEdit from './edit';

export default registerBlockType( 'tyche/list-booking', {
	title: __( 'Available Bookings Block', 'woocommerce-booking' ),
	description: __( 'List all available bookings.', 'woocommerce-booking' ),
	icon: 'calendar',
	category: 'widgets',
	keywords: [
		'tyche',
		'woocommerce',
		'booking',
	],
	example:{},
	supports: {
		html: false,
	},
	edit: ListingEdit,
	save: () => {
		return null;
	},
} );
