/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

/**
 * Internal dependencies
 */
import { Timeline } from './types';
import { WCPayCharge } from '../charges/types';

export const useTimeline = (
	chargeId: string
): {
	timeline?: Timeline;
	timelineError?: Error;
	isLoading: boolean;
} =>
	useSelect(
		( select ) => {
			const {
				getTimeline,
				getTimelineError,
				isResolving,
				getCharge,
				getChargeError,
			} = select( STORE_NAME );

			// Make sure the charge is loaded first to get the intention ID.
			const isChargeLoading = isResolving< boolean >( 'getCharge', [
				chargeId,
			] );
			const chargeError = getChargeError< Error | undefined >( chargeId );
			if ( isChargeLoading || chargeError instanceof Error ) {
				return {
					timeline: undefined,
					timelineError: chargeError,
					isLoading: isChargeLoading,
				};
			}

			const intentionId = getCharge< WCPayCharge | undefined >( chargeId )
				?.payment_intent;
			if ( undefined === intentionId ) {
				// If intention ID is not available, do not render the timeline, but also don't indicate the API error.
				return {
					timeline: undefined,
					timelineError: undefined,
					isLoading: false,
				};
			}

			return {
				timeline: getTimeline< Timeline | undefined >( intentionId ),
				timelineError: getTimelineError< Error | undefined >(
					intentionId
				),
				isLoading: isResolving< boolean >( 'getTimeline', [
					intentionId,
				] ),
			};
		},
		[ chargeId ]
	);
