/** @format */

/**
 * External dependencies
 */
import { useMemo } from 'react';
import type { View, Filter } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { useReportsFees, useReportsFeesSummary } from 'wcpay/data/reports';
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import { formatStringValue } from 'wcpay/utils';
import { displayMethod, displayType } from './strings';
import { buildFeesDateQueryFromFilter } from './date-filter-values';

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
	type?: string;
	search?: string[];
}

const findFilter = (
	filters: Filter[] | undefined,
	field: string
): Filter | undefined => filters?.find( ( f ) => f.field === field );

// DataViews column ids that differ from the backend `sort_field` enum
// (validated against `Transaction::$fields` server-side). Unmapped ids pass
// through unchanged — they already match the backend column name.
const sortFieldByColumnId: Record< string, string > = {
	payment_method: 'source',
	transaction_currency: 'customer_currency',
	deposit_date: 'available_on',
};

const resolveSortField = ( columnId: string | undefined ): string => {
	if ( ! columnId ) {
		return 'date';
	}
	return sortFieldByColumnId[ columnId ] ?? columnId;
};

const getSingleStringValue = ( value: unknown ): string | undefined => {
	if ( typeof value !== 'string' ) {
		return undefined;
	}

	const trimmed = value.trim();
	if ( trimmed === '' || trimmed.includes( ',' ) ) {
		return undefined;
	}

	return trimmed;
};

/**
 * Build a REST query for the Fees endpoint from the DataViews `view`. When no
 * date filter is active the query carries no date bounds, so the endpoint
 * returns all available fees.
 *
 * NOTE: The previous TableCard implementation accepted `order_id` and
 * `deposit_id` filter params, which the PHP controller still honours. They
 * are intentionally not surfaced from the DataViews UI in this PR; a
 * follow-up will add purpose-built filter chips for them.
 *
 */
export const buildFeesQuery = ( view: View ): FeesQuery => {
	const query: FeesQuery = {
		paged: String( view.page ?? 1 ),
		per_page: String( view.perPage ?? 25 ),
		orderby: resolveSortField( view.sort?.field ),
		order: ( view.sort?.direction as 'asc' | 'desc' ) || 'desc',
	};

	Object.assign(
		query,
		buildFeesDateQueryFromFilter( findFilter( view.filters, 'date' ) )
	);

	const methodFilter = findFilter( view.filters, 'payment_method' );
	if ( methodFilter && methodFilter.value ) {
		query.payment_method_type = methodFilter.value as string;
	}

	const typeFilter = findFilter( view.filters, 'type' );
	if ( typeFilter && typeFilter.value ) {
		const value = getSingleStringValue( typeFilter.value );
		if ( value ) {
			query.type = value;
		}
	}

	if ( view.search ) {
		query.search = [ view.search ];
	}

	return query;
};

interface UseFeesDataResult {
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

export const useFeesData = ( view: View ): UseFeesDataResult => {
	const feesQuery = useMemo( () => buildFeesQuery( view ), [ view ] );
	// Summary aggregates (count, total, fees, sources, types) don't change
	// with pagination or sort, so derive a stable summary query that omits
	// those params. Without this, paging or re-sorting would invalidate
	// the summary cache and trigger an unnecessary refetch.
	const summaryQuery = useMemo( () => {
		const next: FeesQuery = { ...feesQuery };
		delete next.paged;
		delete next.per_page;
		delete next.orderby;
		delete next.order;
		return next;
	}, [ feesQuery ] );
	const { feesRows, feesError = {}, isLoading } = useReportsFees( feesQuery );
	const {
		feesSummary,
		feesSummaryError = {},
		isLoading: isSummaryLoading,
	} = useReportsFeesSummary( summaryQuery );

	const totalItems = feesSummary.count ?? 0;
	const perPage = parseInt( feesQuery.per_page ?? '25', 10 );
	const totalPages = Math.max( 1, Math.ceil( totalItems / perPage ) );

	const sources = feesSummary.sources ?? [];
	// The summary endpoint returns `sources` filtered by the active query, so
	// when the user picks a method (or reloads with a method already in the
	// URL) `sources` collapses to just that method — or to an empty list when
	// the filter narrows results to zero rows. DataViews' `FilterText` resolves
	// the chip label by matching `element.value === filterInView.value`, and
	// falls back to bare "Method" when no element matches. To keep the chip
	// stable across fetches, we ensure the currently-active filter value is
	// always present with a derived label, regardless of what summary returns.
	const activeMethodValue = useMemo( () => {
		const f = view.filters?.find(
			( filter ) => filter.field === 'payment_method'
		);
		return typeof f?.value === 'string' ? f.value : undefined;
	}, [ view.filters ] );
	const methodElements = useMemo(
		() => {
			const fromSources = buildMethodElements( sources );
			if (
				activeMethodValue &&
				! fromSources.some( ( el ) => el.value === activeMethodValue )
			) {
				fromSources.push( {
					value: activeMethodValue,
					label:
						displayMethod( activeMethodValue ) || activeMethodValue,
				} );
			}
			return fromSources;
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ sources.join( '|' ), activeMethodValue ]
	);
	const typeElements = useMemo(
		() => buildTypeElements( [ ...feeBearingTypes ] ),
		[]
	);
	return {
		rows: feesRows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading: isLoading || isSummaryLoading,
		error: {
			...feesSummaryError,
			...feesError,
		},
	};
};
