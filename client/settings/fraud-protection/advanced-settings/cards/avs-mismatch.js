/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import { ExternalLink } from '@wordpress/components';

const AVSMismatchRuleCard = () => {
	const declineOnAVSFailure =
		window.wcpaySettings?.accountStatus?.fraudProtection
			?.declineOnAVSFailure ?? true;
	return (
		<FraudProtectionRuleCard
			title={ __( 'AVS Mismatch', 'woocommerce-payments' ) }
			description={ __(
				'This filter compares the street number and the post code submitted by the customer against the data on ' +
					'file with the card issuer.',
				'woocommerce-payments'
			) }
		>
			<FraudProtectionRuleCardNotice type="warning">
				{ declineOnAVSFailure
					? interpolateComponents( {
							mixedString: __(
								'For security, this filter is enabled and cannot be modified. Payments failing address ' +
									'verification will be blocked. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							components: {
								learnMoreLink: (
									// eslint-disable-next-line max-len
									<ExternalLink href="https://woocommerce.com/document/woocommerce-payments/fraud-and-disputes/fraud-protection/#advanced-configuration" />
								),
							},
					  } )
					: __(
							'This filter is disabled, and can not be modified.',
							'woocommerce-payments'
					  ) }
			</FraudProtectionRuleCardNotice>
			<FraudProtectionRuleDescription>
				{ __(
					'Buyers who can provide the street number and post code on file with the issuing bank ' +
						'are more likely to be the actual account holder. AVS matches, however, are not a guarantee.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</FraudProtectionRuleCard>
	);
};

export default AVSMismatchRuleCard;
