<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * File containing global variables used throughout the plugin.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Global-Function
 */

/**
 * Bookable Recurring Weekdays
 */
$bkap_weekdays = array(
	'booking_weekday_0' => __( 'Sunday', 'woocommerce-booking' ),
	'booking_weekday_1' => __( 'Monday', 'woocommerce-booking' ),
	'booking_weekday_2' => __( 'Tuesday', 'woocommerce-booking' ),
	'booking_weekday_3' => __( 'Wednesday', 'woocommerce-booking' ),
	'booking_weekday_4' => __( 'Thursday', 'woocommerce-booking' ),
	'booking_weekday_5' => __( 'Friday', 'woocommerce-booking' ),
	'booking_weekday_6' => __( 'Saturday', 'woocommerce-booking' ),
);

/**
 * Available Weekdays for Start/End of Fixed blocks
 */
$bkap_fixed_days = array(
	'any_days' => __( 'Any Days', 'woocommerce-booking' ),
	'0'        => __( 'Sunday', 'woocommerce-booking' ),
	'1'        => __( 'Monday', 'woocommerce-booking' ),
	'2'        => __( 'Tuesday', 'woocommerce-booking' ),
	'3'        => __( 'Wednesday', 'woocommerce-booking' ),
	'4'        => __( 'Thursday', 'woocommerce-booking' ),
	'5'        => __( 'Friday', 'woocommerce-booking' ),
	'6'        => __( 'Saturday', 'woocommerce-booking' ),
);

/**
 * Available timeslot view modes.
 */
$bkap_timeslot_display_modes = array(
	'dropdown-view' => __( 'Dropdown View', 'woocommerce-booking' ),
	'list-view'     => __( 'List View', 'woocommerce-booking' ),
);

/**
 * Weekdays numbered starting from 0 to 6 for Sunday through Saturday
 */
$bkap_days = array(
	'0' => 'Sunday',
	'1' => 'Monday',
	'2' => 'Tuesday',
	'3' => 'Wednesday',
	'4' => 'Thursday',
	'5' => 'Friday',
	'6' => 'Saturday',
);

/**
 * Months of the year
 */
$bkap_months = array(
	'1'  => __( 'January', 'woocommerce-booking' ),
	'2'  => __( 'February', 'woocommerce-booking' ),
	'3'  => __( 'March', 'woocommerce-booking' ),
	'4'  => __( 'April', 'woocommerce-booking' ),
	'5'  => __( 'May', 'woocommerce-booking' ),
	'6'  => __( 'June', 'woocommerce-booking' ),
	'7'  => __( 'July', 'woocommerce-booking' ),
	'8'  => __( 'August', 'woocommerce-booking' ),
	'9'  => __( 'September', 'woocommerce-booking' ),
	'10' => __( 'October', 'woocommerce-booking' ),
	'11' => __( 'November', 'woocommerce-booking' ),
	'12' => __( 'December', 'woocommerce-booking' ),
);

/**
 * Available Ranges for setup in Booking->Availability
 */
$bkap_dates_months_availability = array(
	'custom_range'    => __( 'Custom Range', 'woocommerce-booking' ),
	'specific_dates'  => __( 'Specific Dates', 'woocommerce-booking' ),
	'range_of_months' => __( 'Range of Months', 'woocommerce-booking' ),
	'holidays'        => __( 'Holidays', 'woocommerce-booking' ),
);


$bkap_from_slot_hrs = array();
$bkap_from_slot_min = array();
$bkap_to_slot_hrs   = array();
$bkap_to_slot_min   = array();
$bkap_time_note     = array();
$bkap_lockout_time  = array();

/**
 * Languages
 */
$bkap_languages = array(
	'af'    => 'Afrikaans',
	'ar'    => 'Arabic',
	'ar-DZ' => 'Algerian Arabic',
	'az'    => 'Azerbaijani',
	'id'    => 'Indonesian',
	'ms'    => 'Malaysian',
	'nl-BE' => 'Dutch Belgian',
	'bs'    => 'Bosnian',
	'bg'    => 'Bulgarian',
	'ca'    => 'Catalan',
	'cs'    => 'Czech',
	'cy-GB' => 'Welsh',
	'da'    => 'Danish',
	'de'    => 'German',
	'et'    => 'Estonian',
	'el'    => 'Greek',
	'en-AU' => 'English Australia',
	'en-NZ' => 'English New Zealand',
	'en-GB' => 'English UK',
	'es'    => 'Spanish',
	'eo'    => 'Esperanto',
	'eu'    => 'Basque',
	'fo'    => 'Faroese',
	'fr'    => 'French',
	'fr-CH' => 'French Swiss',
	'gl'    => 'Galician',
	'sq'    => 'Albanian',
	'ko'    => 'Korean',
	'he'    => 'Hebrew',
	'hi'    => 'Hindi India',
	'hr'    => 'Croatian',
	'hy'    => 'Armenian',
	'is'    => 'Icelandic',
	'it'    => 'Italian',
	'ka'    => 'Georgian',
	'km'    => 'Khmer',
	'lv'    => 'Latvian',
	'lt'    => 'Lithuanian',
	'mk'    => 'Macedonian',
	'hu'    => 'Hungarian',
	'ml'    => 'Malayam',
	'nl'    => 'Dutch',
	'ja'    => 'Japanese',
	'no'    => 'Norwegian',
	'th'    => 'Thai',
	'pl'    => 'Polish',
	'pt'    => 'Portuguese',
	'pt-BR' => 'Portuguese Brazil',
	'ro'    => 'Romanian',
	'rm'    => 'Romansh',
	'ru'    => 'Russian',
	'sk'    => 'Slovak',
	'sl'    => 'Slovenian',
	'sr'    => 'Serbian',
	'fi'    => 'Finnish',
	'sv'    => 'Swedish',
	'ta'    => 'Tamil',
	'vi'    => 'Vietnamese',
	'tr'    => 'Turkish',
	'uk'    => 'Ukrainian',
	'zh-HK' => 'Chinese Hong Kong',
	'zh-CN' => 'Chinese Simplified',
	'zh-TW' => 'Chinese Traditional',
);

/**
 * Date formats for booking.
 */
$bkap_date_formats = array(
	'mm/dd/y'      => 'm/d/y',
	'dd/mm/y'      => 'd/m/y',
	'y/mm/dd'      => 'y/m/d',
	'dd.mm.y'      => 'd.m.y',
	'y.mm.dd'      => 'y.m.d',
	'yy-mm-dd'     => 'Y-m-d',
	'dd-mm-y'      => 'd-m-y',
	'd M, y'       => 'j M, y',
	'd M, yy'      => 'j M, Y',
	'd MM, y'      => 'j F, y',
	'd MM, yy'     => 'j F, Y',
	'DD, d MM, yy' => 'l, j F, Y',
	'D, M d, yy'   => 'D, M j, Y',
	'DD, M d, yy'  => 'l, M j, Y',
	'DD, MM d, yy' => 'l, F j, Y',
	'D, MM d, yy'  => 'D, F j, Y',
);

/**
 * Booking Time Formats
 */
$bkap_time_formats = array(
	'12' => __( '12 hour', 'woocommerce-booking' ),
	'24' => __( '24 hour', 'woocommerce-booking' ),
);

/**
 * Booking Calendar themes
 */
$bkap_calendar_themes = array(
	'smoothness'     => 'Smoothness',
	'ui-lightness'   => 'UI lightness',
	'ui-darkness'    => 'UI darkness',
	'start'          => 'Start',
	'redmond'        => 'Redmond',
	'sunny'          => 'Sunny',
	'overcast'       => 'Overcast',
	'le-frog'        => 'Le Frog',
	'flick'          => 'Flick',
	'pepper-grinder' => 'Pepper Grinder',
	'eggplant'       => 'Eggplant',
	'dark-hive'      => 'Dark Hive',
	'cupertino'      => 'Cupertino',
	'south-street'   => 'South Street',
	'blitzer'        => 'Blitzer',
	'humanity'       => 'Humanity',
	'hot-sneaks'     => 'Hot sneaks',
	'excite-bike'    => 'Excite Bike',
	'vader'          => 'Vader',
	'dot-luv'        => 'Dot Luv',
	'mint-choc'      => 'Mint Choc',
	'black-tie'      => 'Black Tie',
	'trontastic'     => 'Trontastic',
	'swanky-purse'   => 'Swanky Purse',
);
/**
 * Calendar Icons
 */
$bkap_calendar_icons = array(
	'calendar1.gif',
	'none',
);

global $bkap_calendar_themes, $bkap_time_formats, $bkap_date_formats, $bkap_languages, $bkap_days,
$bkap_weekdays, $bkap_calendar_icons, $bkap_timeslot_display_modes;


/**
 * This function return the array based on string passed for it.
 *
 * @since 1.7.0
 */

function bkap_get_book_arrays( $str ) {
	return $GLOBALS[$str];
}

