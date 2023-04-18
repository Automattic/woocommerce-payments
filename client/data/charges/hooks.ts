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
	const {
		getCharge,
		isResolving,
		getChargeError,
		hasFinishedResolution,
	} = selectors;

	return {
		data: getCharge( chargeId ),
		error: getChargeError( chargeId ),
		isLoading:
			isResolving( 'getCharge', [ chargeId ] ) ||
			! hasFinishedResolution( 'getCharge', [ chargeId ] ),
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

export const useChargeFromOrder = ( orderId: string ): ChargeResponse =>
	useSelect(
		( select ) => {
			const {
				getChargeFromOrder,
				isResolving,
				getChargeFromOrderError,
				hasFinishedResolution,
			} = select( STORE_NAME );

			return {
				data: getChargeFromOrder( orderId ),
				error: getChargeFromOrderError( orderId ),
				isLoading:
					isResolving( 'getChargeFromOrder', [ orderId ] ) ||
					! hasFinishedResolution( 'getChargeFromOrder', [
						orderId,
					] ),
			};
		},
		[ orderId ]
	);
