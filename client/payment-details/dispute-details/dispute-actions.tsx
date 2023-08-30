/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Flex } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getAdminUrl } from 'wcpay/utils';
import type { Dispute } from 'wcpay/types/disputes';
import { STORE_NAME } from 'wcpay/data/constants';

export const useDisputeAccept = (
	dispute: Dispute
): {
	doAccept: () => void;
	isLoading: boolean;
} => {
	const { isLoading } = useSelect(
		( select ) => {
			const { isResolving } = select( STORE_NAME );

			return {
				isLoading:
					isResolving( 'getDispute', [ dispute.id ] ) ||
					isResolving( 'getPaymentIntent', [
						dispute.payment_intent,
					] ),
			};
		},
		[ dispute.id ]
	);
	const { acceptTransactionDetailsDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptTransactionDetailsDispute( dispute );
	return { doAccept, isLoading };
};

interface Props {
	/**
	 * The dispute ID.
	 */
	dispute: Dispute;
}
const DisputeActions: React.FC< Props > = ( { dispute } ) => {
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	return (
		<Flex justify="start">
			<Button
				variant="primary"
				disabled={ isLoading }
				onClick={ () => {
					// TODO: Tracks event
					// wcpayTracks.recordEvent(
					// 	'wcpay_dispute_challenge_clicked',
					// 	{}
					// );
					const challengeUrl = getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes/challenge',
						id: dispute.id,
					} );
					getHistory().push( challengeUrl );
				} }
			>
				{ hasStagedEvidence
					? __( 'Continue with challenge', 'woocommerce-payments' )
					: __( 'Challenge dispute', 'woocommerce-payments' ) }
			</Button>

			<Button
				variant="tertiary"
				disabled={ isLoading }
				onClick={ () => {
					// Open modal
				} }
			>
				{ __( 'Accept dispute', 'woocommerce-payments' ) }
			</Button>
		</Flex>
	);
};

export default DisputeActions;
