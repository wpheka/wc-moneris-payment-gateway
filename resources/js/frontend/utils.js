/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Moneris data comes from the server passed on a global object.
 */
export const getMonerisServerData = () => {
	const monerisServerData = getSetting( 'moneris_data', null );
	if ( ! monerisServerData || typeof monerisServerData !== 'object' ) {
		throw new Error( 'Moneris initialization data is not available' );
	}
	return monerisServerData;
};