/**
 * External dependencies
 */
import React from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters, DisputesFilterType } from './config';
import { formatCurrencyName } from '../../utils/currency';

interface DisputesFiltersProps {
	storeCurrencies?: string[];
}

export const DisputesFilters = ( {
	storeCurrencies,
}: DisputesFiltersProps ): JSX.Element => {
	const populateDepositCurrencies = (
		filtersConfiguration: DisputesFilterType[]
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
		<ReportFilters
			filters={ populateDepositCurrencies( filters ) }
			advancedFilters={ advancedFilters }
			showDatePicker={ false }
			path="/payments/disputes"
			query={ getQuery() }
		/>
	);
};

export default DisputesFilters;
