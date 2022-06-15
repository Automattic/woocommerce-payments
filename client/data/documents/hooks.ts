/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import moment from 'moment';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export interface Document {
	document_id: string;
	date: string;
	type: 'vat_invoice';
	period_from: string;
	period_to: string;
}

interface Documents {
	documents: Document[];
	documentsError: unknown;
	isLoading: boolean;
}
interface DocumentsSummary {
	documentsSummary: {
		count?: number;
	};
	isLoading: boolean;
}

export const useDocuments = ( {
	paged,
	per_page: perPage,
	orderby,
	order,
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	type_is: typeIs,
	type_is_not: typeIsNot,
}: Query ): Documents =>
	useSelect(
		( select ) => {
			const { getDocuments, getDocumentsError, isResolving } = select(
				STORE_NAME
			);

			const query = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
					? '25'
					: perPage,
				orderby: orderby || 'date',
				order: order || 'desc',
				match,
				dateBefore,
				dateAfter,
				dateBetween:
					dateBetween &&
					dateBetween.sort( ( a, b ) =>
						moment( a ).diff( moment( b ) )
					),
				typeIs,
				typeIsNot,
			};

			return {
				documents: getDocuments( query ),
				documentsError: getDocumentsError( query ),
				isLoading: isResolving( 'getDocuments', [ query ] ),
			};
		},
		[
			paged,
			perPage,
			orderby,
			order,
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
		]
	);

export const useDocumentsSummary = ( {
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	type_is: typeIs,
	type_is_not: typeIsNot,
}: Query ): DocumentsSummary =>
	useSelect(
		( select ) => {
			const { getDocumentsSummary, isResolving } = select( STORE_NAME );

			const query = {
				match,
				dateBefore,
				dateAfter,
				dateBetween,
				typeIs,
				typeIsNot,
			};

			return {
				documentsSummary: getDocumentsSummary( query ),
				isLoading: isResolving( 'getDocumentsSummary', [ query ] ),
			};
		},
		[
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
		]
	);
