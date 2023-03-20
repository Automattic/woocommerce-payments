/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';

const AVSMismatchRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'AVS Mismatch', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the street number and the post code submitted by the customer against the data on ' +
				'file with the card issuer.',
			'woocommerce-payments'
		) }
	>
		<FraudProtectionRuleDescription>
			{ __(
				'Buyers who can provide the street number and post code on file with the issuing bank ' +
					'are more likely to be the actual account holder. AVS matches, however, are not a guarantee.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleDescription>
		<FraudProtectionRuleCardNotice type="warning">
			{ interpolateComponents( {
				mixedString: __(
					'Payments failing address verification will be blocked. For security, this filter ' +
						'cannot be modified. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<Link href="https://woocommerce.com/document/payments/additional-payment-methods/#available-methods" />
					),
				},
			} ) }
		</FraudProtectionRuleCardNotice>
	</FraudProtectionRuleCard>
);

export default AVSMismatchRuleCard;
