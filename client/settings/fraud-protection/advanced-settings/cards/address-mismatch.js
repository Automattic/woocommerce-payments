/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudProtectionRuleCardNotice from '../rule-card-notice';

const AddressMismatchRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Address Mismatch', 'woocommerce-payments' ) }
		description={ __(
			'This filter screens for differences between the shipping information and the ' +
				'billing information (street, state, post code, and country).',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				setting={ 'address_mismatch' }
				label={ __(
					'Screen transactions for mismatched addresses',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			/>
			<FraudProtectionRuleDescription>
				{ __(
					'There are legitimate reasons for a billing/shipping mismatch with a customer purchase, ' +
						'but a mismatch could also indicate that someone is using a stolen identity to complete a purchase.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
			<FraudProtectionRuleCardNotice type="info">
				{ interpolateComponents( {
					mixedString: __(
						'This filter may modify the address input on your checkout. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
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
		</div>
	</FraudProtectionRuleCard>
);

export default AddressMismatchRuleCard;
