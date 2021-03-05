/**
 * External dependencies
 */
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';

export const DepositsFilters = () => {
	return (
		<ReportFilters
			filters={ filters() }
			advancedFilters={ advancedFilters() }
			showDatePicker={ false }
			path="/payments/deposits"
			query={ getQuery() }
		/>
	);
};

export default DepositsFilters;
