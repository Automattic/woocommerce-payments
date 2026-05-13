/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { ReportFilters } from '@woocommerce/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	FeesSummaryForFilters,
	getFeesAdvancedFilters,
	getFeesFilterOptionsFromSummary,
	getFeesFilters,
} from './config';
import { recordEvent } from 'tracks';

interface FeesFiltersProps {
	feesSummary?: FeesSummaryForFilters;
}

export const FeesFilters = ( {
	feesSummary = {},
}: FeesFiltersProps ): JSX.Element => {
	const { methodOptions, typeOptions } = useMemo(
		() => getFeesFilterOptionsFromSummary( feesSummary ),
		[ feesSummary ]
	);

	const advancedFilters = useMemo(
		() => getFeesAdvancedFilters( methodOptions, typeOptions ),
		[ methodOptions, typeOptions ]
	);

	return (
		<div className="woocommerce-filters-fees">
			<ReportFilters
				key={ `${ methodOptions.length }-${ typeOptions.length }` }
				filters={ getFeesFilters() }
				advancedFilters={ advancedFilters }
				showDatePicker={ false }
				path="/payments/reports"
				query={ getQuery() }
				onAdvancedFilterAction={ (
					event,
					filterQuery: Record< string, unknown > = {}
				) => {
					if ( event === 'filter' ) {
						recordEvent( 'page_view', {
							path: 'payments_reports',
							filter: 'advanced',
						} );

						if (
							filterQuery.date_before ||
							filterQuery.date_after ||
							filterQuery.date_between
						) {
							recordEvent( 'wcpay_reports_date_range_changed', {
								report: 'fees',
							} );
						}
					}
				} }
			/>
		</div>
	);
};

export default FeesFilters;
