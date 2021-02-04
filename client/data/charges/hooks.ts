/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

/**
 * Internal dependencies
 */
import { Charge } from './types';

export const useCharge = (
	chargeId: string
): { charge?: Charge; chargeError?: Error; isLoading: boolean } =>
	useSelect(
		( select ) => {
			const { getCharge, getChargeError, isResolving } = select(
				STORE_NAME
			);
			return {
				charge: getCharge( chargeId ),
				chargeError: getChargeError( chargeId ),
				isLoading: isResolving( 'getCharge', [ chargeId ] ),
			};
		},
		[ chargeId ]
	);
