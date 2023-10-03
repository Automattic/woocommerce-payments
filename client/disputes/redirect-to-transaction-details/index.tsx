/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { getHistory } from '@woocommerce/navigation';
import { Spinner, Icon, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { useDispute } from 'data/index';
import { Charge } from 'wcpay/types/charges';
import { getAdminUrl } from 'wcpay/utils';

import './style.scss';
import warning from 'wcpay/components/icons/warning';

const RedirectToTransactionDetails: React.FC< { query: { id: string } } > = ( {
	query: { id: disputeId },
} ) => {
	const { dispute, error, isLoading } = useDispute( disputeId );

	useEffect( () => {
		if ( ! isLoading && dispute?.charge ) {
			// Dispute type allows charge as nested object or string ID,
			// so we have to hint we expect a Charge object here.
			const chargeObject = dispute.charge as Charge;
			const transactionDetailsUrl = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: chargeObject.payment_intent,
				transaction_id: chargeObject.balance_transaction,
				type: 'dispute',
			} );
			getHistory().replace( transactionDetailsUrl );
		}
	}, [ dispute, isLoading ] );

	return (
		<Page>
			<Flex
				direction="column"
				className="wcpay-dispute-detail-legacy-redirect"
			>
				{ error ? (
					<>
						<FlexItem>
							<Icon icon={ warning } type="warning" size={ 32 } />
						</FlexItem>
						<FlexItem>
							<div>
								<b>Error retrieving dispute</b>
							</div>
							<div>Please check your network and try again.</div>
						</FlexItem>
					</>
				) : (
					<>
						<FlexItem>
							<Spinner />
						</FlexItem>
						<FlexItem>
							<div>
								<b>One moment please</b>
							</div>
							<div>Redirecting to payment details…</div>
						</FlexItem>
					</>
				) }
			</Flex>
		</Page>
	);
};

export default RedirectToTransactionDetails;
