/**
 * External dependencies
 */
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';

export const TransactionFilters = () => {
	return <ReportFilters
		filters={ filters }
		advancedFilters={ advancedFilters }
		showDatePicker={ false }
		path="/payments/transactions"
		query={ getQuery() } />;
};

export default TransactionFilters;
