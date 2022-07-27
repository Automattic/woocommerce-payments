/** @format */
/**
 * External dependencies
 */
import { SelectorMap, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';
import { ChargeResponse } from './types';

export const getChargeData = (
	chargeId: string,
	selectors: SelectorMap
): ChargeResponse => {
	const { getCharge, isResolving, getChargeError } = selectors;

	return {
		data: getCharge( chargeId ),
		error: getChargeError( chargeId ),
		isLoading: isResolving( 'getCharge', [ chargeId ] ),
	};
};

export const useCharge = ( chargeId: string ): ChargeResponse =>
	useSelect(
		( select ) => {
			const selectors = select( STORE_NAME );

			return getChargeData( chargeId, selectors );
		},
		[ chargeId ]
	);
