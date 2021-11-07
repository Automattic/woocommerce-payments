/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useReaderStats = ( chargeId, transactionId ) =>
	useSelect(
		( select ) => {
			const { getReaderStats, getReaderStatsError, isResolving } = select(
				STORE_NAME
			);

			return {
				readers: getReaderStats( chargeId, transactionId ),
				chargeError: getReaderStatsError( chargeId ),
				isLoading: isResolving( 'getReaderStats', [
					chargeId,
					transactionId,
				] ),
			};
		},
		[ chargeId, transactionId ]
	);
