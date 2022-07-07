/**
 * External dependencies
 */
import React from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getFilters, getAdvancedFilters } from './config';
import { formatCurrencyName } from '../../utils/currency';
import './style.scss';
import { __ } from '@wordpress/i18n/build-types';

interface TransactionsFiltersProps {
	storeCurrencies?: string[];
	customerCurrencies?: string[];
}

export const TransactionsFilters = ( {
	storeCurrencies,
	customerCurrencies,
}: TransactionsFiltersProps ): JSX.Element => {
	const customerCurrencyOptions = customerCurrencies?.map(
		( currencyCode: string ) => ( {
			label: formatCurrencyName( currencyCode ),
			value: currencyCode,
		} )
	);

	const currencies = storeCurrencies || [];
	const depositCurrencyOptions = currencies?.map(
		( currencyCode: string ) => ( {
			label: formatCurrencyName( currencyCode ),
			value: currencyCode,
		} )
	);

	return (
		<div className="woocommerce-filters-transactions">
			<ReportFilters
				key={ customerCurrencies?.length }
				filters={ getFilters(
					depositCurrencyOptions,
					2 < depositCurrencyOptions.length
				) }
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
