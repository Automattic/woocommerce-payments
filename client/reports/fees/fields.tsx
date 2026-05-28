/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import type { Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import { getDetailsURL } from 'wcpay/components/details-link';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { formatStringValue, getAdminUrl } from 'wcpay/utils';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { displayMethod, displayType } from './strings';

const EMPTY = '–';

interface FilterElement {
	value: string;
	label: string;
}

interface GetFeesFieldsArgs {
	dateElements: FilterElement[];
	methodElements: FilterElement[];
	typeElements: FilterElement[];
}

const getTransactionURL = ( row: ReportsFee ): string => {
	const detailsURL = getDetailsURL(
		row.payment_id || row.transaction_id,
		'transactions'
	);
	return `${ detailsURL }&transaction_id=${ row.transaction_id }&transaction_type=${ row.type }`;
};

const getOrderURL = ( orderId: ReportsFee[ 'order_id' ] ): string =>
	getAdminUrl( {
		page: 'wc-orders',
		action: 'edit',
		id: orderId ?? '',
	} );

export const getFeesFields = ( {
	dateElements,
	methodElements,
	typeElements,
}: GetFeesFieldsArgs ): Field< ReportsFee >[] =>
	[
		{
			id: 'date',
			label: __( 'Date', 'woocommerce-payments' ),
			header: __( 'Date & time', 'woocommerce-payments' ),
			enableSorting: true,
			enableGlobalSearch: false,
			elements: dateElements,
			filterBy: { isPrimary: true, operators: [ 'is' ] },
			getValue: ( { item }: { item: ReportsFee } ) => item.date,
			render: ( { item }: { item: ReportsFee } ) => (
				<>
					{ formatDateTimeFromString( item.date, {
						includeTime: true,
					} ) }
				</>
			),
		},
		{
			id: 'payment_method',
			label: __( 'Method', 'woocommerce-payments' ),
			elements: methodElements,
			filterBy: { operators: [ 'is' ] },
			getValue: ( { item }: { item: ReportsFee } ) =>
				item.payment_method?.type || '',
			render: ( { item }: { item: ReportsFee } ) => (
				<>{ displayMethod( item.payment_method?.type ) || EMPTY }</>
			),
		},
		{
			id: 'type',
			label: __( 'Type', 'woocommerce-payments' ),
			elements: typeElements,
			filterBy: { operators: [ 'is' ] },
			getValue: ( { item }: { item: ReportsFee } ) => item.type,
			render: ( { item }: { item: ReportsFee } ) => {
				const label =
					displayType[ item.type as keyof typeof displayType ] ||
					formatStringValue( item.type );
				return <>{ label }</>;
			},
		},
		{
			id: 'order_id',
			label: __( 'Order ID', 'woocommerce-payments' ),
			getValue: ( { item }: { item: ReportsFee } ) => item.order_id ?? '',
			render: ( { item }: { item: ReportsFee } ) =>
				item.order_id ? (
					<Link href={ getOrderURL( item.order_id ) } type="external">
						{ String( item.order_id ) }
					</Link>
				) : (
					<>{ EMPTY }</>
				),
		},
		// Link only cells that have a concrete details destination. Other
		// cells render plain text rather than using the old `ClickableCell`
		// wrapper, which hid anchors from the tab order with `tabIndex="-1"`.
		{
			id: 'transaction_id',
			label: __( 'Transaction ID', 'woocommerce-payments' ),
			enableHiding: false,
			getValue: ( { item }: { item: ReportsFee } ) => item.transaction_id,
			render: ( { item }: { item: ReportsFee } ) => (
				<Link href={ getTransactionURL( item ) }>
					{ item.transaction_id }
				</Link>
			),
		},
		{
			id: 'transaction_currency',
			label: __( 'Currency', 'woocommerce-payments' ),
			getValue: ( { item }: { item: ReportsFee } ) =>
				( item.transaction_currency ?? '' ).toUpperCase(),
			render: ( { item }: { item: ReportsFee } ) => (
				<>
					{ ( item.transaction_currency ?? '' ).toUpperCase() ||
						EMPTY }
				</>
			),
		},
		{
			id: 'amount',
			label: __( 'Gross amount', 'woocommerce-payments' ),
			type: 'integer',
			enableSorting: true,
			getValue: ( { item }: { item: ReportsFee } ) => item.amount,
			render: ( { item }: { item: ReportsFee } ) => (
				<>
					{ formatExplicitCurrency(
						item.amount,
						item.deposit_currency
					) }
				</>
			),
		},
		{
			id: 'fees',
			label: __( 'Fees total', 'woocommerce-payments' ),
			type: 'integer',
			enableSorting: true,
			getValue: ( { item }: { item: ReportsFee } ) => item.fees,
			render: ( { item }: { item: ReportsFee } ) => (
				<>
					{ formatExplicitCurrency(
						item.fees,
						item.deposit_currency
					) }
				</>
			),
		},
		{
			id: 'deposit_date',
			label: __( 'Settlement date', 'woocommerce-payments' ),
			type: 'datetime',
			getValue: ( { item }: { item: ReportsFee } ) =>
				item.deposit_date ?? '',
			render: ( { item }: { item: ReportsFee } ) => (
				<>
					{ item.deposit_date
						? formatDateTimeFromString( item.deposit_date )
						: EMPTY }
				</>
			),
		},
		{
			id: 'deposit_id',
			label: __( 'Payout ID', 'woocommerce-payments' ),
			getValue: ( { item }: { item: ReportsFee } ) =>
				item.deposit_id ?? '',
			render: ( { item }: { item: ReportsFee } ) =>
				item.deposit_id ? (
					<Link href={ getDetailsURL( item.deposit_id, 'payouts' ) }>
						{ item.deposit_id }
					</Link>
				) : (
					<>{ EMPTY }</>
				),
		},
	] as Field< ReportsFee >[];
