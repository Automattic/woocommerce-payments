/** @format **/

/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { backup, edit, lock, arrowRight } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';
import { createInterpolateElement } from '@wordpress/element';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Flex,
	FlexItem,
	HorizontalRule,
	Icon,
	Modal,
} from '@wordpress/components';
import type { Dispute } from 'wcpay/types/disputes';
import type { ChargeBillingDetails } from 'wcpay/types/charges';
import { recordEvent } from 'tracks';
import { useDisputeAccept } from 'wcpay/data';
import { getDisputeFeeFormatted, isInquiry } from 'wcpay/disputes/utils';
import { getAdminUrl } from 'wcpay/utils';
import DisputeNotice from './dispute-notice';
import IssuerEvidenceList from './evidence-list';
import DisputeSummaryRow from './dispute-summary-row';
import {
	DisputeSteps,
	InquirySteps,
	NotDefendableInquirySteps,
	NonCompliantDisputeSteps,
} from './dispute-steps';
import InlineNotice from 'components/inline-notice';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';
import './style.scss';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
	orderUrl: string | undefined;
	paymentMethod: string | null;
	bankName: string | null;
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
 */
function getAcceptDisputeProps( {
	dispute,
	isDisputeAcceptRequestPending,
}: {
	dispute: Dispute;
	isDisputeAcceptRequestPending: boolean;
} ): AcceptDisputeProps {
	if ( isInquiry( dispute.status ) ) {
		return {
			acceptButtonLabel: __( 'Issue refund', 'woocommerce-payments' ),
			acceptButtonTracksEvent: 'wcpay_dispute_inquiry_refund_modal_view',
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
			modalButtonTracksEvent: 'wcpay_dispute_inquiry_refund_click',
		};
	}

	return {
		acceptButtonLabel: __( 'Accept dispute', 'woocommerce-payments' ),
		acceptButtonTracksEvent: 'wcpay_dispute_accept_modal_view',
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
		modalButtonLabel: isDisputeAcceptRequestPending
			? __( 'Accepting…', 'woocommerce-payments' )
			: __( 'Accept dispute', 'woocommerce-payments' ),
		modalButtonTracksEvent: 'wcpay_dispute_accept_click',
	};
}

const DisputeAwaitingResponseDetails: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	orderUrl,
	paymentMethod,
	bankName,
} ) => {
	const {
		doAccept,
		isLoading: isDisputeAcceptRequestPending,
	} = useDisputeAccept( dispute );
	const [ isModalOpen, setModalOpen ] = useState( false );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	const [
		isVisaComplianceConditionAccepted,
		setVisaComplianceConditionAccepted,
	] = useState( hasStagedEvidence );
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const {
		featureFlags: { isDisputeIssuerEvidenceEnabled },
	} = useContext( WCPaySettingsContext );

	const isVisaComplianceDispute =
		dispute.reason === 'noncompliant' ||
		( dispute?.enhanced_eligibility_types || [] ).includes(
			'visa_compliance'
		);

	// Get the appropriate documentation URL based on dispute type
	const getLearnMoreDocsUrl = () => {
		if ( isInquiry( dispute.status ) ) {
			if ( paymentMethod === 'klarna' ) {
				return 'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#klarna-inquiries-returns';
			}
			return 'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#inquiries';
		}
		if ( isVisaComplianceDispute ) {
			return 'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#visa-compliance-disputes';
		}
		return 'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#responding';
	};

	// Get the appropriate help link text based on dispute type and payment method
	const getHelpLinkText = () => {
		if ( isInquiry( dispute.status ) ) {
			if ( paymentMethod === 'klarna' ) {
				return __(
					'Please see this document for more information',
					'woocommerce-payments'
				);
			}
			return __(
				'Learn more about payment inquiries',
				'woocommerce-payments'
			);
		}
		if ( isVisaComplianceDispute ) {
			return __(
				'Learn more about Visa compliance disputes',
				'woocommerce-payments'
			);
		}
		return __(
			'Learn more about responding to disputes',
			'woocommerce-payments'
		);
	};

	const handleModalClose = () => {
		// Don't allow the user to close the modal if the accept request is in progress.
		if ( isDisputeAcceptRequestPending ) {
			return;
		}
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

	const disputeAcceptAction = getAcceptDisputeProps( {
		dispute,
		isDisputeAcceptRequestPending,
	} );

	/**
	 * The following cases cannot be defended:
	 * - Klarna inquiries
	 * - Visa Compliance disputes (require confirmation of a specific fee)
	 */
	const isDefendable = ! (
		paymentMethod === 'klarna' && isInquiry( dispute.status )
	);

	const challengeButtonDefaultText = isInquiry( dispute.status )
		? __( 'Submit evidence', 'woocommerce-payments' )
		: __( 'Challenge dispute', 'woocommerce-payments' );

	const inquirySteps = isDefendable ? (
		<InquirySteps
			dispute={ dispute }
			customer={ customer }
			chargeCreated={ chargeCreated }
			bankName={ bankName }
		/>
	) : (
		<NotDefendableInquirySteps
			dispute={ dispute }
			customer={ customer }
			chargeCreated={ chargeCreated }
			bankName={ bankName }
		/>
	);

	const disputeSteps = isVisaComplianceDispute ? (
		<NonCompliantDisputeSteps />
	) : (
		<DisputeSteps
			dispute={ dispute }
			customer={ customer }
			chargeCreated={ chargeCreated }
			bankName={ bankName }
		/>
	);

	// we cannot nest ternary operators, so let's build the steps in a variable
	const steps = isInquiry( dispute.status ) ? inquirySteps : disputeSteps;

	return (
		<div className="transaction-details-dispute-details-wrapper">
			<HorizontalRule />
			<h2 className="transaction-details-dispute-details-title">
				{ __( 'Dispute details', 'woocommerce-payments' ) }
			</h2>
			<div className="transaction-details-dispute-details-body">
				{ /* No matter what the countdown days is, we should show the urgent the urgent notice */ }
				<DisputeNotice
					dispute={ dispute }
					isUrgent={ true }
					paymentMethod={ paymentMethod }
					bankName={ bankName }
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

				{ steps }

				{ isDisputeIssuerEvidenceEnabled && (
					<IssuerEvidenceList
						issuerEvidence={ dispute.issuer_evidence }
					/>
				) }

				{ /* Help link to documentation */ }
				<div className="transaction-details-dispute-details-body__help-link">
					<ExternalLink
						href={ getLearnMoreDocsUrl() }
						onClick={ () => {
							recordEvent( 'wcpay_dispute_help_link_clicked', {
								dispute_status: dispute.status,
								on_page: 'transaction_details',
							} );
						} }
					>
						{ getHelpLinkText() }
					</ExternalLink>
				</div>
				{ /* Checkbox for the Visa Compliance dispute */ }
				{ isVisaComplianceDispute && (
					<div className="transaction-details-dispute-details-body__visa-compliance-checkbox">
						<CheckboxControl
							onChange={ setVisaComplianceConditionAccepted }
							checked={ isVisaComplianceConditionAccepted }
							label={ __(
								'By checking this box, you acknowledge that challenging this Visa compliance dispute incurs a $500 USD network fee, which will be refunded if you win the dispute.',
								'woocommerce-payments'
							) }
							__nextHasNoMarginBottom
						/>
					</div>
				) }
				{ /* Dispute Actions */ }
				{
					<div className="transaction-details-dispute-details-body__actions">
						{ isDefendable && (
							<Link
								href={
									// Prevent the user navigating to the challenge screen if the accept request is in progress.
									isDisputeAcceptRequestPending
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
									data-testid="challenge-dispute-button"
									disabled={
										isDisputeAcceptRequestPending ||
										( isVisaComplianceDispute &&
											! isVisaComplianceConditionAccepted )
									}
									onClick={ () => {
										recordEvent(
											'wcpay_dispute_challenge_clicked',
											{
												dispute_status: dispute.status,
												on_page: 'transaction_details',
											}
										);
									} }
									__next40pxDefaultSize
								>
									{ hasStagedEvidence
										? __(
												'Continue with challenge',
												'woocommerce-payments'
										  )
										: challengeButtonDefaultText }
								</Button>
							</Link>
						) }

						<Button
							variant={ isDefendable ? 'tertiary' : 'primary' }
							disabled={ isDisputeAcceptRequestPending }
							data-testid="open-accept-dispute-modal-button"
							onClick={ () => {
								recordEvent(
									disputeAcceptAction.acceptButtonTracksEvent,
									{
										dispute_status: dispute.status,
										on_page: 'transaction_details',
									}
								);
								setModalOpen( true );
							} }
							__next40pxDefaultSize
						>
							{ disputeAcceptAction.acceptButtonLabel }
						</Button>

						{ /** Accept dispute modal */ }
						{ isModalOpen && (
							<Modal
								title={ disputeAcceptAction.modalTitle }
								onRequestClose={ handleModalClose }
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
										disabled={
											isDisputeAcceptRequestPending
										}
										onClick={ handleModalClose }
										__next40pxDefaultSize
									>
										{ __(
											'Cancel',
											'woocommerce-payments'
										) }
									</Button>
									<Button
										variant="primary"
										isBusy={ isDisputeAcceptRequestPending }
										disabled={
											isDisputeAcceptRequestPending
										}
										data-testid="accept-dispute-button"
										onClick={ () => {
											recordEvent(
												disputeAcceptAction.modalButtonTracksEvent,
												{
													dispute_status:
														dispute.status,
													on_page:
														'transaction_details',
												}
											);

											/**
											 * Handle the primary modal action.
											 * If it's an inquiry, redirect to the order page; otherwise, continue with the default dispute acceptance.
											 */
											if ( isInquiry( dispute.status ) ) {
												viewOrder();
											} else {
												doAccept();
											}
										} }
										__next40pxDefaultSize
									>
										{ disputeAcceptAction.modalButtonLabel }
									</Button>
								</Flex>
							</Modal>
						) }
					</div>
				}
			</div>
		</div>
	);
};

export default DisputeAwaitingResponseDetails;
