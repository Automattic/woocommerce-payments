/**
 * External dependencies
 */
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';
import { useMemo } from '@wordpress/element';

const populateDepositCurrencies = ( filtersConfiguration ) => {
	filtersConfiguration.forEach( ( filter ) => {
		if ( 'store_currency_is' === filter.param ) {
			const currencies = useMemo(
				() => [
					{
						label: 'EUR',
						value: 'eur',
					},
					{
						label: 'USD',
						value: 'usd',
					},
				],
				[]
			);

			filter.filters.push( ...currencies );
			filter.showFilters = () => true;
		}
	} );
	return filtersConfiguration;
};

export const TransactionsFilters = () => {
	return (
		<ReportFilters
			filters={ populateDepositCurrencies( filters ) }
			advancedFilters={ advancedFilters }
			showDatePicker={ false }
			path="/payments/transactions"
			query={ getQuery() }
		/>
	);
};

export default TransactionsFilters;
