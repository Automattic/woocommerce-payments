/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Spinner, Flex, FlexItem } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
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
 * screen or a broken transaction URL. `fellBack` lets the caller explain the
 * redirect so the merchant isn't dropped onto the list with no context.
 */
const resolveDisputeRedirect = (
	dispute?: Dispute
): { url: string; fellBack: boolean } => {
	// `balance_transaction` is a transaction id string at runtime; only build the
	// deep link when it actually is one, so an unexpected shape (e.g. an expanded
	// object) fails safe to the disputes list rather than a broken transaction URL.
	const balanceTransaction = dispute?.charge?.balance_transaction as unknown;
	const transactionId =
		typeof balanceTransaction === 'string' ? balanceTransaction : undefined;

	if ( dispute?.payment_intent && transactionId ) {
		return {
			url: getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: dispute.payment_intent,
				transaction_id: transactionId,
				type: 'dispute',
			} ),
			fellBack: false,
		};
	}

	return {
		url: getAdminUrl( { page: 'wc-admin', path: '/payments/disputes' } ),
		fellBack: true,
	};
};

const RedirectToTransactionDetails: React.FC< { query: { id: string } } > = ( {
	query: { id: disputeId },
} ) => {
	const { dispute, isLoading } = useDispute( disputeId );
	const { createInfoNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		// `isLoading` is derived from `hasFinishedResolution`, so it stays true
		// until the dispute query settles — only redirect once it has.
		if ( isLoading ) {
			return;
		}

		const { url, fellBack } = resolveDisputeRedirect( dispute );

		if ( fellBack ) {
			createInfoNotice(
				__(
					"We couldn't open that dispute directly. Find it in your disputes list below.",
					'woocommerce-payments'
				),
				{ type: 'snackbar' }
			);
		}

		getHistory().replace( url );
	}, [ dispute, isLoading, createInfoNotice ] );

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
					<div>{ __( 'Redirecting…', 'woocommerce-payments' ) }</div>
				</FlexItem>
			</Flex>
		</Page>
	);
};

export default RedirectToTransactionDetails;
