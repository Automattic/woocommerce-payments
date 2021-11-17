/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useCardReaderStats = ( chargeId, transactionId ) =>
	useSelect(
		( select ) => {
			const {
				getCardReaderStats,
				getCardReaderStatsError,
				isResolving,
			} = select( STORE_NAME );

			return {
				readers: getCardReaderStats( chargeId, transactionId ),
				chargeError: getCardReaderStatsError( chargeId ),
				isLoading: isResolving( 'getCardReaderStats', [
					chargeId,
					transactionId,
				] ),
			};
		},
		[ chargeId, transactionId ]
	);
