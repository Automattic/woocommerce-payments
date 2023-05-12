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
import FraudProtectionRuleToggle from '../rule-toggle';

const IPAddressMismatchRuleCard: React.FC = () => (
	<FraudProtectionRuleCard
		title={ __( 'IP Address Mismatch', 'woocommerce-payments' ) }
		description={ interpolateComponents( {
			mixedString: __(
				"This filter screens for customer's {{ipAddressLink}}IP address{{/ipAddressLink}} to see if it is in a different " +
					'country than indicated in their billing address.',
				'woocommerce-payments'
			),
			components: {
				ipAddressLink: (
					<Link
						target="_blank"
						type="external"
						href="https://simple.wikipedia.org/wiki/IP_address"
					/>
				),
			},
		} ) }
		id="ip-address-mismatch"
	>
		<FraudProtectionRuleToggle
			setting={ 'ip_address_mismatch' }
			label={ __(
				"Screen transactions where the IP country and billing country don't match",
				'woocommerce-payments'
			) }
		></FraudProtectionRuleToggle>
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
