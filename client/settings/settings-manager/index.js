/** @format */
/**
 * External dependencies
 */
import React, { useState, useLayoutEffect } from 'react';
import { ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import AdvancedSettings from '../advanced-settings';
import ExpressCheckout from '../express-checkout';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
import ReportingSettings from '../reporting-settings';
import SettingsLayout from '../settings-layout';
import SaveSettingsSection from '../save-settings-section';
import Transactions from '../transactions';
import Deposits from '../deposits';
import LoadableSettingsSection from '../loadable-settings-section';
import PaymentMethodsSection from '../payment-methods-section';
import BuyNowPayLaterSection from '../buy-now-pay-later-section';
import ErrorBoundary from '../../components/error-boundary';
import {
	useDepositDelayDays,
	useGetDuplicatedPaymentMethodIds,
	useSettings,
} from '../../data';
import FraudProtection from '../fraud-protection';
import { isDefaultSiteLanguage } from 'wcpay/utils';
import DuplicatedPaymentMethodsContext from './duplicated-payment-methods-context';

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
		<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/#express-checkouts">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const GeneralSettingsDescription = () => (
	<>
		<h2>{ __( 'General', 'woocommerce-payments' ) }</h2>
		<p>
			{ sprintf(
				/* translators: %s: WooPayments */
				__(
					'Enable or disable %s on your store.',
					'woocommerce-payments'
				),
				'WooPayments'
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
		<ExternalLink href="https://woocommerce.com/document/woopayments/">
			{ __( 'View our documentation', 'woocommerce-payments' ) }
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
			<ExternalLink href="https://woocommerce.com/document/woopayments/deposits/deposit-schedule/">
				{ __(
					'Learn more about pending schedules',
					'woocommerce-payments'
				) }
			</ExternalLink>
		</>
	);
};

const FraudProtectionDescription = () => {
	return (
		<>
			<h2>{ __( 'Fraud protection', 'woocommerce-payments' ) }</h2>
			<p>
				{ __(
					'Help avoid unauthorized transactions and disputes by setting your fraud protection level.',
					'woocommerce-payments'
				) }
			</p>
			<ExternalLink href="https://woocommerce.com/document/woopayments/fraud-and-disputes/fraud-protection/">
				{ __(
					'Learn more about fraud protection',
					'woocommerce-payments'
				) }
			</ExternalLink>
		</>
	);
};

const ReportingDescription = () => {
	return (
		<>
			<h2>{ __( 'Reporting', 'woocommerce-payments' ) }</h2>
			<p>
				{ __(
					'Adjust your report exporting language preferences.',
					'woocommerce-payments'
				) }
			</p>
		</>
	);
};

const AdvancedDescription = () => {
	return (
		<>
			<h2>{ __( 'Advanced settings', 'woocommerce-payments' ) }</h2>
			<p>
				{ __(
					'More options for specific payment needs.',
					'woocommerce-payments'
				) }
			</p>
			<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/#advanced-settings">
				{ __( 'View our documentation', 'woocommerce-payments' ) }
			</ExternalLink>
		</>
	);
};

const SettingsManager = () => {
	const [ isTransactionInputsValid, setTransactionInputsValid ] = useState(
		true
	);

	const { isLoading } = useSettings();

	useLayoutEffect( () => {
		const { anchor } = getQuery();
		const { hash } = window.location;
		const scrollTo = anchor || hash;

		if ( ! isLoading && scrollTo ) {
			const element = document.querySelector( scrollTo );

			if ( ! element ) {
				return;
			}

			const headerElement = document.querySelector(
				'.woocommerce-layout__header'
			);
			const headerSize = headerElement ? headerElement.clientHeight : 60;
			const headerOffset = headerSize + 50; // header size + margin
			const elementPosition = element.getBoundingClientRect().top;
			const offsetPosition =
				elementPosition + window.pageYOffset - headerOffset;

			window.scrollTo( {
				top: offsetPosition,
				behavior: 'smooth',
			} );
		}
	}, [ isLoading ] );

	const [
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
	] = useState( wcpaySettings.dismissedDuplicateNotices || {} );

	return (
		<SettingsLayout>
			<SettingsSection
				description={ GeneralSettingsDescription }
				id="general"
			>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<GeneralSettings />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: useGetDuplicatedPaymentMethodIds(),
					dismissedDuplicateNotices: dismissedDuplicateNotices,
					setDismissedDuplicateNotices: setDismissedDuplicateNotices,
				} }
			>
				<PaymentMethodsSection />
				<BuyNowPayLaterSection />
				<SettingsSection
					id="express-checkouts"
					description={ ExpressCheckoutDescription }
				>
					<LoadableSettingsSection numLines={ 20 }>
						<ErrorBoundary>
							<ExpressCheckout />
						</ErrorBoundary>
					</LoadableSettingsSection>
				</SettingsSection>
			</DuplicatedPaymentMethodsContext.Provider>
			<SettingsSection
				description={ TransactionsDescription }
				id="transactions"
			>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<Transactions
							setTransactionInputsValid={
								setTransactionInputsValid
							}
						/>
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<SettingsSection description={ DepositsDescription } id="deposits">
				<div id={ 'deposit-schedule' }>
					<LoadableSettingsSection numLines={ 20 }>
						<ErrorBoundary>
							<Deposits />
						</ErrorBoundary>
					</LoadableSettingsSection>
				</div>
			</SettingsSection>
			<SettingsSection
				description={ FraudProtectionDescription }
				id="fp-settings"
			>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<FraudProtection />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			{ ! isDefaultSiteLanguage() && (
				<SettingsSection
					description={ ReportingDescription }
					id="fp-settings"
				>
					<LoadableSettingsSection numLines={ 20 }>
						<ErrorBoundary>
							<ReportingSettings />
						</ErrorBoundary>
					</LoadableSettingsSection>
				</SettingsSection>
			) }
			<SettingsSection
				description={ AdvancedDescription }
				id="advanced-settings"
			>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						<AdvancedSettings />
					</ErrorBoundary>
				</LoadableSettingsSection>
			</SettingsSection>
			<SaveSettingsSection disabled={ ! isTransactionInputsValid } />
		</SettingsLayout>
	);
};

export default SettingsManager;
