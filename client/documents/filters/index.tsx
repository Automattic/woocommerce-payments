/**
 * External dependencies
 */
import React from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { filters, advancedFilters } from './config';
import './style.scss';

export const DocumentsFilters = (): JSX.Element => {
	return (
		<div className="woocommerce-filters-documents">
			<ReportFilters
				filters={ filters }
				advancedFilters={ advancedFilters }
				showDatePicker={ false }
				path="/payments/documents"
				query={ getQuery() }
			/>
		</div>
	);
};

export default DocumentsFilters;
