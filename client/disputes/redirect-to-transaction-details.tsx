/**
 * External dependencies
 */
import React from 'react';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { useDispute } from 'data/index';
import { Charge } from 'wcpay/types/charges';
import { Dispute } from 'wcpay/types/disputes';
import { getAdminUrl } from 'wcpay/utils';

const RedirectToTransactionDetails = ( {
	query: { id: disputeId },
}: {
	query: { id: string };
} ): JSX.Element | null => {
	const { dispute, isLoading } = useDispute( disputeId );
	const disputeObject = dispute || ( {} as Dispute );
	const disputeIsAvailable = ! isLoading && dispute && disputeObject.id;
	// Why would dispute.charge ever be a string?
	const chargeObject = disputeObject.charge as Charge;

	let transactionDetailsUrl = '';
	if ( disputeIsAvailable ) {
		const paymentIntentId = disputeObject.charge;
		transactionDetailsUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions/details',
			id: chargeObject.payment_intent,
			transaction_id: chargeObject.balance_transaction,
			type: 'dispute',
		} );
		getHistory().replace( transactionDetailsUrl );
		return null;
	}

	return (
		<Page>
			<h1>
				We gonna redirect to
				<a href={ transactionDetailsUrl }>transaction details</a>
			</h1>
			<pre>{ transactionDetailsUrl }</pre>
		</Page>
	);
};

export default RedirectToTransactionDetails;
