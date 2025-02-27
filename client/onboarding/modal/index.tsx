/**
 * External dependencies
 */
import React from 'react';
import { Fill } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { OnboardingModal } from './modal';

const ModalFill = () => {
	return (
		<Fill name="__EXPERIMENTAL__WcAdminSettingsPaymentsSlots">
			<OnboardingModal
				isOpen={ true }
				onClose={ () => {
					// TODO: Implement later
				} }
			/>
		</Fill>
	);
};

registerPlugin( 'woopayments-onboarding-modal', {
	render: ModalFill,
	scope: 'woocommerce-settings-payment-woopayments',
} );
