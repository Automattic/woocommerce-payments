/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';

import './../../style.scss';
import FraudProtectionRuleDescription from '../rule-description';
import interpolateComponents from 'interpolate-components';
import { Link } from '@woocommerce/components';
import FraudProtectionRuleToggle from '../rule-toggle';

const InternationalIPAddressRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'International IP Address', 'woocommerce-payments' ) }
		description={ interpolateComponents( {
			mixedString: __(
				'This filter screens for IP addresses outside of your ' +
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
				key={ 'international_ip_address' }
				label={ __(
					'Screen transactions for international IP addresses',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			></FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'You should be especially wary when a customer has an international IP address but uses domestic billing and ' +
						'shipping information. Fraudsters often pretend to live in one location, but live and shop from another.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export default InternationalIPAddressRuleCard;
