/**
 * External dependencies
 */
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';
import { formatCurrencyName } from '../../utils/currency';

export const DepositsFilters = ( props ) => {
	const populateDepositCurrencies = ( filtersConfiguration ) => {
		filtersConfiguration.forEach( ( filter ) => {
			if ( filter.param === 'store_currency_is' ) {
				const currencies = props.storeCurrencies || [];
				// Generate select options: pick the first one (default) and add provided currencies
				filter.filters = [
					filter.filters[ 0 ],
					...currencies.map( ( currencyCode ) => ( {
						label: formatCurrencyName( currencyCode ),
						value: currencyCode,
					} ) ),
				];
				// Show the select when several currencies are available.
				if ( filter.filters.length > 2 ) {
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
			path="/payments/deposits"
			query={ getQuery() }
		/>
	);
};

export default DepositsFilters;
