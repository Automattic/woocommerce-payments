/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useCharge = ( chargeId ) => useSelect( select => {
	const { getCharge, getChargeError, isResolving } = select( STORE_NAME );
	return {
		charge: getCharge( chargeId ),
		chargeError: getChargeError( chargeId ),
		isLoading: isResolving( 'getCharge', [ chargeId ] ),
	};
}, [ chargeId ] );
