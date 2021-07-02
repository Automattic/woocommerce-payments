/**
 * External dependencies
 */
import React from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters, TransactionsFilterType } from './config';
import { formatCurrencyName } from '../../utils/currency';

interface TransactionsFiltersProps {
	storeCurrencies?: string[];
}

export const TransactionsFilters: React.FunctionComponent< TransactionsFiltersProps > = ( {
	storeCurrencies,
} ): JSX.Element => {
	const populateDepositCurrencies = (
		filtersConfiguration: TransactionsFilterType[]
	): TransactionsFilterType[] => {
		filtersConfiguration.forEach( ( filter: TransactionsFilterType ) => {
			if ( 'store_currency_is' === filter.param ) {
				const currencies = storeCurrencies || [];
				// Generate select options: pick the first one (default) and add provided currencies
				filter.filters = [
					filter.filters[ 0 ],
					...currencies.map( ( currencyCode: string ) => ( {
						label: formatCurrencyName( currencyCode ),
						value: currencyCode,
					} ) ),
				];
				// Show the select when several currencies are available.
				if ( 2 < filter.filters.length ) {
					filter.showFilters = () => true;
				}
			}
		} );
		return filtersConfiguration;
	};

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
