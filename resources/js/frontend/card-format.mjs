/**
 * Card field formatting for the block checkout, in place of Cleave.js.
 *
 * Cleave.js (archived upstream since 2022) threw "Cannot read properties of undefined
 * (reading 'slice')" and left the field empty whenever a value arrived without a key press:
 * browser card autofill, password managers and some mobile keyboards. These functions take
 * whatever is in the box and return the same shapes Cleave produced, which the server
 * already reads: "4242 4242 4242 4242", "MM/YY" and up to four CVC digits.
 */

const digits = ( value ) => String( value || '' ).replace( /\D/g, '' );

/**
 * Card number in groups: 4-6-5 for American Express, otherwise groups of four.
 *
 * @param {string} value What is in the box.
 * @return {string} Formatted number.
 */
export const formatCardNumber = ( value ) => {
	let d = digits( value );
	const amex = /^3[47]/.test( d );
	d = d.slice( 0, amex ? 15 : 19 );
	const groups = amex ? [ 4, 6, 5 ] : [ 4, 4, 4, 4, 3 ];
	const parts = [];
	let at = 0;
	for ( const size of groups ) {
		if ( at >= d.length ) {
			break;
		}
		parts.push( d.slice( at, at + size ) );
		at += size;
	}
	return parts.join( ' ' );
};

/**
 * Expiry as MM/YY. A single first digit above 1 is a month ("4" is "04"); a four-digit
 * year, as autofill can send ("12/2030"), is cut to two; months are kept to 01-12.
 *
 * @param {string} value What is in the box.
 * @return {string} Formatted expiry.
 */
export const formatExpiry = ( value ) => {
	const raw = String( value || '' );
	const slash = raw.match( /^\s*(\d{1,2})\s*\/\s*(\d{0,4})\s*$/ );
	let d;
	if ( slash ) {
		const month =
			slash[ 1 ].length === 1 && slash[ 2 ]
				? '0' + slash[ 1 ]
				: slash[ 1 ];
		d = month + slash[ 2 ].slice( -2 );
		if ( slash[ 2 ].length === 4 ) {
			d = month + slash[ 2 ].slice( 2 );
		}
	} else {
		d = digits( raw );
		if ( d.length === 6 && d.slice( 2, 4 ) === '20' ) {
			d = d.slice( 0, 2 ) + d.slice( 4 );
		}
	}
	if ( d.length && d[ 0 ] > '1' ) {
		d = '0' + d;
	}
	d = d.slice( 0, 4 );
	let month = d.slice( 0, 2 );
	if ( month.length === 2 ) {
		if ( month === '00' ) {
			month = '01';
		} else if ( month > '12' ) {
			month = '12';
		}
	}
	return d.length > 2 ? month + '/' + d.slice( 2 ) : month;
};

/**
 * CVC: up to four digits.
 *
 * @param {string} value What is in the box.
 * @return {string} Digits.
 */
export const formatCvc = ( value ) => digits( value ).slice( 0, 4 );
