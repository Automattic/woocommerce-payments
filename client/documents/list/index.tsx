/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __, _n } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard, TableCardColumn } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useDocuments, useDocumentsSummary } from 'data/index';
import './style.scss';
import DocumentsFilters from '../filters';
import Page from '../../components/page';

interface Column extends TableCardColumn {
	key: 'date' | 'type' | 'description' | 'download';
	visible?: boolean;
	cellClassName?: string;
}

const getColumns = (): Column[] =>
	[
		{
			key: 'date',
			label: __( 'Date / Time', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Date and time', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
			defaultOrder: 'desc',
			cellClassName: 'date-time',
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
			isNumeric: true,
			isSortable: true,
		},
		{
			key: 'download',
			label: __( 'Download', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Download', 'woocommerce-payments' ),
			isNumeric: true,
			isSortable: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const DocumentsList = (): JSX.Element => {
	const { documents, isLoading } = useDocuments( getQuery() );
	const {
		documentsSummary,
		isLoading: isSummaryLoading,
	} = useDocumentsSummary( getQuery() );

	const columnsToDisplay = getColumns();

	const totalRows = documentsSummary.count || 0;
	const rows = documents.map( ( document ) => {
		// Map document into table row.
		const data = {
			date: {
				value: document.date,
				display: dateI18n(
					'M j, Y / g:iA',
					moment.utc( document.date ).local().toISOString()
				),
			},
			type: {
				value: document.type,
				display: document.type,
			},
			description: {
				value: document.description,
				display: document.description,
			},
			download: {
				value: document.download,
				display: document.download,
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
