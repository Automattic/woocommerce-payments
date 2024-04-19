/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';

const CVCVerificationRuleCard: React.FC = () => {
	const declineOnCVCFailure =
		wcpaySettings?.accountStatus?.fraudProtection?.declineOnCVCFailure ??
		true;
	return (
		<FraudProtectionRuleCard
			title={ __( 'CVC Verification', 'woocommerce-payments' ) }
			description={ __(
				'This filter checks the security code submitted by the customer against the data on file with the card issuer.',
				'woocommerce-payments'
			) }
			id="cvc-verification-card"
		>
			<FraudProtectionRuleDescription>
				{ __(
					'Because the card security code appears only on the card and not on receipts or statements, the card security code ' +
						'provides some assurance that the physical card is in the possession of the buyer.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
			<FraudProtectionRuleCardNotice type="warning">
				{ declineOnCVCFailure
					? interpolateComponents( {
							mixedString: __(
								'For security, this filter is enabled and cannot be modified. Payments failing CVC verification ' +
									'will be blocked. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							components: {
								learnMoreLink: (
									<Link
										target="_blank"
										type="external"
										// eslint-disable-next-line max-len
										href="https://woocommerce.com/document/woopayments/fraud-and-disputes/fraud-protection/#advanced-configuration"
									/>
								),
							},
					  } )
					: __(
							'This filter is disabled, and can not be modified.',
							'woocommerce-payments'
					  ) }
			</FraudProtectionRuleCardNotice>
		</FraudProtectionRuleCard>
	);
};

export default CVCVerificationRuleCard;
