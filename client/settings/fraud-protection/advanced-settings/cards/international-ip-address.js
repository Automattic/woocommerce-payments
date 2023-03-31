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
import AllowedCountriesNotice from '../allow-countries-notice';
import { getAdminUrl } from 'wcpay/utils';

const InternationalIPAddressRuleCard = () => {
	return (
		<FraudProtectionRuleCard
			title={ __( 'International IP Address', 'woocommerce-payments' ) }
			description={ interpolateComponents( {
				mixedString: __(
					'This filter screens for {{ipAddressLink}}IP addresses{{/ipAddressLink}} outside of your ' +
						'{{supportedCountriesLink}}supported countries{{/supportedCountriesLink}}.',
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
					supportedCountriesLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							href={ getAdminUrl( {
								page: 'wc-settings',
								tab: 'general',
							} ) }
						/>
					),
				},
			} ) }
			id="international-ip-address-card"
		>
			<AllowedCountriesNotice setting={ 'international_ip_address' } />
			<FraudProtectionRuleToggle
				setting={ 'international_ip_address' }
				label={ __(
					'Screen transactions for international IP addresses',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			/>
			<FraudProtectionRuleDescription>
				{ __(
					'You should be especially wary when a customer has an international IP address but uses domestic billing and ' +
						'shipping information. Fraudsters often pretend to live in one location, but live and shop from another.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</FraudProtectionRuleCard>
	);
};

export default InternationalIPAddressRuleCard;
