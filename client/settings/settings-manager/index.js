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
import { useDepositDelayDays, useSettings } from '../../data';
import Tour from 'wcpay/components/tour';

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
		<ExternalLink href="https://woocommerce.com/document/payments/settings-guide/#section-4">
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
	const { isLoading } = useSettings();

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
			{ ! isLoading && (
				<Tour
					options={ [
						{
							selector: '.settings-section:last-child',
							content: {
								title: 'Enhanced fraud protection is here 🔒',
								image: 'https://picsum.photos/200',
								description:
									// eslint-disable-next-line max-len
									'Incoming transactions will now be screened for common risk factors, at the level of your choosing. Review any transactions caught by these filters and select whether you’d like to approve or decline them',
								counter: false,
								previousButton: false,
								actionButton: {
									text: 'See what’s new',
								},
							},
						},
						{
							selector: '.deposits__bank-information > h4',
							content: {
								title: 'Choose your filter level 🚦',
								image: {
									src: 'https://picsum.photos/200',
									mobileOnly: true,
								},
								description:
									'Decide how aggressively you want to filter suspicious payments, from standard to advanced.',
								counter: true,
								previousButton: true,
								actionButton: true,
							},
						},
						{
							selector: '.express-checkout__label',
							content: {
								title: 'Take more control 🎚️',
								image: {
									src: 'https://picsum.photos/200',
									mobileOnly: true,
								},
								description:
									// eslint-disable-next-line max-len
									'We recommend using one of the preset risk levels, but if you need more control, head to Advanced to fine-tune the various filters.',
								counter: true,
								previousButton: true,
								actionButton: true,
							},
						},
						{
							selector: '.payment-methods__available-methods',
							content: {
								title: 'Ready for review 📥️',
								image: {
									src: 'https://picsum.photos/200',
									mobileOnly: true,
								},
								description:
									// eslint-disable-next-line max-len
									"Payments that have been caught by a risk filter will appear under Transactions > Payments. We'll let you know why each payment was flagged so that you can determine whether to approve or block it.",
								counter: true,
								previousButton: true,
								actionButton: {
									text: 'Got it',
								},
							},
						},
					] }
				/>
			) }
		</SettingsLayout>
	);
};

export default SettingsManager;
