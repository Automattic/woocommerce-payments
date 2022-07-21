/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import type { Query } from '@woocommerce/navigation';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type {
	Dispute,
	CachedDisputes,
	DisputesSummary,
	DisputeStatusCounts,
} from 'wcpay/types/disputes';
import { STORE_NAME } from '../constants';
import { disputeAwaitingResponseStatuses } from 'wcpay/disputes/filters/config';

export const useDispute = (
	id: string
): {
	dispute: Dispute;
	isLoading: boolean;
	doAccept: () => void;
} => {
	const { dispute, isLoading } = useSelect(
		( select ) => {
			const { getDispute, isResolving } = select( STORE_NAME );

			return {
				dispute: <Dispute>getDispute( id ),
				isLoading: <boolean>isResolving( 'getDispute', [ id ] ),
			};
		},
		[ id ]
	);

	const { acceptDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( id );

	return { dispute, isLoading, doAccept };
};

export const useDisputeEvidence = (): {
	updateDispute: ( data: Dispute ) => void;
} => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
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
}: Query ): CachedDisputes =>
	useSelect(
		( select ) => {
			const { getDisputes, isResolving } = select( STORE_NAME );

			const search =
				filter === 'awaiting_response'
					? disputeAwaitingResponseStatuses
					: undefined;

			const query = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
					? '25'
					: perPage,
				storeCurrencyIs,
				match,
				dateBefore,
				dateAfter,
				dateBetween:
					dateBetween &&
					dateBetween.sort( ( a, b ) =>
						moment( a ).diff( moment( b ) )
					),
				search,
				statusIs,
				statusIsNot,
				orderBy: orderBy || 'created',
				order: order || 'desc',
			};

			return {
				disputes: getDisputes( query ),
				isLoading: isResolving( 'getDisputes', [ query ] ),
			};
		},
		[
			paged,
			perPage,
			storeCurrencyIs,
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			filter,
			statusIs,
			statusIsNot,
			orderBy,
			order,
		]
	);

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
}: Query ): DisputesSummary =>
	useSelect(
		( select ) => {
			const { getDisputesSummary, isResolving } = select( STORE_NAME );

			const search =
				filter === 'awaiting_response'
					? disputeAwaitingResponseStatuses
					: undefined;

			const query = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
					? '25'
					: perPage,
				match,
				storeCurrencyIs,
				dateBefore,
				dateAfter,
				dateBetween,
				search,
				statusIs,
				statusIsNot,
			};

			return {
				disputesSummary: getDisputesSummary( query ),
				isLoading: isResolving( 'getDisputesSummary', [ query ] ),
			};
		},
		[
			paged,
			perPage,
			storeCurrencyIs,
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			filter,
			statusIs,
			statusIsNot,
		]
	);

export const useDisputeStatusCounts = (
	statuses: string[]
): DisputeStatusCounts =>
	useSelect(
		( select ) => {
			const { getDisputeStatusCounts, isResolving } = select(
				STORE_NAME
			);

			return {
				disputeStatusCounts: getDisputeStatusCounts( statuses ),
				isLoading: isResolving( 'getDisputeStatusCounts', [
					statuses,
				] ),
			};
		},
		[ statuses ]
	);
