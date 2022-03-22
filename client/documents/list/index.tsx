/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard, TableCardColumn } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { displayType } from 'documents/strings';
import { Document, useDocuments, useDocumentsSummary } from 'data/index';
import './style.scss';
import DocumentsFilters from '../filters';
import Page from '../../components/page';
import { getViewDocumentUrl } from 'wcpay/utils';

interface Column extends TableCardColumn {
	key: 'date' | 'type' | 'description' | 'download';
	visible?: boolean;
	cellClassName?: string;
}

const getColumns = (): Column[] =>
	[
		{
			key: 'date',
			label: __( 'Date', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Date and time', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
			defaultOrder: 'desc',
			cellClassName: 'date',
			isSortable: true,
			defaultSort: true,
		},
		{
			key: 'type',
			label: __( 'Type', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Type', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
		},
		{
			key: 'description',
			label: __( 'Description', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Description', 'woocommerce-payments' ),
		},
		{
			key: 'download',
			label: '',
			screenReaderLabel: __( 'Download', 'woocommerce-payments' ),
			isLeftAligned: false,
			isNumeric: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

const getDocumentDescription = ( document: Document ) => {
	switch ( document.type ) {
		case 'test_document':
			if ( document.period_from && document.period_to ) {
				return sprintf(
					__(
						'This is a test document for %s to %s',
						'woocommerce-payments'
					),
					dateI18n(
						'M j, Y',
						moment.utc( document.period_from ).toISOString(),
						'utc'
					),
					dateI18n(
						'M j, Y',
						moment.utc( document.period_to ).toISOString(),
						'utc'
					)
				);
			}
			return __( 'This is a test document', 'woocommerce-payments' );
			break;

		default:
			return __( 'Unknown document type', 'woocommerce-payments' );
			break;
	}
};

export const DocumentsList = (): JSX.Element => {
	const { documents, isLoading } = useDocuments( getQuery() );
	const {
		documentsSummary,
		isLoading: isSummaryLoading,
	} = useDocumentsSummary( getQuery() );

	const columnsToDisplay = getColumns();

	const totalRows = documentsSummary.count || 0;
	const rows = documents.map( ( document: Document ) => {
		const documentType =
			displayType[ document.type ] ??
			__( 'Unknown document type', 'woocommerce-payments' );
		// Map document into table row.
		const data = {
			date: {
				value: document.date,
				display: dateI18n(
					'M j, Y',
					moment.utc( document.date ).local().toISOString()
				),
			},
			type: {
				value: documentType,
				display: documentType,
			},
			description: {
				value: getDocumentDescription( document ),
				display: getDocumentDescription( document ),
			},
			download: {
				value: getViewDocumentUrl( document.document_id ),
				display: (
					<a
						href={ getViewDocumentUrl( document.document_id ) }
						rel="noopener noreferrer"
						target="_blank"
						style={ { display: 'inline' } }
					>
						{ __( 'Download', 'woocommerce-payments' ) }
					</a>
				),
			},
		};

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

	const title = __( 'Documents', 'woocommerce-payments' );

	// initializing summary with undefined as we don't want to render the TableSummary component unless we have the data
	let summary;
	const isDocumentsSummaryDataLoaded =
		documentsSummary.count !== undefined && false === isSummaryLoading;

	// Generate summary only if the data has been loaded
	if ( isDocumentsSummaryDataLoaded ) {
		summary = [
			{
				label: _n(
					'document',
					'documents',
					// We've already checked that `.count` is not undefined, but TypeScript doesn't detect
					// that so we remove the `undefined` in the type manually.
					documentsSummary.count as number,
					'woocommerce-payments'
				),
				value: `${ documentsSummary.count }`,
			},
		];
	}

	return (
		<Page>
			<DocumentsFilters />
			<TableCard
				className="documents-list woocommerce-report-table has-search"
				title={ title }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				actions={ [] }
			/>
		</Page>
	);
};

export default DocumentsList;
