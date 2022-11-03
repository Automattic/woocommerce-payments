/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { sprintf, __ } from '@wordpress/i18n';
import { Link, TableCard, TableCardColumn } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import Page from '../../components/page';
import CaptureAuthorizationButton from 'wcpay/components/capture-authorization-button';
import RiskLevelComponent, {
	calculateRiskMapping,
} from 'components/risk-level';
import { getDetailsURL } from 'wcpay/components/details-link';
import ClickableCell from 'wcpay/components/clickable-cell';
import wcpayTracks from 'tracks';
import { formatExplicitCurrency } from 'wcpay/utils/currency';

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
		{
			key: 'action',
			label: __( 'Action', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Action', 'woocommerce-payments' ),
			visible: true,
			required: true,
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

		const paymentDetailsUrl = getDetailsURL(
			auth.payment_intent_id || auth.charge_id,
			'transactions'
		);

		const clickable = ( children: React.ReactNode ) => (
			<ClickableCell href={ paymentDetailsUrl }>
				{ children }
			</ClickableCell>
		);

		const data = {
			authorized_on: {
				value: auth.created,
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment.utc( auth.created ).local().toISOString()
					)
				),
			},
			capture_by: {
				value: moment
					.utc( auth.created )
					.add( 7, 'days' )
					.toISOString(),
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment
							.utc( auth.created )
							.add( 7, 'days' )
							.toISOString()
					)
				),
			},
			order: {
				value: auth.order_id,
				display: auth.order_id ? (
					<Link
						href={ `post.php?post=${ auth.order_id }&action=edit` }
						type="wp-admin"
					>
						{
							// translators: %1$s Order identifier %2$ Customer name.
							auth.customer_name
								? sprintf(
										__(
											'#%1$s from %2$s',
											'woocommerce-payments'
										),
										auth.order_id,
										auth.customer_name ?? ''
								  )
								: `#${ auth.order_id }`
						}
					</Link>
				) : (
					__( 'N/A', 'woocommerce-payments' )
				),
			},
			risk_level: {
				value: calculateRiskMapping( auth.risk_level ),
				display: clickable(
					<RiskLevelComponent risk={ auth.risk_level } />
				),
			},
			amount: {
				value: auth.amount,
				display: clickable(
					getFormatedAmountFromString( stringAmount )
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
			action: {
				display: (
					<CaptureAuthorizationButton
						orderId={ auth.order_id }
						paymentIntentId={ auth.payment_intent_id }
						onClick={ () => {
							wcpayTracks.recordEvent(
								'payments_transactions_uncaptured_list_capture_charge_button_click',
								{
									payment_intent_id: auth.payment_intent_id,
								}
							);
						} }
					/>
				),
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
		false === isSummaryLoading;

	if ( isAuthorizationsSummaryLoaded ) {
		summary = [
			{
				label: __( 'authorization(s)', 'woocommerce-payments' ),
				value: String( authorizationsSummary.count ),
			},
		];

		if (
			authorizationsSummary.count &&
			authorizationsSummary.count > 0 &&
			authorizationsSummary.all_currencies &&
			authorizationsSummary.all_currencies.length === 1
		) {
			// Only show the total if there is one currency available
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
