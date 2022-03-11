/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

interface CardReader {
	id: string;
	livemode: boolean;
	device_type: string;
	label: string;
	location: string;
	metadata: Record< string, any >;
	status: string;
	is_active: boolean;
}

interface CardReaderSummary {
	reader_id: string;
	count: number;
	status: string;
	amount: number;
	fee: {
		currency: string;
	};
}

export const useCardReaderStats = (
	chargeId: string,
	transactionId: string
): {
	readers: CardReaderSummary[];
	chargeError: string;
	isLoading: boolean;
} =>
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

export const useReaders = (
	limit: number
): {
	readers: CardReader[];
	isLoading: boolean;
} =>
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
