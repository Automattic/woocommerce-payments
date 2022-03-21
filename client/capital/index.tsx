/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { __, _n } from '@wordpress/i18n';
import { TableCard } from '@woocommerce/components';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import ErrorBoundary from 'components/error-boundary';
import ActiveLoanSummary from 'components/active-loan-summary';
import { formatExplicitCurrency, isZeroDecimalCurrency } from 'utils/currency';
import { CapitalLoan } from 'data/capital/types';
import ClickableCell from 'components/clickable-cell';
import Chip from 'components/chip';
import { useLoans } from 'wcpay/data';
import { getAdminUrl } from 'wcpay/utils';

import './style.scss';

const columns = [
	{
		key: 'paid_out_at',
		label: __( 'Dispursed', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Dispursed', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		defaultSort: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Status', 'woocommerce-payments' ),
		required: true,
		cellClassName: 'is-center-aligned',
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: false,
		isNumeric: true,
	},
	{
		key: 'fee_amount',
		label: __( 'Fixed fee', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Fixed fee', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: false,
		isNumeric: true,
	},
	{
		key: 'withhold_rate',
		label: __( 'Withhold rate', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Withhold rate', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: false,
		isNumeric: true,
	},
	{
		key: 'first_paydown_at',
		label: __( 'First paydown', 'woocommerce-payments' ),
		screenReaderLabel: __( 'First paydown', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: false,
		isNumeric: true, // Hack: this is not a numeric field, but "isNumeric" is needed for it to be right-aligned
	},
];

const getLoanStatusText = ( loan: CapitalLoan ) => {
	return loan.fully_paid_at
		? __( 'Paid off', 'woocommerce-payments' ) +
				': ' +
				dateI18n( 'M j, Y', loan.fully_paid_at )
		: __( 'Active', 'woocommerce-payments' );
};

const getLoanStatusChip = ( loan: CapitalLoan ) => {
	return (
		<Chip
			message={ getLoanStatusText( loan ) }
			type={ loan.fully_paid_at ? 'primary' : 'warning' }
		/>
	);
};

const getRowsData = ( loans: CapitalLoan[] ) =>
	loans.map( ( loan ) => {
		const clickable = ( children: React.ReactNode ) => (
			<ClickableCell
				href={ getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
					type: 'charge',
					filter: 'advanced',
					loan_id_is: loan.stripe_loan_id,
				} ) }
			>
				{ children }
			</ClickableCell>
		);

		const data = {
			paid_out_at: {
				value: loan.paid_out_at,
				display: clickable( dateI18n( 'M j, Y', loan.paid_out_at ) ),
			},
			status: {
				value: getLoanStatusText( loan ),
				display: clickable( getLoanStatusChip( loan ) ),
			},
			amount: {
				value: isZeroDecimalCurrency( loan.currency )
					? loan.amount
					: loan.amount / 100,
				display: clickable(
					formatExplicitCurrency(
						loan.amount,
						loan.currency.toUpperCase()
					)
				),
			},
			fee_amount: {
				value: isZeroDecimalCurrency( loan.currency )
					? loan.fee_amount
					: loan.fee_amount / 100,
				display: clickable(
					formatExplicitCurrency(
						loan.fee_amount,
						loan.currency.toUpperCase()
					)
				),
			},
			withhold_rate: {
				value: loan.withhold_rate,
				display: clickable(
					+( loan.withhold_rate * 100 ).toFixed( 2 ) + '%'
				),
			},
			first_paydown_at: {
				value: loan.first_paydown_at,
				display: clickable(
					loan.first_paydown_at
						? dateI18n( 'M j, Y', loan.first_paydown_at )
						: '-'
				),
			},
		} as Record<
			string,
			{ value: string | number; display: React.ReactNode }
		>;

		return columns.map( ( { key } ) => data[ key ] );
	} );

const getSummary = ( loans: CapitalLoan[] ) => {
	if ( ! loans.length ) {
		return [];
	}

	const summary = [
		{
			label: _n( 'loan', 'loans', loans.length, 'woocommerce-payments' ),
			value: String( loans.length ),
		},
	];

	const currencies = Array.from(
		new Set( loans.map( ( l ) => l.currency ) )
	);
	if ( 1 === currencies.length ) {
		summary.push( {
			label: __( 'total', 'woocommerce-payments' ),
			value: formatExplicitCurrency(
				loans.reduce(
					( acc: number, loan: CapitalLoan ) => acc + loan.amount,
					0
				),
				currencies[ 0 ]
			),
		} );
		summary.push( {
			label: __( 'fixed fees', 'woocommerce-payments' ),
			value: formatExplicitCurrency(
				loans.reduce(
					( acc: number, loan: CapitalLoan ) => acc + loan.fee_amount,
					0
				),
				currencies[ 0 ]
			),
		} );
	}
	return summary;
};

const CapitalPage = (): JSX.Element => {
	const { loans, isLoading } = useLoans();

	return (
		<Page>
			<TestModeNotice topic={ topics.loans } />
			{ wcpaySettings.accountLoans.has_active_loan && (
				<ErrorBoundary>
					<ActiveLoanSummary />
				</ErrorBoundary>
			) }
			<TableCard
				className="wcpay-loans-list"
				title={ __( 'All loans', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				totalRows={ loans.length }
				headers={ columns }
				rows={ getRowsData( loans ) }
				summary={ getSummary( loans ) }
				showMenu={ false }
			/>
		</Page>
	);
};

export default CapitalPage;
