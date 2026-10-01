/**
 * External dependencies
 */
import React, { useMemo, useState } from 'react';
import { SlotFillProvider, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RegistryProvider, useRegistry } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PaymentMethodsControl } from '../settings/payment-methods-section';
import {
	BuyNowPayLaterControl,
	BuyNowPayLaterMethodsDescription,
} from '../settings/buy-now-pay-later-section';
import { ExpressCheckoutControl } from '../settings/express-checkout';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import DuplicatedPaymentMethodsContext from '../settings/settings-manager/duplicated-payment-methods-context';
import {
	useGetDuplicatedPaymentMethodIds,
	useSettings,
} from '../data/settings';
import FormBusyState from '../components/form-busy-state';
import { createSettingsRegistry } from './settings-registry';

const AccountContext = ( { children }: React.PropsWithChildren ) => {
	const [ dismissedDuplicateNotices, setDismissedDuplicateNotices ] =
		useState( wcpaySettings.dismissedDuplicateNotices || {} );
	const duplicates = useGetDuplicatedPaymentMethodIds();
	const { isSaving } = useSettings();
	return (
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates,
					dismissedDuplicateNotices,
					setDismissedDuplicateNotices,
				} }
			>
				<FormBusyState isBusy={ isSaving }>{ children }</FormBusyState>
			</DuplicatedPaymentMethodsContext.Provider>
		</WCPaySettingsContext.Provider>
	);
};

const ExistingSettings = ( { children }: React.PropsWithChildren ) => {
	const parent = useRegistry();
	const registry = useMemo(
		() => createSettingsRegistry( parent ),
		[ parent ]
	);
	return (
		<RegistryProvider value={ registry }>
			<SlotFillProvider>
				<div className="wcpay-settings-screen__controls">
					<AccountContext>{ children }</AccountContext>
				</div>
			</SlotFillProvider>
		</RegistryProvider>
	);
};

const getMethodTabs = () => [
	{
		name: 'checkout',
		title: __( 'Payments accepted on checkout', 'woocommerce-payments' ),
	},
	{
		name: 'buy-now-pay-later',
		title: __( 'Buy now, pay later', 'woocommerce-payments' ),
	},
	{
		name: 'express-checkouts',
		title: __( 'Express checkouts', 'woocommerce-payments' ),
	},
];

/** The payment method, buy now pay later and express checkout lists, in tabs inside the Payment methods card. */
export const PaymentMethods = () => (
	<ExistingSettings>
		<TabPanel
			className="wcpay-settings-screen__method-tabs"
			tabs={ getMethodTabs() }
		>
			{ ( tab ) => {
				if ( tab.name === 'buy-now-pay-later' ) {
					return (
						<BuyNowPayLaterControl>
							<BuyNowPayLaterMethodsDescription />
						</BuyNowPayLaterControl>
					);
				}
				if ( tab.name === 'express-checkouts' ) {
					return <ExpressCheckoutControl />;
				}
				return <PaymentMethodsControl />;
			} }
		</TabPanel>
	</ExistingSettings>
);
