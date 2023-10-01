/**
 * External dependencies
 */
import React from 'react';
import { getHistory } from '@woocommerce/navigation';
import { Spinner, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { useDispute } from 'data/index';
import { Charge } from 'wcpay/types/charges';
import { Dispute } from 'wcpay/types/disputes';
import { getAdminUrl } from 'wcpay/utils';

import './style.scss';

const RedirectToTransactionDetails = ( {
	query: { id: disputeId },
}: {
	query: { id: string };
} ): JSX.Element | null => {
	const { dispute, isLoading } = useDispute( disputeId );
	const disputeObject = dispute || ( {} as Dispute );
	const disputeIsAvailable = ! isLoading && dispute && disputeObject.id;
	// Dispute type allows charge as nested object or string ID,
	// so we have to hint we expect a Charge object here.
	const chargeObject = disputeObject.charge as Charge;

	let transactionDetailsUrl = '';
	if ( disputeIsAvailable ) {
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
			<Flex
				direction="column"
				className="wcpay-dispute-detail-legacy-redirect"
			>
				<FlexItem>
					<Spinner />
				</FlexItem>
				<FlexItem>
					<div>
						<b>One moment please</b>
					</div>
					<div>Redirecting to payment details…</div>
				</FlexItem>
			</Flex>
		</Page>
	);
};

export default RedirectToTransactionDetails;
