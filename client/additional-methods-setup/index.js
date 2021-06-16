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
import UpePreviewMethodSelector from './upe-preview-methods-selector';
import WcPayUpeContextProvider from '../settings/wcpay-upe-toggle/provider';

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
				// It might also be worth exploring adding an error boundary to prevent the whole page to be blank in case of error?
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

				// overwriting the default values, while the test is running
				// using object spread to override the attributes makes things a bit easier when it'll be time to clean up
				...( window.wcpayAdditionalMethodsSetup
					.isUpeSettingsPreviewEnabled
					? {
							additionalInfo: __(
								'Get early access to additional payment methods and an improved checkout experience',
								'woocommerce-payments'
							),
							title: __(
								'Boost your sales by accepting new payment methods',
								'woocommerce-payments'
							),
							container: (
								<WcPayUpeContextProvider
									defaultIsUpeEnabled={
										window.wcpayAdditionalMethodsSetup
											.isUpeEnabled
									}
								>
									<UpePreviewMethodSelector />
								</WcPayUpeContextProvider>
							),
					  }
					: {} ),
			},
		];
	}
);
