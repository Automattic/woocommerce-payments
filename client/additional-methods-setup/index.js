/** @format **/
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import MethodSelector from './methods-selector';

addFilter(
	'woocommerce_admin_onboarding_task_list',
	'woocommerce-payments',
	( tasks ) => {
		return [
			...tasks,
			{
				key: 'woocommerce-payments--additional-payment-methods',
				title: __(
					'Set up additional payment methods',
					'woocommerce-payments'
				),
				// it might be worth exploring how to use Suspense
				// to lazily load the JS that might not be necessary if the action is not taken.
				// TODO: error boundary to prevent the whole page to be blank
				container: <MethodSelector />,
				// please note: marking an item as "dismissed" does not mean it's "completed" - they are considered 2 different things
				completed:
					'yes' ===
					window.wcpayAdditionalMethodsSetup.isSetupCompleted,
				visible: true,
				additionalInfo: __(
					'Offer your customers preferred payment methods with WooCommerce Payments',
					'woocommerce-payments'
				),
				isDismissable: true,
			},
		];
	}
);
