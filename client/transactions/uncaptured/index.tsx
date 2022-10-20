/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TableCard, TableCardColumn } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import Page from '../../components/page';

interface Column extends TableCardColumn {
	key:
		| 'authorized_on'
		| 'capture_by'
		| 'order'
		| 'risk_level'
		| 'amount'
		| 'customer_email'
		| 'customer_country';
	visible?: boolean;
	cellClassName?: string;
}

const getColumns = (): Column[] =>
	[
		{
			key: 'authorized_on',
			label: __( 'Authorized on', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Authorized on', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
			defaultOrder: 'desc',
			cellClassName: 'date-time',
			isSortable: true,
			defaultSort: true,
		},
		{
			key: 'capture_by',
			label: __( 'Capture by', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Capture by', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
			defaultOrder: 'desc',
			cellClassName: 'date-time',
			isSortable: true,
			defaultSort: true,
		},
		{
			key: 'order',
			label: __( 'Order', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Order number', 'woocommerce-payments' ),
			required: true,
		},
		{
			key: 'risk_level',
			label: __( 'Risk level', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Risk level of transaction',
				'woocommerce-payments'
			),
			isLeftAligned: true,
		},
		{
			key: 'amount',
			label: __( 'Amount', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
			isNumeric: true,
			isSortable: true,
		},
		{
			key: 'customer_email',
			label: __( 'Email', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Email', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
		{
			key: 'customer_country',
			label: __( 'Country', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Country', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

const getFormatedAmountFromString = ( string: string ) => {
	return `${ string.substring( 0, string.length - 2 ) }.${ string.substring(
		string.length - 2
	) }$`;
};

export const AuthorizationsList = (): JSX.Element => {
	const columnsToDisplay = getColumns();
	const {
		authorizationsSummary,
		isLoading: isSummaryLoading,
	} = useAuthorizationsSummary( getQuery() );

	const { authorizations, isLoading } = useAuthorizations( getQuery() );

	const rows = authorizations.map( ( auth ) => {
		const stringAmount = String( auth.amount );

		const data = {
			authorized_on: {
				value: auth.created,
				display: auth.created, // TODO format date. Will be done on components PR
			},
			capture_by: {
				value: auth.created,
				display: auth.created,
			},
			order: {
				value: auth.order_id,
				display: auth.order_id, // TODO add link to order
			},
			risk_level: {
				value: auth.risk_level,
				display: auth.risk_level, // TODO use RiskLevel component. Will be done on components PR.
			},
			amount: {
				value: auth.amount,
				display: getFormatedAmountFromString( stringAmount ),
			},
			customer_email: {
				value: auth.customer_email,
				display: auth.customer_email,
			},
			customer_country: {
				value: auth.customer_country,
				display: auth.customer_country,
			},
		};

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

	let summary;

	const isAuthorizationsSummaryLoaded =
		authorizationsSummary.count !== undefined &&
		authorizationsSummary.total !== undefined &&
		authorizationsSummary.totalAmount !== undefined &&
		false === isSummaryLoading;

	if ( isAuthorizationsSummaryLoaded ) {
		summary = [
			{
				value: String( authorizationsSummary.count ),
				label: 'authorizations',
			},
		];

		summary.push( {
			value: String( authorizationsSummary.total ),
			label: 'total',
		} );

		summary.push( {
			value: getFormatedAmountFromString(
				String( authorizationsSummary.totalAmount )
			),
			label: 'Pending',
		} );
	}

	return (
		<Page>
			<TableCard
				className="authorizations-list woocommerce-report-table has-search"
				title={ __(
					'Uncaptured transactions',
					'woocommerce-payments'
				) }
				isLoading={ isLoading || isSummaryLoading }
				rowsPerPage={ 10 }
				totalRows={ 2 }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
			/>
		</Page>
	);
};

export default AuthorizationsList;
