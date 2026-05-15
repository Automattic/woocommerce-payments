/** @format */

/**
 * External dependencies
 */
import type { View, Filter } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import { useReportsFees, useReportsFeesSummary } from 'wcpay/data';
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import type { ReportsPeriodRange } from '../period-selector';

export interface FeesQuery {
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

export const viewToFeesQuery = (
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
	rows: ReportsFee[];
	totalItems: number;
	totalPages: number;
	methodElements: Array< { value: string; label: string } >;
	typeElements: Array< { value: string; label: string } >;
	isLoading: boolean;
	error: Record< string, unknown >;
}

export const useFeesData = (
	view: View,
	period: ReportsPeriodRange
): UseFeesDataResult => {
	const feesQuery = viewToFeesQuery( view, period );
	const { feesRows, feesError = {}, isLoading } = useReportsFees( feesQuery );
	const { feesSummary, isLoading: isSummaryLoading } =
		useReportsFeesSummary( feesQuery );

	const totalItems = feesSummary.count ?? 0;
	const perPage = parseInt( feesQuery.per_page ?? '25', 10 );
	const totalPages = Math.max( 1, Math.ceil( totalItems / perPage ) );

	const methodElements = ( feesSummary.sources ?? [] ).map( ( source ) => ( {
		value: source,
		label: source,
	} ) );

	const typeElements = ( feesSummary.types ?? [] ).map( ( type ) => ( {
		value: type,
		label: type,
	} ) );

	return {
		rows: feesRows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading: isLoading || isSummaryLoading,
		error: feesError,
	};
};
