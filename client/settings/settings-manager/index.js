/** @format */
/**
 * External dependencies
 */
import React, {
	useState,
	useLayoutEffect,
	useCallback,
	useEffect,
} from 'react';
import { Card, CardBody, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';
import { loadConnectAndInitialize } from '@stripe/connect-js/pure';
import {
	ConnectAccountManagement,
	ConnectComponentsProvider,
} from '@stripe/react-connect-js';

/**
 * Internal dependencies
 */
import AdvancedSettings from '../advanced-settings';
import ExpressCheckout from '../express-checkout';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
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
import DuplicatedPaymentMethodsContext from './duplicated-payment-methods-context';
import { createAccountSession } from 'wcpay/onboarding/utils';
import appearance from 'wcpay/onboarding/kyc/appearance';

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
			<h2>{ __( 'Payouts', 'woocommerce-payments' ) }</h2>
			<p>
				{ sprintf(
					__(
						'Funds are available for payout %s business days after they’re received.',
						'woocommerce-payments'
					),
					depositDelayDays
				) }
			</p>
			<ExternalLink href="https://woocommerce.com/document/woopayments/payouts/payout-schedule/">
				{ __(
					'Learn more about pending schedules',
					'woocommerce-payments'
				) }
			</ExternalLink>
		</>
	);
};

const AccountDetailsDescription = () => {
	return (
		<>
			<h2>{ __( 'Account details', 'woocommerce-payments' ) }</h2>
			<p>
				{ __(
					'View and edit your WooPayments account details like personal or business information and public information.',
					'woocommerce-payments'
				) }
			</p>
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

	const [ locale, setLocale ] = useState( '' );
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );
	const [ stripeConnectInstance, setStripeConnectInstance ] = useState(
		null
	);

	const fetchAccountSession = useCallback( async () => {
		try {
			const accountSession = await createAccountSession( null, false );
			if ( accountSession && accountSession.clientSecret ) {
				return accountSession; // Return the full account session object
			}

			setLoadErrorMessage(
				__(
					"Failed to create account session. Please check that you're using the latest version of WooPayments.",
					'woocommerce-payments'
				)
			);
		} catch ( error ) {
			setLoadErrorMessage(
				__(
					'Failed to retrieve account session. Please try again later.',
					'woocommerce-payments'
				)
			);
		}

		// Return null if an error occurred.
		return null;
	}, [] );

	// Function to fetch clientSecret for use in Stripe auto-refresh or initialization
	const fetchClientSecret = useCallback( async () => {
		const accountSession = await fetchAccountSession();
		if ( accountSession ) {
			return accountSession.clientSecret; // Only return the clientSecret
		}
		throw new Error( 'Error fetching the client secret' );
	}, [ fetchAccountSession ] );

	// Effect to fetch the publishable key and clientSecret on initial render
	useEffect( () => {
		const fetchKeys = async () => {
			try {
				const accountSession = await fetchAccountSession();
				if ( accountSession ) {
					setLocale( accountSession.locale );
					setPublishableKey( accountSession.publishableKey );
					setClientSecret( () => fetchClientSecret );
				}
			} catch ( error ) {
				setLoadErrorMessage(
					__(
						'Failed to create account session. Please check that you are using the latest version of WooPayments.',
						'woocommerce-payments'
					)
				);
			}
		};

		fetchKeys();
	}, [ fetchAccountSession, fetchClientSecret ] );

	// Effect to initialize the Stripe Connect instance once publishableKey and clientSecret are ready.
	useEffect( () => {
		if ( publishableKey && clientSecret && ! stripeConnectInstance ) {
			const stripeInstance = loadConnectAndInitialize( {
				publishableKey,
				fetchClientSecret,
				appearance: {
					overlays: 'drawer',
					variables: appearance.variables,
				},
				locale: locale.replace( '_', '-' ),
			} );

			setStripeConnectInstance( stripeInstance );
		}
	}, [
		publishableKey,
		clientSecret,
		stripeConnectInstance,
		fetchClientSecret,
		locale,
	] );

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
				<div id="payout-schedule">
					<LoadableSettingsSection numLines={ 20 }>
						<ErrorBoundary>
							<Deposits />
						</ErrorBoundary>
					</LoadableSettingsSection>
				</div>
			</SettingsSection>
			<SettingsSection
				description={ AccountDetailsDescription }
				id="account-details"
			>
				<LoadableSettingsSection numLines={ 20 }>
					<ErrorBoundary>
						{ loadErrorMessage ? (
							<div>error</div>
						) : (
							stripeConnectInstance && (
								<Card>
									<CardBody>
										<ConnectComponentsProvider
											connectInstance={
												stripeConnectInstance
											}
										>
											<ConnectAccountManagement
												collectionOptions={ {
													fields: 'eventually_due',
													futureRequirements:
														'include',
												} }
											/>
										</ConnectComponentsProvider>
									</CardBody>
								</Card>
							)
						) }
					</ErrorBoundary>
				</LoadableSettingsSection>
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
