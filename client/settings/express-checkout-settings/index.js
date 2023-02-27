/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';
import SettingsSection from '../settings-section';
import { getPaymentSettingsUrl } from '../../utils';
import PaymentRequestSettings from './payment-request-settings';
import PlatformCheckoutSettings from './platform-checkout-settings';
import SettingsLayout from '../settings-layout';
import LoadableSettingsSection from '../loadable-settings-section';
import SaveSettingsSection from '../save-settings-section';
import ErrorBoundary from '../../components/error-boundary';
import WooIcon from 'wcpay/gateway-icons/woo';
import ApplePay from 'wcpay/gateway-icons/apple-pay';
import GooglePay from 'wcpay/gateway-icons/google-pay';

const methods = {
	platform_checkout: {
		title: 'WooPay',
		sections: [
			{
				section: 'enable',
				description: () => (
					<>
						<div className="express-checkout-settings__icon">
							<WooIcon />
						</div>
						<p>
							{ __(
								'Allow your customers to collect payments via WooPay.',
								'woocommerce-payments'
							) }
						</p>
					</>
				),
			},
			{
				section: 'appearance',
				description: () => (
					<>
						<div>
							<h2>{ __( 'Checkout appearance' ) }</h2>
						</div>
					</>
				),
			},
			{
				section: 'general',
				description: () => (
					<>
						<h2>{ __( 'Settings', 'woocommerce-payments' ) }</h2>
						<p>
							{ __(
								'Configure the display of WooPay buttons on your store.',
								'woocommerce-payments'
							) }
						</p>
					</>
				),
			},
		],
		controls: ( props ) => <PlatformCheckoutSettings { ...props } />,
	},
	payment_request: {
		title: 'Apple Pay / Google Pay',
		sections: [
			{
				section: 'enable',
				description: () => (
					<>
						<div className="express-checkout-settings__icons">
							<div className="express-checkout-settings__icon">
								<ApplePay />
							</div>
							<div className="express-checkout-settings__icon">
								<GooglePay />
							</div>
						</div>
						<p>
							{ __(
								'Allow your customers to collect payments via Apple Pay and Google Pay.',
								'woocommerce-payments'
							) }
						</p>
					</>
				),
			},
			{
				section: 'general',
				description: () => (
					<>
						<h2>{ __( 'Settings', 'woocommerce-payments' ) }</h2>
						<p>
							{ __(
								'Configure the display of Apple Pay and Google Pay buttons on your store.',
								'woocommerce-payments'
							) }
						</p>
					</>
				),
			},
		],
		controls: ( props ) => <PaymentRequestSettings { ...props } />,
	},
};

const ExpressCheckoutSettings = ( { methodId } ) => {
	const method = methods[ methodId ];

	if ( ! method ) {
		return (
			<p>
				{ __(
					'Invalid express checkout method ID specified.',
					'woocommerce-payments'
				) }
			</p>
		);
	}

	// Only show the 'general' section of the WooPay method if the WooPay express checkout feature is enabled.
	if (
		'WooPay' === method.title &&
		! wcpaySettings.featureFlags.woopayExpressCheckout
	) {
		method.sections = method.sections.filter( ( section ) => {
			return 'general' !== section.section;
		} );
	}

	const { title, sections, controls: Controls } = method;

	return (
		<SettingsLayout>
			<h2 className="express-checkout-settings__breadcrumbs">
				<a href={ getPaymentSettingsUrl() }>
					{ __( 'WooCommerce Payments', 'woocommerce-payments' ) }
				</a>{ ' ' }
				&gt; <span>{ title }</span>
			</h2>

			{ sections.map( ( { section, description } ) => (
				<SettingsSection key={ section } description={ description }>
					<LoadableSettingsSection numLines={ 30 }>
						<ErrorBoundary>
							<Controls section={ section } />
						</ErrorBoundary>
					</LoadableSettingsSection>
				</SettingsSection>
			) ) }

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default ExpressCheckoutSettings;
