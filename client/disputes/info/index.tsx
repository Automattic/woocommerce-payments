/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import OrderLink from 'components/order-link';
import { getDetailsURL } from 'components/details-link';
import { reasons } from '../strings';
import { formatStringValue } from 'utils';
import { formatExplicitCurrency } from 'utils/currency';
import './style.scss';
import Loadable from 'components/loadable';
import { Dispute } from 'wcpay/types/disputes';

const fields: { key: string; label: string }[] = [
	{ key: 'created', label: __( 'Dispute date', 'woocommerce-payments' ) },
	{ key: 'amount', label: __( 'Disputed amount', 'woocommerce-payments' ) },
	{ key: 'dueBy', label: __( 'Respond by', 'woocommerce-payments' ) },
	{ key: 'reason', label: __( 'Reason', 'woocommerce-payments' ) },
	{ key: 'order', label: __( 'Order', 'woocommerce-payments' ) },
	{ key: 'customer', label: __( 'Customer', 'woocommerce-payments' ) },
	{
		key: 'transactionId',
		label: __( 'Transaction ID', 'woocommerce-payments' ),
	},
];

const composeTransactionIdLink = ( dispute: Dispute ): JSX.Element => {
	const chargeId =
		'object' === typeof dispute.charge ? dispute.charge.id : dispute.charge;
	return (
		<Link href={ getDetailsURL( chargeId, 'transactions' ) }>
			{ chargeId }
		</Link>
	);
};

const composeDisputeReason = ( dispute: Dispute ): string => {
	const reasonMapping = reasons[ dispute.reason ];
	return reasonMapping
		? reasonMapping.display
		: formatStringValue( dispute.reason );
};

const Info = ( {
	dispute,
	isLoading,
}: {
	dispute: Dispute;
	isLoading: boolean;
} ): JSX.Element => {
	const data: Record< string, any > = isLoading
		? {
				created: 'Created date',
				amount: 'Amount',
				dueBy: 'Due by date',
				reason: 'Dispute reason',
				order: 'Order link',
				customer: 'Customer name',
				transactionId: 'Transaction link',
		  }
		: {
				created: dateI18n(
					'M j, Y',
					moment( dispute.created * 1000 ).toISOString()
				),
				amount: formatExplicitCurrency(
					dispute.amount || 0,
					dispute.currency || 'USD'
				),
				dueBy: dispute.evidence_details
					? dateI18n(
							'M j, Y - g:iA',
							moment(
								dispute.evidence_details.due_by * 1000
							).toISOString()
					  )
					: null,
				reason: composeDisputeReason( dispute ),
				order: dispute.order ? (
					<OrderLink order={ dispute.order } />
				) : null,
				customer:
					'object' === typeof dispute.charge
						? dispute.charge.billing_details.name
						: null,
				transactionId: composeTransactionIdLink( dispute ),
		  };

	return (
		<div className="wcpay-dispute-info">
			{ fields.map( ( { key, label } ) => {
				if ( null == data[ key ] ) {
					return null;
				}
				return (
					<div key={ key } className="wcpay-dispute-info-item">
						<Loadable isLoading={ isLoading } display="inline">
							<span className="wcpay-dispute-info-key">{ `${ label }: ` }</span>
							<span className="wcpay-dispute-info-value">
								{ data[ key ] }
							</span>
						</Loadable>
					</div>
				);
			} ) }
		</div>
	);
};

export default Info;
