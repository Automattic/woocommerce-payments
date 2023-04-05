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

export const useLatestFraudOutcome = (
	id: string
): LatestFraudOutcomeResponse =>
	useSelect(
		( select ) => {
			const {
				isResolving,
				hasFinishedResolution,
				getLatestFraudOutcome,
				getLatestFraudOutcomeError,
			} = select( STORE_NAME );

			if ( ! id || ! wcpaySettings.isFraudProtectionSettingsEnabled ) {
				return {
					data: undefined,
					error: undefined,
					isLoading: false,
				};
			}

			return {
				data: getLatestFraudOutcome( id ),
				error: getLatestFraudOutcomeError( id ),
				isLoading:
					isResolving( 'getLatestFraudOutcome', [ id ] ) ||
					! hasFinishedResolution( 'getLatestFraudOutcome', [ id ] ),
			};
		},
		[ id ]
	);
