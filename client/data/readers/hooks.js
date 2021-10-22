/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useReaderStats = ( chargeId ) =>
	useSelect(
		( select ) => {
			const { getReaderStats, getReaderStatsError, isResolving } = select(
				STORE_NAME
			);

			return {
				readers: getReaderStats( chargeId ),
				chargeError: getReaderStatsError( chargeId ),
				isLoading: isResolving( 'getReaderStats', [ chargeId ] ),
			};
		},
		[ chargeId ]
	);
