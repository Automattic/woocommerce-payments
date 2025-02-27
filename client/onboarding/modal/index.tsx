/**
 * External dependencies
 */
import React from 'react';
import { Fill } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';

const OnboardingModal = () => {
	return (
		<Fill name="__EXPERIMENTAL__WcAdminSettingsPaymentsSlots">
			<p>Onboarding Modal</p>
		</Fill>
	);
};

registerPlugin( 'woopayments-onboarding-modal', {
	render: OnboardingModal,
	scope: 'woocommerce-settings-payment-woopayments',
} );
