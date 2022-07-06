/**
 * External dependencies
 */
import React from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	getFilters,
	getAdvancedFilters,
	TransactionsFilterType,
} from './config';
import { formatCurrencyName } from '../../utils/currency';
import './style.scss';

interface TransactionsFiltersProps {
	storeCurrencies?: string[];
	customerCurrencies?: string[];
}

export const TransactionsFilters = ( {
	storeCurrencies,
	customerCurrencies,
}: TransactionsFiltersProps ): JSX.Element => {
	const customerCurrencyOptions = Object.entries( {
		gbp: 'GBP (£)',
		usd: 'USD ($)',
		eur: 'EUR (€)',
	} )
		.map( ( [ type, label ] ) => {
			return { label, value: type };
		} )
		.filter( function ( el ) {
			return el != null;
		} );

	// const customerCurrencyOptions = customerCurrencies?.reduce(
	// 	( obj, currency ) => {
	// 		return {
	// 			...obj,
	// 			[ currency ]: currency.toUpperCase(),
	// 		};
	// 	},
	// 	{}
	// );

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
				filters={ populateDepositCurrencies( getFilters() ) }
				advancedFilters={ getAdvancedFilters(
					customerCurrencyOptions
				) }
				showDatePicker={ false }
				path="/payments/transactions"
				query={ getQuery() }
			/>
		</div>
	);
};

export default TransactionsFilters;
