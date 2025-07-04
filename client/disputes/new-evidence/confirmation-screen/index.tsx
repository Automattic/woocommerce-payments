/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, ExternalLink } from 'wcpay/components/wp-components-wrapped';
import { getAdminUrl } from 'wcpay/utils';
import InlineNotice from 'components/inline-notice';
import DisputeEvidenceSubmittedIllustration from 'assets/images/dispute-evidence-submitted.svg?asset';

import './style.scss';

interface ConfirmationScreenProps {
	disputeId: string;
	bankName: string | null;
}

const ConfirmationScreen: React.FC< ConfirmationScreenProps > = ( {
	disputeId,
	bankName,
} ) => {
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
							"You did it! The form is in the bank's hands",
							'woocommerce-payments'
						) }
					</h2>

					<p className="wcpay-dispute-evidence-confirmation__subtitle">
						{ __(
							'Thank you for taking the time and submitting the dispute.',
							'woocommerce-payments'
						) }
					</p>

					{ /* What's next section */ }
					<div className="wcpay-dispute-evidence-confirmation__next-steps">
						<h3>
							{ __( "What's next?", 'woocommerce-payments' ) }
						</h3>
						<ul>
							<li>
								{ __(
									"It might take a few days for the cardholder's bank to review your dispute",
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ createInterpolateElement(
									__(
										"Once reviewed, you'll receive an email or you can check back regularly on <disputesPageLink>Disputes page</disputesPageLink>",
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
										'Still unsure or need more information? <learnMoreLink>Learn more about disputes</learnMoreLink>',
										'woocommerce-payments'
									),
									{
										learnMoreLink: (
											<ExternalLink href="https://woocommerce.com/document/payments/disputes/">
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
						className="dispute-steps__notice-content"
					>
						{ createInterpolateElement(
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
					<div className="wcpay-dispute-evidence-new__button-row">
						<Button
							variant="secondary"
							onClick={ () => {
								window.location.href = getAdminUrl( {
									page: 'wc-admin',
									path: '/payments/disputes',
									filter: 'awaiting_response',
								} );
							} }
						>
							{ __(
								'Return to disputes',
								'woocommerce-payments'
							) }
						</Button>
						<div className="wcpay-dispute-evidence-new__button-group-right">
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
		</div>
	);
};

export default ConfirmationScreen;
