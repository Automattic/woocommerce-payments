/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import moment from 'moment';
import { __, sprintf } from '@wordpress/i18n';
import { backup, edit, lock } from '@wordpress/icons';
import { createInterpolateElement } from '@wordpress/element';
import { Link } from '@woocommerce/components';
import {
	Button,
	Card,
	CardBody,
	Flex,
	FlexItem,
	Icon,
	Modal,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import wcpayTracks from 'tracks';
import { useDisputeAccept } from 'wcpay/data';
import {
	getDisputeFee,
	isAwaitingResponse,
	isInquiry,
} from 'wcpay/disputes/utils';
import { getAdminUrl } from 'wcpay/utils';
import { formatCurrency } from 'wcpay/utils/currency';
import DisputeNotice from './dispute-notice';
import IssuerEvidenceList from './evidence-list';
import DisputeSummaryRow from './dispute-summary-row';
import InlineNotice from 'components/inline-notice';
import './style.scss';

interface Props {
	dispute: Dispute;
}

const DisputeAwaitingResponseDetails: React.FC< Props > = ( { dispute } ) => {
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );

	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const disputeFee = getDisputeFee( dispute );

	const onModalClose = () => {
		setModalOpen( false );
	};

	const onAccept = () => {
		wcpayTracks.recordEvent( wcpayTracks.events.DISPUTE_ACCEPT_CLICK, {
			dispute_status: dispute.status,
		} );
		setModalOpen( false );
		doAccept();
	};

	return (
		<div className="transaction-details-dispute-details-wrapper">
			<Card>
				<CardBody className="transaction-details-dispute-details-body">
					{ isAwaitingResponse( dispute.status ) &&
						countdownDays >= 0 && (
							<>
								<DisputeNotice
									dispute={ dispute }
									urgent={ countdownDays <= 2 }
								/>
								{ hasStagedEvidence && (
									<InlineNotice
										icon={ edit }
										isDismissible={ false }
									>
										{ __(
											`You initiated a challenge to this dispute. Click 'Continue with challenge' to proceed with your draft response.`,
											'woocommerce-payments'
										) }
									</InlineNotice>
								) }
								<DisputeSummaryRow
									dispute={ dispute }
									daysRemaining={ countdownDays }
								/>
								<IssuerEvidenceList
									issuerEvidence={ dispute.issuer_evidence }
								/>
							</>
						) }

					{ /* Dispute Actions */ }
					{ ! isInquiry( dispute ) && (
						<Flex justify="start">
							<Link
								href={
									isLoading
										? ''
										: getAdminUrl( {
												page: 'wc-admin',
												path:
													'/payments/disputes/challenge',
												id: dispute.id,
										  } )
								}
							>
								<Button
									variant="primary"
									disabled={ isLoading }
									onClick={ () => {
										wcpayTracks.recordEvent(
											wcpayTracks.events
												.DISPUTE_CHALLENGE_CLICK,
											{
												dispute_status: dispute.status,
											}
										);
									} }
								>
									{ hasStagedEvidence
										? __(
												'Continue with challenge',
												'woocommerce-payments'
										  )
										: __(
												'Challenge dispute',
												'woocommerce-payments'
										  ) }
								</Button>
							</Link>

							<Button
								variant="tertiary"
								disabled={ isLoading }
								onClick={ () => {
									wcpayTracks.recordEvent(
										wcpayTracks.events
											.DISPUTE_ACCEPT_MODAL_VIEW,
										{
											dispute_status: dispute.status,
										}
									);
									setModalOpen( true );
								} }
							>
								{ __(
									'Accept dispute',
									'woocommerce-payments'
								) }
							</Button>

							{ isModalOpen && (
								<Modal
									title="Accept the dispute?"
									onRequestClose={ onModalClose }
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
														'Accepting the dispute marks it as <em>Lost</em>. The disputed amount will be returned to the cardholder, with a %s dispute fee deducted from your account.',
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
										<Button
											variant="tertiary"
											onClick={ onModalClose }
										>
											{ __(
												'Cancel',
												'woocommerce-payments'
											) }
										</Button>
										<Button
											variant="primary"
											onClick={ onAccept }
										>
											{ __(
												'Accept dispute',
												'woocommerce-payments'
											) }
										</Button>
									</Flex>
								</Modal>
							) }
						</Flex>
					) }
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeAwaitingResponseDetails;
