/** @format **/

/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
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
import { DisputeSteps, InquirySteps } from './dispute-steps';
import InlineNotice from 'components/inline-notice';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';
import './style.scss';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
	orderUrl: string | undefined;
}

/**
 * The lines of text to display in the modal to confirm acceptance / refunding of the dispute / inquiry.
 */
interface ModalLineItem {
	icon: JSX.Element;
	description: string | JSX.Element;
}

interface AcceptDisputeProps {
	/**
	 * The label for the button that opens the modal.
	 */
	acceptButtonLabel: string;
	/**
	 * The event to track when the button that opens the modal is clicked.
	 */
	acceptButtonTracksEvent: string;
	/**
	 * The title of the modal.
	 */
	modalTitle: string;
	/**
	 * The lines of text to display in the modal.
	 */
	modalLines: ModalLineItem[];
	/**
	 * The label for the primary button in the modal to Accept / Refund the dispute / inquiry.
	 */
	modalButtonLabel: string;
	/**
	 * The event to track when the primary button in the modal is clicked.
	 */
	modalButtonTracksEvent: string;
}

/**
 * Disputes and Inquiries have different text for buttons and the modal.
 * They also have different icons and tracks events. This function returns the correct props.
 *
 * @param dispute
 */
function getAcceptDisputeProps( dispute: Dispute ): AcceptDisputeProps {
	if ( isInquiry( dispute ) ) {
		return {
			acceptButtonLabel: __( 'Issue refund', 'woocommerce-payments' ),
			acceptButtonTracksEvent:
				wcpayTracks.events.DISPUTE_INQUIRY_REFUND_MODAL_VIEW,
			modalTitle: __( 'Issue a refund?', 'woocommerce-payments' ),
			modalLines: [
				{
					icon: <Icon icon={ backup } size={ 24 } />,
					description: __(
						'Issuing a refund will close the inquiry, returning the amount in question back to the cardholder. No additional fees apply.',
						'woocommerce-payments'
					),
				},
				{
					icon: <Icon icon={ arrowRight } size={ 24 } />,
					description: __(
						'You will be taken to the order, where you must complete the refund process manually.',
						'woocommerce-payments'
					),
				},
			],
			modalButtonLabel: __(
				'View order to issue refund',
				'woocommerce-payments'
			),
			modalButtonTracksEvent:
				wcpayTracks.events.DISPUTE_INQUIRY_REFUND_CLICK,
		};
	}

	return {
		acceptButtonLabel: __( 'Accept dispute', 'woocommerce-payments' ),
		acceptButtonTracksEvent: wcpayTracks.events.DISPUTE_ACCEPT_MODAL_VIEW,
		modalTitle: __( 'Accept the dispute?', 'woocommerce-payments' ),
		modalLines: [
			{
				icon: <Icon icon={ backup } size={ 24 } />,
				description: createInterpolateElement(
					sprintf(
						/* translators: %s: dispute fee, <em>: emphasis HTML element. */
						__(
							'Accepting the dispute marks it as <em>Lost</em>. The disputed amount and the %s dispute fee will not be returned to you.',
							'woocommerce-payments'
						),
						getDisputeFeeFormatted( dispute, true ) ?? '-'
					),
					{
						em: <em />,
					}
				),
			},
			{
				icon: <Icon icon={ lock } size={ 24 } />,
				description: __(
					'This action is final and cannot be undone.',
					'woocommerce-payments'
				),
			},
		],
		modalButtonLabel: __( 'Accept dispute', 'woocommerce-payments' ),
		modalButtonTracksEvent: wcpayTracks.events.DISPUTE_ACCEPT_CLICK,
	};
}

const DisputeAwaitingResponseDetails: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	orderUrl,
} ) => {
	const { doAccept, isLoading } = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );

	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const {
		featureFlags: { isDisputeIssuerEvidenceEnabled },
	} = useContext( WCPaySettingsContext );

	const onModalClose = () => {
		setModalOpen( false );
	};

	const viewOrder = () => {
		if ( orderUrl ) {
			window.location.href = orderUrl;
			return;
		}

		createErrorNotice(
			__(
				'Unable to view order. Order not found.',
				'woocommerce-payments'
			)
		);
	};

	const disputeAcceptAction = getAcceptDisputeProps( dispute );

	const challengeButtonDefaultText = isInquiry( dispute )
		? __( 'Submit evidence', 'woocommerce-payments' )
		: __( 'Challenge dispute', 'woocommerce-payments' );

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

					<DisputeSummaryRow dispute={ dispute } />

					{ isInquiry( dispute ) ? (
						<InquirySteps
							dispute={ dispute }
							customer={ customer }
							chargeCreated={ chargeCreated }
						/>
					) : (
						<DisputeSteps
							dispute={ dispute }
							customer={ customer }
							chargeCreated={ chargeCreated }
						/>
					) }

					{ isDisputeIssuerEvidenceEnabled && (
						<IssuerEvidenceList
							issuerEvidence={ dispute.issuer_evidence }
						/>
					) }

					{ /* Dispute Actions */ }
					{
						<div className="transaction-details-dispute-details-body__actions">
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
												.DISPUTE_CHALLENGE_CLICKED,
											{
												dispute_status: dispute.status,
												on_page: 'transaction_details',
											}
										);
									} }
								>
									{ hasStagedEvidence
										? __(
												'Continue with challenge',
												'woocommerce-payments'
										  )
										: challengeButtonDefaultText }
								</Button>
							</Link>

							<Button
								variant="tertiary"
								disabled={ isLoading }
								onClick={ () => {
									wcpayTracks.recordEvent(
										disputeAcceptAction.acceptButtonTracksEvent,
										{
											dispute_status: dispute.status,
											on_page: 'transaction_details',
										}
									);
									setModalOpen( true );
								} }
							>
								{ disputeAcceptAction.acceptButtonLabel }
							</Button>

							{ /** Accept dispute modal */ }
							{ isModalOpen && (
								<Modal
									title={ disputeAcceptAction.modalTitle }
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

									{ disputeAcceptAction.modalLines.map(
										( line, key ) => (
											<Flex justify="start" key={ key }>
												<FlexItem className="transaction-details-dispute-accept-modal__icon">
													{ line.icon }
												</FlexItem>
												<FlexItem>
													{ line.description }
												</FlexItem>
											</Flex>
										)
									) }

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
													disputeAcceptAction.modalButtonTracksEvent,
													{
														dispute_status:
															dispute.status,
														on_page:
															'transaction_details',
													}
												);
												setModalOpen( false );
												/**
												 * Handle the primary modal action.
												 * If it's an inquiry, redirect to the order page; otherwise, continue with the default dispute acceptance.
												 */
												if ( isInquiry( dispute ) ) {
													viewOrder();
												} else {
													doAccept();
												}
											} }
										>
											{
												disputeAcceptAction.modalButtonLabel
											}
										</Button>
									</Flex>
								</Modal>
							) }
						</div>
					}
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeAwaitingResponseDetails;
