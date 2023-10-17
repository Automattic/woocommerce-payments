/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import type { Query } from '@woocommerce/navigation';
import moment from 'moment';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { QueryFunctionContext, useQuery } from 'react-query';

/**
 * Internal dependencies
 */
import type {
	Dispute,
	DisputesSummary,
	CachedDisputes,
} from 'wcpay/types/disputes';
import type { ApiError } from 'wcpay/types/errors';
import { STORE_NAME } from '../constants';
import { disputeAwaitingResponseStatuses } from 'wcpay/disputes/filters/config';
import { formatDateValue } from 'wcpay/utils';
import { snakeCase } from 'lodash';

const fetchDispute = async ( { queryKey }: QueryFunctionContext ) => {
	const [ , id ] = queryKey;
	const path = addQueryArgs( `/wc/v3/payments/disputes/${ id }` );
	const response = await apiFetch< Dispute >( { path } );
	return response;
};

/**
 * Returns the dispute object, error object, and loading state.
 * Fetches the dispute object if it is not already cached.
 */
export const useDispute = (
	id: string
): {
	dispute?: Dispute;
	error?: ApiError;
	isLoading: boolean;
} => {
	const { data, isLoading, isError } = useQuery<
		Dispute | undefined,
		ApiError | undefined
	>( [ 'disputes', id ], fetchDispute, {
		refetchOnMount: false,
	} );

	return {
		dispute: data,
		error: isError ? { code: '414' } : undefined,
		isLoading,
	};
};

/**
 * Returns the dispute accept function and loading state.
 * Does not return or fetch the dispute object.
 */
export const useDisputeAccept = (
	dispute: Dispute
): {
	doAccept: () => void;
	isLoading: boolean;
} => {
	const { isLoading } = useSelect(
		( select ) => {
			const { isResolving } = select( STORE_NAME );

			return {
				isLoading: isResolving( 'getDispute', [ dispute.id ] ),
			};
		},
		[ dispute.id ]
	);
	const { acceptDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( dispute );
	return { doAccept, isLoading };
};

export const useDisputeEvidence = (): {
	updateDispute: ( data: Dispute ) => void;
} => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
};

const formatQueryFilters = ( query: any ) => ( {
	user_email: query.userEmail,
	match: query.match,
	store_currency_is: query.storeCurrencyIs,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	search: query.search,
	status_is: query.statusIs,
	status_is_not: query.statusIsNot,
} );

const fetchDisputes = async ( { queryKey }: QueryFunctionContext ) => {
	const [ , query ] = queryKey;
	const path = addQueryArgs(
		`/wc/v3/payments/disputes`,
		query as Record< string, unknown >
	);
	const response = await apiFetch< {
		data: CachedDisputes[ 'disputes' ];
	} >( { path } );
	return response;
};

export const useDisputes = ( {
	paged,
	per_page: perPage,
	store_currency_is: storeCurrencyIs,
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	filter,
	status_is: statusIs,
	status_is_not: statusIsNot,
	orderby: orderBy,
	order,
}: Query ): CachedDisputes => {
	const search =
		filter === 'awaiting_response'
			? disputeAwaitingResponseStatuses
			: undefined;

	let query: Record< string, unknown > = {
		paged: Number.isNaN( parseInt( paged ?? '', 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) ) ? '25' : perPage,
		storeCurrencyIs,
		match,
		dateBefore,
		dateAfter,
		dateBetween:
			dateBetween &&
			dateBetween.sort( ( a, b ) => moment( a ).diff( moment( b ) ) ),
		search,
		statusIs,
		statusIsNot,
		orderBy: orderBy || 'created',
		order: order || 'desc',
	};

	query = {
		page: query.paged,
		pagesize: query.perPage,
		sort: snakeCase( query.orderBy as string ),
		direction: query.order,
		...formatQueryFilters( query ),
	};

	const { isLoading, data } = useQuery(
		[ 'disputes', query ],
		fetchDisputes,
		{
			refetchOnMount: true,
			refetchOnWindowFocus: true,
			refetchInterval: 10000,
			refetchOnReconnect: true,
		}
	);

	return {
		disputes: data?.data || [],
		isLoading,
	};
};

const fetchDisputesSummary = async ( { queryKey }: QueryFunctionContext ) => {
	const [ , query ] = queryKey;
	const path = addQueryArgs(
		`/wc/v3/payments/disputes/summary`,
		query as Record< string, unknown >
	);

	const response = await apiFetch< {
		count?: number;
		currencies?: string[];
	} >( {
		path,
	} );

	return response;
};

export const useDisputesSummary = ( {
	paged,
	per_page: perPage,
	match,
	store_currency_is: storeCurrencyIs,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	filter,
	status_is: statusIs,
	status_is_not: statusIsNot,
}: Query ): DisputesSummary => {
	const search =
		filter === 'awaiting_response'
			? disputeAwaitingResponseStatuses
			: undefined;

	let query = {
		paged: Number.isNaN( parseInt( paged ?? '', 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) ) ? '25' : perPage,
		match,
		storeCurrencyIs,
		dateBefore,
		dateAfter,
		dateBetween,
		search,
		statusIs,
		statusIsNot,
	} as any;

	query = {
		...query,
		page: query.paged,
		pagesize: query.perPage,
		...formatQueryFilters( query ),
	};

	const { isLoading, data } = useQuery(
		[ 'disputesSummary', query ],
		fetchDisputesSummary
	);
	return {
		disputesSummary: data || {},
		isLoading,
	};
};
