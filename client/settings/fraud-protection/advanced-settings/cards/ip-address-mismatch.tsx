/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';

const IPAddressMismatchRuleCard: React.FC = () => (
	<FraudProtectionRuleCard
		title={ __( 'IP Address Mismatch', 'woocommerce-payments' ) }
		id="ip-address-mismatch"
	>
		<FraudProtectionRuleToggle
			setting="ip_address_mismatch"
			label={ __(
				'Enable IP Address Mismatch filter',
				'woocommerce-payments'
			) }
			description={ interpolateComponents( {
				mixedString: __(
					"This filter screens for customer's {{ipAddressLink}}IP address{{/ipAddressLink}} to see if it is in a different " +
						'country than indicated in their billing address. When enabled the payment will be blocked.',
					'woocommerce-payments'
				),
				components: {
					ipAddressLink: (
						<ExternalLink href="https://simple.wikipedia.org/wiki/IP_address" />
					),
				},
			} ) }
		/>

		<FraudProtectionRuleDescription>
			{ __(
				'Fraudulent transactions often use fake addresses to place orders. If the IP address seems to be in ' +
					'one country, but the billing address is in another, that could signal potential fraud.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleDescription>
	</FraudProtectionRuleCard>
);

export default IPAddressMismatchRuleCard;
