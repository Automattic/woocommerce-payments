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
import FraudProtectionRuleToggle from '../rule-toggle';
import { getAdminUrl } from 'wcpay/utils';
import { isSellingToAvsSupportedLocations } from '../utils';

const AVSMismatchRuleCard: React.FC = () => {
	const isSellingToSupportedLocations = isSellingToAvsSupportedLocations();

	return (
		<FraudProtectionRuleCard
			title={ __( 'AVS Mismatch', 'woocommerce-payments' ) }
			id="avs-mismatch-card"
		>
			{ ! isSellingToSupportedLocations && (
				<FraudProtectionRuleCardNotice type="warning">
					{ interpolateComponents( {
						mixedString: __(
							'AVS checks are commonly supported only for cards issued in the United States, Canada, ' +
								'and the United Kingdom. None of your {{sellingLocationsLink}}selling locations{{/sellingLocationsLink}} ' +
								'support AVS, so this filter is unlikely to block any payments.',
							'woocommerce-payments'
						),
						components: {
							sellingLocationsLink: (
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
				</FraudProtectionRuleCardNotice>
			) }
			<FraudProtectionRuleToggle
				setting="avs_verification"
				label={ __(
					'Enable AVS Mismatch filter',
					'woocommerce-payments'
				) }
				description={ __(
					'This filter compares the post code submitted by the customer against the post code on ' +
						'file with the card issuer. The payment will be blocked if the two post codes do not match. ' +
						'AVS checks are not supported by every country or card issuer, so this filter will not block ' +
						'all payments with a mismatched post code.',
					'woocommerce-payments'
				) }
			/>

			<FraudProtectionRuleDescription>
				{ __(
					'Buyers who can provide correct post code on file with the issuing bank ' +
						'are more likely to be the actual account holder.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</FraudProtectionRuleCard>
	);
};

export default AVSMismatchRuleCard;
