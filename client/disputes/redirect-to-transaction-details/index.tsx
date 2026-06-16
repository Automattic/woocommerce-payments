/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { Spinner, Flex, FlexItem } from '@wordpress/components';
import Page from 'components/page';
import { useDispute } from 'wcpay/data/disputes';
import { getAdminUrl } from 'wcpay/utils';
import type { Dispute } from 'wcpay/types/disputes';

import './style.scss';

/**
 * Resolves where the legacy `/payments/disputes/details` link should land.
 *
 * Prefers the dispute's transaction details screen. Falls back to the disputes
 * list — which always loads — when the dispute can't be retrieved or is missing
 * the ids needed to build the transaction details deep link (both can be absent
 * around dispute creation, before the balance transaction exists). This keeps
 * the dispute notification "view dispute" CTA from dead-ending on an error
 * screen or a broken transaction URL.
 */
const getDisputeRedirectUrl = ( dispute?: Dispute ): string => {
	const balanceTransaction = dispute?.charge?.balance_transaction;

	if ( dispute?.payment_intent && balanceTransaction ) {
		return getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions/details',
			id: dispute.payment_intent,
			transaction_id: balanceTransaction,
			type: 'dispute',
		} );
	}

	return getAdminUrl( {
		page: 'wc-admin',
		path: '/payments/disputes',
	} );
};

const RedirectToTransactionDetails: React.FC< { query: { id: string } } > = ( {
	query: { id: disputeId },
} ) => {
	const { dispute, isLoading } = useDispute( disputeId );

	useEffect( () => {
		if ( isLoading ) {
			return;
		}

		getHistory().replace( getDisputeRedirectUrl( dispute ) );
	}, [ dispute, isLoading ] );

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
						<b>
							{ __(
								'One moment please',
								'woocommerce-payments'
							) }
						</b>
					</div>
					<div>
						{ __(
							'Redirecting to payment details…',
							'woocommerce-payments'
						) }
					</div>
				</FlexItem>
			</Flex>
		</Page>
	);
};

export default RedirectToTransactionDetails;
