/** @format */

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, ExternalLink } from '@wordpress/components';
import { getAdminUrl } from 'wcpay/utils';
import InlineNotice from 'components/inline-notice';
import DisputeEvidenceSubmittedIllustration from 'assets/images/dispute-evidence-submitted.svg?asset';

interface ConfirmationScreenProps {
	disputeId: string;
	bankName: string | null;
	isVisaComplianceDispute: boolean;
}

const ConfirmationScreen: React.FC< ConfirmationScreenProps > = ( {
	disputeId,
	bankName,
	isVisaComplianceDispute,
} ) => {
	useEffect( () => {
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}, [] );

	return (
		<div className="wcpay-dispute-evidence-confirmation">
			<div className="wcpay-dispute-evidence-confirmation__wrapper">
				<div className="wcpay-dispute-evidence-confirmation__content">
					{ /* Success illustration */ }
					<div className="wcpay-dispute-evidence-confirmation__illustration">
						<img
							src={ DisputeEvidenceSubmittedIllustration }
							alt={ __(
								'Evidence submitted successfully',
								'woocommerce-payments'
							) }
							className="wcpay-dispute-evidence-confirmation__illustration-image"
						/>
					</div>

					{ /* Main success message */ }
					<h2 className="wcpay-dispute-evidence-confirmation__title">
						{ __(
							'Thanks for sharing your response!',
							'woocommerce-payments'
						) }
					</h2>

					<p className="wcpay-dispute-evidence-confirmation__subtitle">
						{ isVisaComplianceDispute
							? __(
									'Your evidence has been sent to Visa for review.',
									'woocommerce-payments'
							  )
							: __(
									"Your evidence has been sent to the cardholder's bank for review.",
									'woocommerce-payments'
							  ) }
					</p>

					{ /* What's next section */ }
					<div className="wcpay-dispute-evidence-confirmation__next-steps">
						<h3>
							{ __( 'What’s next?', 'woocommerce-payments' ) }
						</h3>
						<ul>
							<li>
								{ isVisaComplianceDispute
									? __(
											'Visa will review your response. Please be patient — this usually takes a few weeks, but in some cases it can take up to 3 months.',
											'woocommerce-payments'
									  )
									: __(
											'The cardholder’s bank will review your response. Please be patient — this usually takes a few weeks, but in some cases it can take up to 3 months.',
											'woocommerce-payments'
									  ) }
							</li>
							<li>
								{ createInterpolateElement(
									__(
										"You'll be informed of any updates via email, or you can check the status of your case at any time in your <disputesPageLink>Disputes area</disputesPageLink>.",
										'woocommerce-payments'
									),
									{
										disputesPageLink: (
											<a
												href={ getAdminUrl( {
													page: 'wc-admin',
													path: '/payments/disputes',
												} ) }
											>
												{ __(
													'Disputes page',
													'woocommerce-payments'
												) }
											</a>
										),
									}
								) }
							</li>
							<li>
								{ createInterpolateElement(
									__(
										'Want to know more about how disputes work? <learnMoreLink>Check out our resources</learnMoreLink>',
										'woocommerce-payments'
									),
									{
										learnMoreLink: (
											<ExternalLink href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#how-they-work">
												{ __(
													'Learn more about disputes',
													'woocommerce-payments'
												) }
											</ExternalLink>
										),
									}
								) }
							</li>
						</ul>
					</div>

					{ /* Important notice */ }
					<InlineNotice
						icon
						isDismissible={ false }
						status="info"
						className="wcpay-dispute-evidence-confirmation__notice"
					>
						{ isVisaComplianceDispute
							? createInterpolateElement(
									__(
										'<strong>The outcome of this dispute will be determined by Visa.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.',
										'woocommerce-payments'
									),
									{
										strong: <strong />,
									}
							  )
							: createInterpolateElement(
									bankName
										? sprintf(
												__(
													'<strong>The outcome of this dispute will be determined by %1$s.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.',
													'woocommerce-payments'
												),
												bankName
										  )
										: __(
												"<strong>The outcome of this dispute will be determined by the cardholder's bank.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.",
												'woocommerce-payments'
										  ),
									{
										strong: <strong />,
									}
							  ) }
					</InlineNotice>

					{ /* Action buttons */ }
					<div className="wcpay-dispute-evidence-confirmation__actions">
						<Button
							variant="secondary"
							onClick={ () => {
								window.location.href = getAdminUrl( {
									page: 'wc-admin',
									path: '/payments/disputes',
								} );
							} }
						>
							{ __(
								'Return to disputes',
								'woocommerce-payments'
							) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								window.location.href = getAdminUrl( {
									page: 'wc-admin',
									path: '/payments/disputes/challenge',
									id: disputeId,
								} );
							} }
						>
							{ __(
								'View submitted dispute',
								'woocommerce-payments'
							) }
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default ConfirmationScreen;
