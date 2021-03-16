/**
 * External dependencies
 */
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';
import { currencyNames } from '../strings';
import { __ } from '@wordpress/i18n';

export const TransactionsFilters = ( props ) => {
	const populateDepositCurrencies = ( filtersConfiguration ) => {
		filtersConfiguration.forEach( ( filter ) => {
			if ( 'store_currency_is' === filter.param ) {
				const currencies = props.storeCurrencies || [];
				// Generate select options: pick the first one (default) and add provided currencies
				filter.filters = [
					filter.filters[ 0 ],
					...currencies.map( ( currencyCode ) => {
						const currencyName =
							currencyNames[ currencyCode ] ||
							currencyCode.toUpperCase();
						return {
							// eslint-disable-next-line @wordpress/i18n-no-variables
							label: __( currencyName, 'woocommerce-payments' ),
							value: currencyCode,
						};
					} ),
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
