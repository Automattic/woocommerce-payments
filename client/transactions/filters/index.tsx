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
import './style.scss';

interface TransactionsFiltersProps {
	storeCurrencies?: string[];
}

export const TransactionsFilters = ( {
	storeCurrencies,
}: TransactionsFiltersProps ): JSX.Element => {
	const populateDepositCurrencies = (
		filtersConfiguration: TransactionsFilterType[]
	) => {
		filtersConfiguration.forEach( ( filter ) => {
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
		<div className="woocommerce-filters-transactions">
			<ReportFilters
				filters={ populateDepositCurrencies( filters ) }
				advancedFilters={ advancedFilters }
				showDatePicker={ false }
				path="/payments/transactions"
				query={ getQuery() }
			/>
		</div>
	);
};

export default TransactionsFilters;
