/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AdvancedSettings from '../advanced-settings';
import PaymentMethods from '../../payment-methods';
import PaymentRequest from '../payment-request';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
import ApplePayIcon from '../../gateway-icons/apple-pay';
import GooglePayIcon from '../../gateway-icons/google-pay';
import SettingsLayout from '../settings-layout';
import SaveSettingsSection from '../save-settings-section';
import TransactionsAndDeposits from '../transactions-and-deposits';
import WCPaySettingsContext from '../wcpay-settings-context';
import LoadableSettingsSection from '../loadable-settings-section';
import WcPayUpeContextProvider from '../wcpay-upe-toggle/provider';

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

const PaymentRequestDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
		<ul className="settings-section__icons">
			<li>
				<ApplePayIcon />
			</li>
			<li>
				<GooglePayIcon />
			</li>
		</ul>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets ' +
					'for faster, more secure checkouts across different parts of your store.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/payments/apple-pay/">
			{ __( 'How it works?', 'woocommerce-payments' ) }
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

const TransactionsAndDepositsDescription = () => (
	<>
		<h2>{ __( 'Transactions and deposits', 'woocommerce-payments' ) }</h2>
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

const SettingsManager = () => {
	const {
		featureFlags: {
			upeSettingsPreview: isUPESettingsPreviewEnabled,
			upe: isUpeEnabled,
		},
	} = useContext( WCPaySettingsContext );

	return (
		<SettingsLayout>
			<SettingsSection Description={ GeneralSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<GeneralSettings />
				</LoadableSettingsSection>
			</SettingsSection>
			{ isUPESettingsPreviewEnabled && (
				<SettingsSection Description={ PaymentMethodsDescription }>
					<LoadableSettingsSection numLines={ 20 }>
						<WcPayUpeContextProvider
							defaultIsUpeEnabled={ isUpeEnabled }
						>
							<PaymentMethods />
						</WcPayUpeContextProvider>
					</LoadableSettingsSection>
				</SettingsSection>
			) }
			<SettingsSection Description={ PaymentRequestDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<PaymentRequest />
				</LoadableSettingsSection>
			</SettingsSection>
			<SettingsSection Description={ TransactionsAndDepositsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<TransactionsAndDeposits />
				</LoadableSettingsSection>
			</SettingsSection>
			<AdvancedSettings />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
