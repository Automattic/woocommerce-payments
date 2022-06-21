/** @format */

/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import { getQuery, Query, updateQueryString } from '@woocommerce/navigation';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type {
	Dispute,
	CachedDisputes,
	DisputesSummary,
} from 'wcpay/types/disputes';
import { STORE_NAME } from '../constants';
import { disputeNeedsResponseStatuses } from 'wcpay/disputes/filters/config';

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
				filter === 'needs_response'
					? disputeNeedsResponseStatuses
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
				filter === 'needs_response'
					? disputeNeedsResponseStatuses
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

export const useDisputesFilterSelection = ( {
	disputesSummary,
	isLoading,
}: DisputesSummary ): void => {
	// Keep track if 'all' filter has been viewed.
	const [ allFilterViewed, setAllFilterViewed ] = useState( false );

	// Select 'all' filter if summary returns 0 disputes needing response.
	// If 'all' filter has been viewed, 'needs_response' filter can be selected.
	useEffect( () => {
		if (
			false === allFilterViewed &&
			'needs_response' === getQuery().filter &&
			false === isLoading &&
			0 === disputesSummary.count
		) {
			updateQueryString( { filter: undefined } );
			setAllFilterViewed( true );
		}
	}, [ disputesSummary, isLoading, allFilterViewed ] );

	// Update state if 'all' or undefined filter is present in query.
	useEffect( () => {
		if ( [ 'all', undefined ].includes( getQuery().filter ) ) {
			setAllFilterViewed( true );
		}
	}, [] );
};
