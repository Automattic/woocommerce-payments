/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { LatestFraudOutcomeResponse } from './types';
import { FraudOutcome } from '../../types/fraud-outcome';

export const useLatestFraudOutcome = (
	id: string
): LatestFraudOutcomeResponse =>
	useSelect(
		( select ) => {
			const {
				isResolving,
				getLatestFraudOutcome,
				getLatestFraudOutcomeError,
			} = select( STORE_NAME );

			if ( ! id || ! wcpaySettings.isFraudProtectionSettingsEnabled ) {
				return {
					data: {} as FraudOutcome,
					error: undefined,
					isLoading: false,
				};
			}

			return {
				data: getLatestFraudOutcome( id ),
				error: getLatestFraudOutcomeError( id ),
				isLoading: isResolving( 'getLatestFraudOutcome', [ id ] ),
			};
		},
		[ id ]
	);
