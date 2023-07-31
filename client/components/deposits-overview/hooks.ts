/**
 * Internal dependencies
 */
import { useDeposits } from 'wcpay/data';
import { CachedDeposit } from 'wcpay/types/deposits';

interface RecentDeposits {
	deposits: CachedDeposit[];
	isLoading: boolean;
}

const useRecentDeposits = ( currency?: string ): RecentDeposits => {
	const query = {
		status_is_not: 'estimated',
		store_currency_is: currency,
		orderby: 'date',
		order: 'desc',
		per_page: '3',
	};
	const deposits = useDeposits( query );

	return {
		deposits: deposits.deposits,
		isLoading: deposits.isLoading,
	};
};

export default useRecentDeposits;
