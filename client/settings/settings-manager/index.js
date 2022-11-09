/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AdvancedSettings from '../advanced-settings';
import PaymentMethods from '../../payment-methods';
import ExpressCheckout from '../express-checkout';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
import SettingsLayout from '../settings-layout';
import SaveSettingsSection from '../save-settings-section';
import Transactions from '../transactions';
import Deposits from '../deposits';
import WCPaySettingsContext from '../wcpay-settings-context';
import LoadableSettingsSection from '../loadable-settings-section';
import WcPayUpeContextProvider from '../wcpay-upe-toggle/provider';
import ErrorBoundary from '../../components/error-boundary';
import { useDepositDelayDays } from '../../data';

const PaymentMethodsDescription = () => (
	<>
		<h2>
			{ __( 'Payments accepted on checkout', 'woocommerce-payments' ) }
		</h2>
		<p>
			{ __(
				'Add and edit payments available to customers at checkout. ' +
					'Based on their device type, location, and purchase history, ' +
					'your customers will only see the most relevant payment methods.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const ExpressCheckoutDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets ' +
					'for faster, more secure checkouts across different parts of your store.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/payments/#section-13">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const GeneralSettingsDescription = () => (
	<>
		<h2>{ __( 'General', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Enable or disable WooCommerce Payments on your store and turn on test mode to simulate transactions.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const TransactionsDescription = () => (
	<>
		<h2>{ __( 'Transactions', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				"Update your store's configuration to ensure smooth transactions.",
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/payments/faq/">
			{ __( 'View frequently asked questions', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const DepositsDescription = () => {
	const depositDelayDays = useDepositDelayDays();

	return (
		<>
			<h2>{ __( 'Deposits', 'woocommerce-payments' ) }</h2>
			<p>
				{ sprintf(
					__(
						'Funds are available for deposit %s business days after they’re received.',
						'woocommerce-payments'
					),
					depositDelayDays
				) }
			</p>
			<ExternalLink href="https://woocommerce.com/document/payments/faq/deposit-schedule/#section-2">
				{ __(
					'Learn more about pending schedules',
					'woocommerce-payments'
				) }
			</ExternalLink>
		</>
	);
};

const SettingsManager = () => {
	const {
		featureFlags: {
			upeSettingsPreview: isUPESettingsPreviewEnabled,
			upe: isUpeEnabled,
		},
	} = useContext( WCPaySettingsContext );

	return (
		<SettingsLayout>
			<SettingsSection description={ GeneralSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<GeneralSettings />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			{ isUPESettingsPreviewEnabled && (
				<SettingsSection description={ PaymentMethodsDescription }>
					<LoadableSettingsSection numLines={ 20 }>
						<ErrorBoundary>
							<WcPayUpeContextProvider
								defaultIsUpeEnabled={ isUpeEnabled }
							>
								<PaymentMethods />
							</WcPayUpeContextProvider>
						</ErrorBoundary>
					</LoadableSettingsSection>
				</SettingsSection>
			) }
			<SettingsSection description={ ExpressCheckoutDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<ExpressCheckout />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<SettingsSection description={ TransactionsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<WcPayUpeContextProvider
							defaultIsUpeEnabled={ isUpeEnabled }
						>
							<Transactions />
						</WcPayUpeContextProvider>
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<SettingsSection description={ DepositsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<Deposits />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<AdvancedSettings />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
