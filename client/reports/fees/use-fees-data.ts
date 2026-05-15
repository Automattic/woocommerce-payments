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
import type { ReportsPeriodRange } from '../period-selector';
import { displayMethod, displayType } from './strings';

interface FeesQuery {
	paged?: string;
	per_page?: string;
	orderby?: string;
	order?: 'asc' | 'desc';
	match?: 'all' | 'advanced';
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

const periodToDateBetween = ( period: ReportsPeriodRange ): string[] => [
	period.start.slice( 0, 10 ),
	period.end.slice( 0, 10 ),
];

/**
 * Build a REST query for the Fees endpoint from the DataViews `view` and the
 * report `period`. `period` supplies the date range when `view` carries no
 * explicit date filter — without it, the query falls back to the endpoint's
 * default (potentially all-time) window.
 *
 * NOTE: The previous TableCard implementation accepted `order_id`,
 * `deposit_id`, and `customer_email` filter params, which the PHP controller
 * still honours. They are intentionally not surfaced from the DataViews UI in
 * this PR; a follow-up will add purpose-built filter chips for them
 * (tracked alongside the deferred in-UI date filter chip — RSM-2125).
 */
export const buildFeesQuery = (
	view: View,
	period: ReportsPeriodRange
): FeesQuery => {
	const query: FeesQuery = {
		paged: String( view.page ?? 1 ),
		per_page: String( view.perPage ?? 25 ),
		orderby: view.sort?.field || 'date',
		order: ( view.sort?.direction as 'asc' | 'desc' ) || 'desc',
	};

	const dateFilter = findFilter( view.filters, 'date' );
	if ( dateFilter ) {
		const op = dateFilter.operator as string;
		if ( op === 'between' ) {
			query.date_between = dateFilter.value as string[];
		} else if ( op === 'before' ) {
			query.date_before = dateFilter.value as string;
		} else if ( op === 'after' ) {
			query.date_after = dateFilter.value as string;
		}
	} else {
		query.date_between = periodToDateBetween( period );
	}

	const methodFilter = findFilter( view.filters, 'payment_method' );
	if ( methodFilter ) {
		query.payment_method_type = methodFilter.value as string;
	}

	const typeFilter = findFilter( view.filters, 'type' );
	if ( typeFilter ) {
		query.type = typeFilter.value as string | string[];
	}

	if ( view.search ) {
		query.search = [ view.search ];
	}

	const hasNonDateFilter = ( view.filters || [] ).some(
		( f ) => f.field !== 'date'
	);
	if ( hasNonDateFilter ) {
		query.match = 'advanced';
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
	sources: string[]
): Array< { value: string; label: string } > =>
	sources.map( ( source ) => ( {
		value: source,
		label: displayMethod( source ) || source,
	} ) );

const buildTypeElements = (
	types: string[]
): Array< { value: string; label: string } > =>
	types.map( ( type ) => ( {
		value: type,
		label:
			displayType[ type as keyof typeof displayType ] ||
			formatStringValue( type ),
	} ) );

export const useFeesData = (
	view: View,
	period: ReportsPeriodRange
): UseFeesDataResult => {
	const feesQuery = useMemo(
		() => buildFeesQuery( view, period ),
		[ view, period ]
	);
	const { feesRows, feesError = {}, isLoading } = useReportsFees( feesQuery );
	const { feesSummary, isLoading: isSummaryLoading } =
		useReportsFeesSummary( feesQuery );

	const totalItems = feesSummary.count ?? 0;
	const perPage = parseInt( feesQuery.per_page ?? '25', 10 );
	const totalPages = Math.max( 1, Math.ceil( totalItems / perPage ) );

	const sources = feesSummary.sources ?? [];
	const types = feesSummary.types ?? [];
	const methodElements = useMemo(
		() => buildMethodElements( sources ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ sources.join( '|' ) ]
	);
	const typeElements = useMemo(
		() => buildTypeElements( types ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ types.join( '|' ) ]
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
