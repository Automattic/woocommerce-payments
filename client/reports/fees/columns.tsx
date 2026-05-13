/** @format */

/**
 * External dependencies
 */
import type {
	TableCardBodyColumn,
	TableCardColumn,
} from '@woocommerce/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ReportsFee } from 'wcpay/data/reports/hooks';

export const emptyFeesValue = '\u2013';

export type FeesColumnKey =
	| 'date'
	| 'payment_method'
	| 'type'
	| 'order_id'
	| 'transaction_id'
	| 'transaction_currency'
	| 'amount'
	| 'fees'
	| 'deposit_date'
	| 'deposit_id';

export interface Column extends TableCardColumn {
	key: FeesColumnKey;
	visible: boolean;
	cellClassName?: string;
}

export const getFeesColumns = (): Column[] => [
	{
		key: 'date',
		label: __( 'Date & time', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Date and time', 'woocommerce-payments' ),
		visible: true,
		isLeftAligned: true,
		defaultOrder: 'desc',
		cellClassName: 'date-time',
		isSortable: true,
		defaultSort: true,
	},
	{
		key: 'payment_method',
		label: __( 'Method', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Payment method', 'woocommerce-payments' ),
		visible: true,
		isLeftAligned: true,
		isSortable: false,
	},
	{
		key: 'type',
		label: __( 'Type', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Type', 'woocommerce-payments' ),
		visible: true,
		isLeftAligned: true,
		isSortable: false,
	},
	{
		key: 'order_id',
		label: __( 'Order ID', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Order ID', 'woocommerce-payments' ),
		visible: true,
		isLeftAligned: true,
	},
	{
		key: 'transaction_id',
		label: __( 'Transaction ID', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Transaction ID', 'woocommerce-payments' ),
		visible: true,
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'transaction_currency',
		label: __( 'Currency', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Currency', 'woocommerce-payments' ),
		visible: true,
	},
	{
		key: 'amount',
		label: __( 'Gross amount', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Gross amount', 'woocommerce-payments' ),
		visible: true,
		isNumeric: true,
		isSortable: true,
	},
	{
		key: 'fees',
		label: __( 'Fees total', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Fees total', 'woocommerce-payments' ),
		visible: true,
		isNumeric: true,
		isSortable: true,
	},
	{
		key: 'deposit_date',
		label: __( 'Settlement date', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Settlement date', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'deposit_id',
		label: __( 'Payout ID', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Payout ID', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
];

export const getFeesColumnCell = (
	row: Partial< ReportsFee >,
	key: FeesColumnKey
): TableCardBodyColumn => {
	if ( key === 'payment_method' ) {
		return {
			value: row.payment_method?.type || '',
			display: row.payment_method?.type || emptyFeesValue,
		};
	}

	const value = row[ key ];

	if ( value === null || value === undefined || value === '' ) {
		return {
			value: '',
			display: emptyFeesValue,
		};
	}

	return {
		value,
		display: value,
	};
};
