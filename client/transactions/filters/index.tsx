/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getFilters, getAdvancedFilters } from './config';
import { formatCurrencyName } from '../../utils/currency';
import './style.scss';
import { recordEvent, events } from 'tracks';

interface TransactionsFiltersProps {
	storeCurrencies: string[];
	customerCurrencies: string[];
}

export const TransactionsFilters = ( {
	storeCurrencies,
	customerCurrencies,
}: TransactionsFiltersProps ): JSX.Element => {
	const advancedFilters = useMemo(
		() =>
			getAdvancedFilters(
				customerCurrencies.map( ( currencyCode: string ) => ( {
					label: formatCurrencyName( currencyCode ),
					value: currencyCode,
				} ) )
			),
		[ customerCurrencies ]
	);

	const filters = useMemo(
		() =>
			getFilters(
				storeCurrencies.map( ( currencyCode: string ) => ( {
					label: formatCurrencyName( currencyCode ),
					value: currencyCode,
				} ) ),
				storeCurrencies.length > 1
			),
		[ storeCurrencies ]
	);

	return (
		<div className="woocommerce-filters-transactions">
			<ReportFilters
				key={ customerCurrencies?.length }
				filters={ filters }
				advancedFilters={ advancedFilters }
				showDatePicker={ false }
				path="/payments/transactions"
				query={ getQuery() }
				onAdvancedFilterAction={ ( event ) => {
					if ( event === 'filter' ) {
						recordEvent( events.PAGE_VIEW, {
							path: 'payments_transactions',
							filter: 'advanced',
						} );
					}
				} }
			/>
		</div>
	);
};

export default TransactionsFilters;
