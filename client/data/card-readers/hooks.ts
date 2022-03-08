/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { CardReaders, CardReaderStats } from 'wcpay/types/card-readers';
import { STORE_NAME } from '../constants';

export const useCardReaderStats = (
	chargeId: string,
	transactionId: string
): CardReaderStats =>
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

export const useReaders = ( limit: number ): CardReaders =>
	useSelect(
		( select ) => {
			const { getCardReaders, isResolving } = select( STORE_NAME );

			const query = {
				limit,
			};

			return {
				readers: getCardReaders( query ),
				isLoading: isResolving( 'getCardReaders', [ query ] ),
			};
		},
		[ limit ]
	);
