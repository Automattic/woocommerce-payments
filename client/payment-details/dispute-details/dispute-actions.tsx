/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Flex, FlexItem, Icon, Modal } from '@wordpress/components';
import { backup, lock } from '@wordpress/icons';
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
				isLoading: isResolving( 'getDispute', [ dispute.id ] ),
			};
		},
		[ dispute.id ]
	);
	const { acceptTransactionDetailsDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptTransactionDetailsDispute( dispute );
	return { doAccept, isLoading };
};

interface Props {
	dispute: Dispute;
}
const DisputeActions: React.FC< Props > = ( { dispute } ) => {
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );

	const onClose = () => {
		setModalOpen( false );
	};

	const onSubmit = () => {
		// TODO: Tracks event
		// wcpayTracks.recordEvent(
		// 	'wcpay_dispute_challenge_clicked',
		// 	{}
		// );
		setModalOpen( false );
		doAccept();
	};

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
					// TODO: Tracks event
					// wcpayTracks.recordEvent(
					// 	'wcpay_dispute_challenge_clicked',
					// 	{}
					// );
					setModalOpen( true );
				} }
			>
				{ __( 'Accept dispute', 'woocommerce-payments' ) }
			</Button>

			{ isModalOpen && (
				<Modal
					title="Accept the dispute?"
					onRequestClose={ onClose }
					className="transaction-details-dispute-accept-modal"
				>
					<p>
						<strong>
							{ __(
								'Before proceeding, please take note of the following:',
								'woocommerce-payments'
							) }
						</strong>
					</p>
					<Flex justify="start">
						<FlexItem>
							<Icon
								icon={ backup }
								size={ 24 }
								className="transaction-details-dispute-accept-modal__icon"
							/>
						</FlexItem>
						<FlexItem>
							{ __(
								'Accepting the dispute marks it as Lost. The disputed amount will be returned to the cardholder, with a $15 dispute fee deducted from your account',
								'woocommerce-payments'
							) }
						</FlexItem>
					</Flex>
					<Flex justify="start">
						<FlexItem>
							<Icon
								icon={ lock }
								size={ 24 }
								className="transaction-details-dispute-accept-modal__icon"
							/>
						</FlexItem>
						<FlexItem>
							{ __(
								'Accepting the dispute is final and cannot be undone.',
								'woocommerce-payments'
							) }
						</FlexItem>
					</Flex>

					<Flex
						className="transaction-details-dispute-accept-modal__actions"
						justify="end"
					>
						<Button variant="tertiary" onClick={ onClose }>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button variant="primary" onClick={ onSubmit }>
							{ __( 'Accept dispute', 'woocommerce-payments' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</Flex>
	);
};

export default DisputeActions;
