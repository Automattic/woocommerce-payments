/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TableCard, TableCardColumn, Search } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import Page from '../../components/page';
import autocompleter from 'transactions/autocompleter';

interface Column extends TableCardColumn {
	key:
		| 'authorization_id'
		| 'authorized_on'
		| 'captured_by'
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
			key: 'authorization_id',
			label: __( 'Authorization Id', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
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
			key: 'captured_by',
			label: __( 'Captured by', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Captured by', 'woocommerce-payments' ),
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

	const getRiskColor = ( risk: string ) => {
		switch ( risk ) {
			case 'high':
				return 'crimson';
			case 'normal':
				return 'green';
			case 'elevated':
				return 'darkorange';
		}
	};

	const rows = authorizations.map( ( auth ) => {
		const stringAmount = String( auth.amount );

		const data = {
			authorization_id: {
				value: auth.authorization_id,
				display: auth.authorization_id,
			},
			authorized_on: {
				value: auth.authorized_on,
				display: auth.authorized_on,
			},
			captured_by: {
				value: auth.captured_by,
				display: auth.captured_by,
			},
			order: {
				value: auth.order.number,
				display: (
					<a
						href={ auth.order.url }
					>{ `#${ auth.order.number } from ${ auth.customer_name }` }</a>
				),
			},
			risk_level: {
				value: auth.risk_level,
				display: (
					<span style={ { color: getRiskColor( auth.risk_level ) } }>
						{ auth.risk_level.charAt( 0 ).toUpperCase() +
							auth.risk_level.slice( 1 ) }
					</span>
				),
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

	const summary = [];

	summary.push( {
		value: String( authorizationsSummary.count ),
		label: 'authorizations',
	} );

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
				actions={ [
					<Button
						isPrimary
						isBusy={ false }
						disabled={ false }
						key="capture-button"
					>
						{ __( 'Capture payments', 'woocommerce-payments' ) }
					</Button>,

					<Search
						allowFreeTextSearch={ true }
						inlineTags
						key="search"
						placeholder={ __( 'Search', 'woocommerce-payments' ) }
						showClearButton={ true }
						type={
							wcpaySettings.featureFlags.customSearch
								? 'custom'
								: 'customers'
						}
						autocompleter={ autocompleter }
					/>,
					/*downloadable && (
						<DownloadButton
							key="download"
							isDisabled={ isLoading || isDownloading }
							onClick={ onDownload }
						/>
					),*/
				] }
			/>
		</Page>
	);
};

export default AuthorizationsList;
