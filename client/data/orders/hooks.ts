/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { OrderResponse } from './types';

export const useOrder = ( id: string ): OrderResponse =>
	useSelect(
		( select ) => {
			const {
				isResolving,
				hasFinishedResolution,
				getOrder,
				getOrderError,
			} = select( STORE_NAME );

			if ( ! id || ! wcpaySettings.isFraudProtectionSettingsEnabled ) {
				return {
					data: undefined,
					error: undefined,
					isLoading: false,
				};
			}

			return {
				data: getOrder( id ),
				error: getOrderError( id ),
				isLoading:
					isResolving( 'getOrder', [ id ] ) ||
					! hasFinishedResolution( 'getOrder', [ id ] ),
			};
		},
		[ id ]
	);
