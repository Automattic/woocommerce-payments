/**
 * External dependencies
 */
import React, { useMemo, useState } from 'react';
import {
	BaseControl,
	ExternalLink,
	SlotFillProvider,
	TabPanel,
} from '@wordpress/components';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { __ } from '@wordpress/i18n';
import { RegistryProvider, useRegistry } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PaymentMethodsControl } from '../settings/payment-methods-section';
import { BuyNowPayLaterControl } from '../settings/buy-now-pay-later-section';
import { ExpressCheckoutControl } from '../settings/express-checkout';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import DuplicatedPaymentMethodsContext from '../settings/settings-manager/duplicated-payment-methods-context';
import {
	useGetDuplicatedPaymentMethodIds,
	useSettings,
} from '../data/settings';
import FormBusyState from '../components/form-busy-state';
import { PaymentMethodItemToggleContext } from '../components/payment-method-item';
import {
	createSettingsRegistry,
	getCurrentEntityName,
} from './settings-registry';
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
	const name = getCurrentEntityName();
	const registry = useMemo(
		() => createSettingsRegistry( parent, name ),
		[ parent, name ]
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

/** A tab's description, with a link to its documentation. */
const TabDescription = ( {
	text,
	link,
	linkLabel,
}: {
	text: string;
	link: string;
	linkLabel: string;
} ) => (
	<p className="wcpay-settings-screen__card-description wcpay-settings-screen__tab-description">
		{ text } <ExternalLink href={ link }>{ linkLabel }</ExternalLink>
	</p>
);

const learnMore = () => __( 'Learn more', 'woocommerce-payments' );

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
							<TabDescription
								text={ __(
									'Boost sales by offering customers additional buying power and flexible payment options.',
									'woocommerce-payments'
								) }
								link="https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/"
								linkLabel={ learnMore() }
							/>
						</BuyNowPayLaterControl>
					);
				}
				if ( tab.name === 'express-checkouts' ) {
					return (
						<>
							<TabDescription
								text={ __(
									'Let your customers use their favorite express payment methods and digital wallets ' +
										'for faster, more secure checkouts across different parts of your store.',
									'woocommerce-payments'
								) }
								link="https://woocommerce.com/document/woopayments/settings-guide/#express-checkouts"
								linkLabel={ learnMore() }
							/>
							<ExpressCheckoutControl />
						</>
					);
				}
				return (
					<>
						<TabDescription
							text={ __(
								'Add and edit payments available to customers at checkout. ' +
									'Based on their device type, location, and purchase history, ' +
									'your customers will only see the most relevant payment methods.',
								'woocommerce-payments'
							) }
							link="https://woocommerce.com/document/woopayments/fees/"
							linkLabel={ __(
								'Learn about WooPayments fees in your country',
								'woocommerce-payments'
							) }
						/>
						<PaymentMethodsControl />
					</>
				);
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
