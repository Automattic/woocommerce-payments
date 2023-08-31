/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { backup, lock } from '@wordpress/icons';
import { createInterpolateElement } from '@wordpress/element';
import { Button, Flex, FlexItem, Icon, Modal } from '@wordpress/components';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';
import { useDisputeAccept } from 'wcpay/data';
import { getDisputeFee } from 'wcpay/disputes/utils';
import { formatCurrency } from 'wcpay/utils/currency';

interface Props {
	dispute: Dispute;
}
const DisputeActions: React.FC< Props > = ( { dispute } ) => {
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );

	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const disputeFee = getDisputeFee( dispute );

	const onClose = () => {
		setModalOpen( false );
	};

	const onSubmit = () => {
		wcpayTracks.recordEvent( wcpayTracks.events.DISPUTE_ACCEPT_CLICK );
		setModalOpen( false );
		doAccept();
	};

	return (
		<Flex justify="start">
			<Button
				variant="primary"
				disabled={ isLoading }
				onClick={ () => {
					wcpayTracks.recordEvent(
						wcpayTracks.events.DISPUTE_CHALLENGE_CLICK
					);
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
					wcpayTracks.recordEvent(
						wcpayTracks.events.DISPUTE_ACCEPT_MODAL_VIEW
					);
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
						<FlexItem className="transaction-details-dispute-accept-modal__icon">
							<Icon icon={ backup } size={ 24 } />
						</FlexItem>
						<FlexItem>
							{ createInterpolateElement(
								sprintf(
									/* translators: %s: dispute fee, <em>: emphasis HTML element. */
									__(
										'Accepting the dispute marks it as <em>Lost</em>. The disputed amount will be returned to the cardholder, with a %s dispute fee deducted from your account',
										'woocommerce-payments'
									),
									disputeFee &&
										formatCurrency(
											disputeFee.fee,
											disputeFee.currency
										)
								),
								{
									em: <em />,
								}
							) }
						</FlexItem>
					</Flex>
					<Flex justify="start">
						<FlexItem className="transaction-details-dispute-accept-modal__icon">
							<Icon icon={ lock } size={ 24 } />
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
