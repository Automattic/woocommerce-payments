/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';

const InternationalBillingAddressRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'International Billing Address', 'woocommerce-payments' ) }
		description={ interpolateComponents( {
			mixedString: __(
				"This filter screens the customer's billing information for addresses outside of your " +
					'{{supportedCountriesLink}}supported countries{{/supportedCountriesLink}}.',
				'woocommerce-payments'
			),
			components: {
				supportedCountriesLink: <Link href="#" />,
			},
		} ) }
	>
		<div>
			<FraudProtectionRuleToggle
				setting={ 'international_billing_address' }
				label={ __(
					'Screen transactions for international billing addresses',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			></FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'Due to the difficulty of authenticating foreign citizens and cross-border legal ' +
						'enforcement against fraudulent activities, you should exercise caution when ' +
						'accepting orders from customers in foreign countries who are using non-domestic addresses.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export default InternationalBillingAddressRuleCard;
