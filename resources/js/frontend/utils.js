/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Hitpay data comes form the server passed on a global object.
 */
export const getMonerisServerData = () => {
	const monerisServerData = getSetting( 'moneris_data', null );
	if ( ! monerisServerData || typeof monerisServerData !== 'object' ) {
		throw new Error( 'Hitpay initialization data is not available' );
	}
	return monerisServerData;
};