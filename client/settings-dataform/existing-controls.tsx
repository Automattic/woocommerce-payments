/**
 * External dependencies
 */
import React, { useMemo, useState } from 'react';
import { SlotFillProvider } from '@wordpress/components';
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
				<div className="wcpay-settings-dataform-controls">
					<AccountContext>{ children }</AccountContext>
				</div>
			</SlotFillProvider>
		</RegistryProvider>
	);
};

export const PaymentMethods = () => (
	<ExistingSettings>
		<PaymentMethodsControl />
		<BuyNowPayLaterControl>
			<BuyNowPayLaterMethodsDescription />
		</BuyNowPayLaterControl>
	</ExistingSettings>
);
export const ExpressCheckouts = () => (
	<ExistingSettings>
		<ExpressCheckoutControl />
	</ExistingSettings>
);
