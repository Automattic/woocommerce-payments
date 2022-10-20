/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TableCard, TableCardColumn } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import Page from '../../components/page';
import { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';
import { formatExplicitCurrency } from 'utils/currency';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';

interface Column extends TableCardColumn {
	key:
		| 'authorization_id'
		| 'created'
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
			key: 'authorization_id',
			label: __( 'Authorization Id', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
		{
			key: 'created',
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

export const AuthorizationsList = (): JSX.Element => {
	const columnsToDisplay = getColumns();
	const {
		authorizationsSummary,
		isLoading: isSummaryLoading,
	} = useAuthorizationsSummary( getQuery() );

	const { authorizations, isLoading } = useAuthorizations( getQuery() );

	const rows = authorizations.map( ( auth ) => {
		const stringAmount = String( auth.amount );
		const riskLevel = <RiskLevel risk={ auth.risk_level } />;
		const detailsURL = getDetailsURL(
			auth.payment_intent_id,
			'transactions'
		);

		const clickable = ( children: JSX.Element | string ) => (
			<ClickableCell href={ detailsURL }>{ children }</ClickableCell>
		);

		const data = {
			authorization_id: {
				// The authorization id is not exposed. Using the payment intent id as unique identifier
				value: auth.payment_intent_id,
				display: auth.payment_intent_id,
			},
			created: {
				value: dateI18n(
					'M j, Y / g:iA',
					moment.utc( auth.created ).local().toISOString()
				),
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment.utc( auth.created ).local().toISOString()
					)
				),
			},
			// Payments are authorized for a maximum of 7 days
			capture_by: {
				value: dateI18n(
					'M j, Y / g:iA',
					moment
						.utc( auth.created )
						.add( 7, 'd' )
						.local()
						.toISOString()
				),
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment
							.utc( auth.created )
							.add( 7, 'd' )
							.local()
							.toISOString()
					)
				),
			},
			order: {
				value: auth.order_id,
				display: clickable(
					`#${ auth.order_id } from ${ auth.customer_name }`
				),
			},
			risk_level: {
				value: calculateRiskMapping( auth.risk_level ),
				display: clickable( riskLevel ),
			},
			amount: {
				value: auth.amount,
				display: clickable(
					formatExplicitCurrency( auth.amount, auth.currency )
				),
			},
			customer_email: {
				value: auth.customer_email,
				display: clickable( auth.customer_email ),
			},
			customer_country: {
				value: auth.customer_country,
				display: clickable( auth.customer_country ),
			},
		};

		return columnsToDisplay.map(
			( { key } ) =>
				data[ key ] || {
					display: null,
				}
		);
	} );

	let summary;

	const isAuthorizationsSummaryLoaded =
		authorizationsSummary.count !== undefined &&
		authorizationsSummary.total !== undefined &&
		false === isSummaryLoading;

	if ( isAuthorizationsSummaryLoaded ) {
		summary = [
			{
				label: __( 'authorization(s)', 'woocommerce-payments' ),
				value: String( authorizationsSummary.count ),
			},
		];

		summary.push( {
			label: __( 'total', 'woocommerce-payments' ),
			value: `${ formatExplicitCurrency(
				// We've already checked that `.total` is not undefined, but TypeScript doesn't detect
				// that so we remove the `undefined` in the type manually.
				authorizationsSummary.total as number,
				authorizationsSummary.currency
			) }`,
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
