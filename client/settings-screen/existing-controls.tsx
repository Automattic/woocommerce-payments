/**
 * External dependencies
 */
import React, { useMemo, useState } from 'react';
import { BaseControl, SlotFillProvider, TabPanel } from '@wordpress/components';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
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
import { PaymentMethodItemToggleContext } from '../components/payment-method-item';
import { createSettingsRegistry } from './settings-registry';
import PaymentRequestButtonPreview from '../settings/express-checkout-settings/payment-request-button-preview';
import { getExpressCheckoutConfig } from 'utils/express-checkout';

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
				<PaymentMethodItemToggleContext.Provider value={ true }>
					<div className="wcpay-settings-screen__controls">
						<AccountContext>{ children }</AccountContext>
					</div>
				</PaymentMethodItemToggleContext.Provider>
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

/** A preview of the express checkout buttons with the style being edited. */
export const ButtonPreview = () => {
	const stripePromise = useMemo( () => {
		const stripeSettings = getExpressCheckoutConfig( 'stripe' );
		return loadStripe( stripeSettings.publishableKey, {
			stripeAccount: stripeSettings.accountId,
			locale: stripeSettings.locale,
		} );
	}, [] );
	return (
		<ExistingSettings>
			{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
			<BaseControl
				label={ __( 'Preview', 'woocommerce-payments' ) }
				__nextHasNoMarginBottom
			>
				<Elements stripe={ stripePromise }>
					<PaymentRequestButtonPreview />
				</Elements>
			</BaseControl>
		</ExistingSettings>
	);
};
