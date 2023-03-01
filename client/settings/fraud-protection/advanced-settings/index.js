/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import './../style.scss';
import SettingsLayout from 'wcpay/settings/settings-layout';
import AVSMismatchRuleCard from './cards/avs-mismatch';
import CVCVerificationRuleCard from './cards/cvc-verification';
import InternationalIPAddressRuleCard from './cards/international-ip-address';
import InternationalBillingAddressRuleCard from './cards/international-billing-address';
import AddressMismatchRuleCard from './cards/address-mismatch';
import OrderVelocityRuleCard from './cards/order-velocity';
import PurchasePriceThresholdRuleCard from './cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard from './cards/order-items-threshold';

const Breadcrumb = () => (
	<h2 className="fraud-protection-header-breadcrumb">
		<Link href="">
			{ __( 'WooCommerce Payments', 'woocommerce-payments' ) }
		</Link>
		&nbsp;&gt;&nbsp;
		{ __( 'Advanced fraud protection', 'woocommerce-payments' ) }
	</h2>
);

const FraudProtectionAdvancedSettingsPage = () => {
	return (
		<SettingsLayout displayBanner={ false }>
			<Breadcrumb />
			<AVSMismatchRuleCard />
			<CVCVerificationRuleCard />
			<InternationalIPAddressRuleCard />
			<InternationalBillingAddressRuleCard />
			<AddressMismatchRuleCard />
			<OrderVelocityRuleCard />
			<PurchasePriceThresholdRuleCard />
			<OrderItemsThresholdRuleCard />
		</SettingsLayout>
	);
};

export default FraudProtectionAdvancedSettingsPage;
