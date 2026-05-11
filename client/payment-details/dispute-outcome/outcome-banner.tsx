/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import DisputeStatusChip from 'wcpay/components/dispute-status-chip';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import { getBankName } from 'wcpay/utils/charge';
import type { Charge } from 'wcpay/types/charges';
import type { Dispute } from 'wcpay/types/disputes';
import type { BalanceTransaction } from 'wcpay/types/balance-transactions';
import './outcome-banner.scss';

interface Props {
	dispute: Dispute;
	charge: Charge;
}

const findTransaction = (
	dispute: Dispute,
	category: 'dispute' | 'dispute_reversal'
): BalanceTransaction | undefined =>
	dispute.balance_transactions.find(
		( txn ) => txn.reporting_category === category
	);

// Picks the balance transaction whose timestamp and amounts represent the
// outcome decision for the given status. Won outcomes are decided when the
// reversal posts; lost outcomes when the original deduction posts.
// Warning-closed inquiries typically have no balance_transactions.
const selectPrimaryTransaction = (
	dispute: Dispute
): BalanceTransaction | undefined => {
	if ( dispute.status === 'won' ) {
		return findTransaction( dispute, 'dispute_reversal' );
	}
	if ( dispute.status === 'lost' ) {
		return findTransaction( dispute, 'dispute' );
	}
	return undefined;
};

const placeholder = '—';

const renderAmount = (
	value: number | undefined,
	currency: string
): string => {
	if ( typeof value !== 'number' ) {
		return placeholder;
	}
	return formatExplicitCurrency( Math.abs( value ), currency );
};

const OutcomeBanner: React.FC< Props > = ( { dispute, charge } ) => {
	const primaryTxn = selectPrimaryTransaction( dispute );

	const deductedRaw = primaryTxn?.amount;
	const feesRaw = primaryTxn?.fee;
	const netRaw =
		typeof deductedRaw === 'number' && typeof feesRaw === 'number'
			? Math.abs( deductedRaw ) + Math.abs( feesRaw )
			: undefined;

	const decisionTimestamp = primaryTxn?.created ?? dispute.created;

	const issuerName =
		getBankName( charge ) ?? __( 'Unknown bank', 'woocommerce-payments' );

	return (
		<div
			className="dispute-outcome-banner"
			data-testid="dispute-outcome-banner"
		>
			<div className="dispute-outcome-banner__header">
				<DisputeStatusChip
					status={ dispute.status }
					prefixDisputeType
				/>
				<span className="dispute-outcome-banner__issuer">
					{ issuerName }
				</span>
			</div>

			<dl className="dispute-outcome-banner__amounts">
				<div className="dispute-outcome-banner__amount">
					<dt>{ __( 'Deducted', 'woocommerce-payments' ) }</dt>
					<dd>{ renderAmount( deductedRaw, dispute.currency ) }</dd>
				</div>
				<div className="dispute-outcome-banner__amount">
					<dt>{ __( 'Fees', 'woocommerce-payments' ) }</dt>
					<dd>{ renderAmount( feesRaw, dispute.currency ) }</dd>
				</div>
				<div className="dispute-outcome-banner__amount">
					<dt>{ __( 'Net', 'woocommerce-payments' ) }</dt>
					<dd>{ renderAmount( netRaw, dispute.currency ) }</dd>
				</div>
			</dl>

			<div className="dispute-outcome-banner__meta">
				<span className="dispute-outcome-banner__decision-date">
					{ __( 'Decision date:', 'woocommerce-payments' ) }{ ' ' }
					{ formatDateTimeFromTimestamp( decisionTimestamp ) }
				</span>
			</div>
		</div>
	);
};

export default OutcomeBanner;
