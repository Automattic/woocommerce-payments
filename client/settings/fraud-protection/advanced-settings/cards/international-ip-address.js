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
import FraudProtectionRuleToggle from '../rule-toggle';
import AllowedCountriesNotice from '../allow-countries-notice';

const InternationalIPAddressRuleCard = () => {
	return (
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
			<FraudProtectionRuleToggle
				setting={ 'international_ip_address' }
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
			<AllowedCountriesNotice setting={ 'international_ip_address' } />
		</FraudProtectionRuleCard>
	);
};

export default InternationalIPAddressRuleCard;
