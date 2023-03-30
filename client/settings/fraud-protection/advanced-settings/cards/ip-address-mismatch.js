/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';

const IPAddressMismatchRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'IP Address Mismatch', 'woocommerce-payments' ) }
		description={ __(
			'This filter screens for customer IP (Internet Protocol) addresses that are in a different ' +
				'country than the customer indicated in their billing address.',
			'woocommerce-payments'
		) }
	>
		<FraudProtectionRuleToggle
			setting={ 'ip_address_mismatch' }
			label={ __(
				"Screen transactions where the IP country and billing country don't match",
				'woocommerce-payments'
			) }
			helpText={ __(
				'When enabled, the payment method will not be charged until you review and approve the transaction'
			) }
		></FraudProtectionRuleToggle>
		<FraudProtectionRuleDescription>
			{ __(
				'Fraudulent transactions often use fake address to place orders. If the IP address seems to be in ' +
					'one country, but the billing address is in another, that could signal potential fraud.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleDescription>
	</FraudProtectionRuleCard>
);

export default IPAddressMismatchRuleCard;
