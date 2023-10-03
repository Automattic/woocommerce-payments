/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import moment from 'moment';
import { __, sprintf } from '@wordpress/i18n';
import { backup, edit, lock, arrowRight } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';
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
import type { ChargeBillingDetails } from 'wcpay/types/charges';
import wcpayTracks from 'tracks';
import { useDisputeAccept } from 'wcpay/data';
import { getDisputeFeeFormatted, isInquiry } from 'wcpay/disputes/utils';
import { getAdminUrl } from 'wcpay/utils';
import DisputeNotice from './dispute-notice';
import IssuerEvidenceList from './evidence-list';
import DisputeSummaryRow from './dispute-summary-row';
import DisputeSteps from './dispute-steps';
import InlineNotice from 'components/inline-notice';
import './style.scss';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
	orderUrl: string | undefined;
}

const DisputeAwaitingResponseDetails: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	orderUrl,
} ) => {
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;

	// This is a temporary restriction and can be removed once steps and actions for inquiries are implemented.
	const showDisputeSteps = ! isInquiry( dispute );

	const onModalClose = () => {
		setModalOpen( false );
	};

	return (
		<div className="transaction-details-dispute-details-wrapper">
			<Card>
				<CardBody className="transaction-details-dispute-details-body">
					<DisputeNotice
						dispute={ dispute }
						isUrgent={ countdownDays <= 2 }
					/>
					{ hasStagedEvidence && (
						<InlineNotice icon={ edit } isDismissible={ false }>
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
					{ showDisputeSteps && (
						<DisputeSteps
							dispute={ dispute }
							customer={ customer }
							chargeCreated={ chargeCreated }
							daysRemaining={ countdownDays }
						/>
					) }
					<IssuerEvidenceList
						issuerEvidence={ dispute.issuer_evidence }
					/>

					<div className="transaction-details-dispute-details-body__actions">
						{ isInquiry( dispute ) ? (
							/* Inquiry Actions */
							<>
								<Link
									href={ getAdminUrl( {
										page: 'wc-admin',
										path: '/payments/disputes/challenge',
										id: dispute.id,
									} ) }
								>
									<Button
										variant="primary"
										disabled={ isLoading }
										onClick={ () => {
											wcpayTracks.recordEvent(
												wcpayTracks.events
													.DISPUTE_CHALLENGE_CLICK,
												{
													dispute_status:
														dispute.status,
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
													'Submit evidence',
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
												.DISPUTE_INQUIRY_REFUND_MODAL_VIEW,
											{
												dispute_status: dispute.status,
											}
										);
										setModalOpen( true );
									} }
								>
									{ __(
										'Issue refund',
										'woocommerce-payments'
									) }
								</Button>

								{ /** Accept dispute modal */ }
								{ isModalOpen && (
									<Modal
										title={ __(
											'Issue a refund?',
											'woocommerce-payments'
										) }
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
												<Icon
													icon={ backup }
													size={ 24 }
												/>
											</FlexItem>
											<FlexItem>
												{ __(
													'Issuing a refund will close the inquiry, returning the amount in question back to the cardholder. No additional fees apply.',
													'woocommerce-payments'
												) }
											</FlexItem>
										</Flex>

										<Flex justify="start">
											<FlexItem className="transaction-details-dispute-accept-modal__icon">
												<Icon
													icon={ arrowRight }
													size={ 24 }
												/>
											</FlexItem>
											<FlexItem>
												{ __(
													'You will be taken to the order, where you must complete the refund process manually.',
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
												onClick={ () => {
													wcpayTracks.recordEvent(
														wcpayTracks.events
															.DISPUTE_INQUIRY_REFUND_CLICK,
														{
															dispute_status:
																dispute.status,
														}
													);
													setModalOpen( false );

													if ( orderUrl ) {
														window.location.href = orderUrl;
													} else {
														createErrorNotice(
															__(
																'Unable to view order. Order not found.',
																'woocommerce-payments'
															)
														);
													}
												} }
											>
												{ __(
													'View order to issue refund',
													'woocommerce-payments'
												) }
											</Button>
										</Flex>
									</Modal>
								) }
							</>
						) : (
							/* Dispute Actions */
							<>
								<Link
									href={
										// Prevent the user navigating to the challenge screen if the accept request is in progress.
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
													dispute_status:
														dispute.status,
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

								{ /** Accept dispute modal */ }
								{ isModalOpen && (
									<Modal
										title={ __(
											'Accept the dispute?',
											'woocommerce-payments'
										) }
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
												<Icon
													icon={ backup }
													size={ 24 }
												/>
											</FlexItem>
											<FlexItem>
												{ createInterpolateElement(
													sprintf(
														/* translators: %s: dispute fee, <em>: emphasis HTML element. */
														__(
															'Accepting the dispute marks it as <em>Lost</em>. The disputed amount will be returned to the cardholder, with a %s dispute fee deducted from your account.',
															'woocommerce-payments'
														),
														getDisputeFeeFormatted(
															dispute,
															true
														) ?? '-'
													),
													{
														em: <em />,
													}
												) }
											</FlexItem>
										</Flex>

										<Flex justify="start">
											<FlexItem className="transaction-details-dispute-accept-modal__icon">
												<Icon
													icon={ lock }
													size={ 24 }
												/>
											</FlexItem>
											<FlexItem>
												{ __(
													'This action is final and cannot be undone.',
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
												onClick={ () => {
													wcpayTracks.recordEvent(
														wcpayTracks.events
															.DISPUTE_ACCEPT_CLICK,
														{
															dispute_status:
																dispute.status,
														}
													);
													setModalOpen( false );

													doAccept();
												} }
											>
												{ __(
													'Accept dispute',
													'woocommerce-payments'
												) }
											</Button>
										</Flex>
									</Modal>
								) }
							</>
						) }
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeAwaitingResponseDetails;
