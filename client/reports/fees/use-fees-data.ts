/** @format */

/**
 * External dependencies
 */
import { useMemo } from 'react';
import type { View, Filter } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import { useReportsFees, useReportsFeesSummary } from 'wcpay/data';
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import { formatStringValue } from 'wcpay/utils';
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import type { ReportsPeriodRange } from '../period-selector';
import { displayMethod, displayType } from './strings';

// Default fee-bearing transaction types, mirroring DEFAULT_FEE_BEARING_TYPES in
// the PHP controller. The summary endpoint exposes `sources` (payment methods
// seen in the active range) but not `types`, so we hard-code them here. DataViews
// won't show a field in the "Add filter" menu unless `elements` is non-empty.
const feeBearingTypes: ReadonlyArray< string > = [
	'charge',
	'payment',
	'payment_failure_refund',
	'payment_refund',
	'refund',
	'refund_failure',
	'dispute',
	'dispute_reversal',
	'fee_refund',
	'network_costs',
];

interface FeesQuery {
	paged?: string;
	per_page?: string;
	orderby?: string;
	order?: 'asc' | 'desc';
	date_before?: string;
	date_after?: string;
	date_between?: string[];
	payment_method_type?: string;
	type?: string | string[];
	search?: string[];
}

const findFilter = (
	filters: Filter[] | undefined,
	field: string
): Filter | undefined => filters?.find( ( f ) => f.field === field );

/**
 * Build a REST query for the Fees endpoint from the DataViews `view` and the
 * standalone date filter. When no date filter is active the query carries no
 * date bounds, so the endpoint returns all available fees.
 *
 * NOTE: The previous TableCard implementation accepted `order_id`,
 * `deposit_id`, and `customer_email` filter params, which the PHP controller
 * still honours. They are intentionally not surfaced from the DataViews UI in
 * this PR; a follow-up will add purpose-built filter chips for them.
 *
 * The `period` argument is retained on the signature so callers don't need to
 * change yet, but it no longer affects the query — date filtering is driven
 * entirely by the standalone Date filter component.
 */
export const buildFeesQuery = (
	view: View,
	dateFilter: DateFilterValue | undefined,
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	period?: ReportsPeriodRange
): FeesQuery => {
	const query: FeesQuery = {
		paged: String( view.page ?? 1 ),
		per_page: String( view.perPage ?? 25 ),
		orderby: view.sort?.field || 'date',
		order: ( view.sort?.direction as 'asc' | 'desc' ) || 'desc',
	};

	if ( dateFilter ) {
		switch ( dateFilter.operator ) {
			case 'on':
				query.date_between = [ dateFilter.value, dateFilter.value ];
				break;
			case 'between':
				query.date_between = dateFilter.value;
				break;
			case 'before':
				query.date_before = dateFilter.value;
				break;
			case 'after':
				query.date_after = dateFilter.value;
				break;
		}
	}

	const methodFilter = findFilter( view.filters, 'payment_method' );
	if ( methodFilter && methodFilter.value ) {
		query.payment_method_type = methodFilter.value as string;
	}

	const typeFilter = findFilter( view.filters, 'type' );
	if ( typeFilter && typeFilter.value ) {
		// The Type filter is multi-select (`isAny`), but the REST controller
		// declares `type` as a single string in its schema; sending `type[]=…`
		// triggers a 400 (`rest_invalid_param`). The PHP handler accepts a
		// comma-separated string and splits it into `type_is_in`, so we join
		// the array client-side. Single selection is sent as a plain string.
		const value = typeFilter.value;
		if ( Array.isArray( value ) ) {
			if ( value.length > 0 ) {
				query.type = value.join( ',' );
			}
		} else {
			query.type = value as string;
		}
	}

	if ( view.search ) {
		query.search = [ view.search ];
	}

	return query;
};

interface UseFeesDataResult {
	feesQuery: FeesQuery;
	rows: ReportsFee[];
	totalItems: number;
	totalPages: number;
	methodElements: Array< { value: string; label: string } >;
	typeElements: Array< { value: string; label: string } >;
	isLoading: boolean;
	error: Record< string, unknown >;
}

const buildMethodElements = (
	sources: Array< string | null >
): Array< { value: string; label: string } > =>
	sources
		.filter( ( source ): source is string => Boolean( source ) )
		.map( ( source ) => ( {
			value: source,
			label: displayMethod( source ) || source,
		} ) );

const buildTypeElements = (
	types: Array< string | null >
): Array< { value: string; label: string } > =>
	types
		.filter( ( type ): type is string => Boolean( type ) )
		.map( ( type ) => ( {
			value: type,
			label:
				displayType[ type as keyof typeof displayType ] ||
				formatStringValue( type ),
		} ) );

export const useFeesData = (
	view: View,
	dateFilter: DateFilterValue | undefined,
	period: ReportsPeriodRange
): UseFeesDataResult => {
	const feesQuery = useMemo(
		() => buildFeesQuery( view, dateFilter, period ),
		[ view, dateFilter, period ]
	);
	const { feesRows, feesError = {}, isLoading } = useReportsFees( feesQuery );
	const { feesSummary, isLoading: isSummaryLoading } =
		useReportsFeesSummary( feesQuery );

	const totalItems = feesSummary.count ?? 0;
	const perPage = parseInt( feesQuery.per_page ?? '25', 10 );
	const totalPages = Math.max( 1, Math.ceil( totalItems / perPage ) );

	const sources = feesSummary.sources ?? [];
	const methodElements = useMemo(
		() => buildMethodElements( sources ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ sources.join( '|' ) ]
	);
	const typeElements = useMemo(
		() => buildTypeElements( [ ...feeBearingTypes ] ),
		[]
	);

	return {
		feesQuery,
		rows: feesRows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading: isLoading || isSummaryLoading,
		error: feesError,
	};
};
