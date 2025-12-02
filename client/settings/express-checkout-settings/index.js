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
import PaymentRequestSettings from './payment-request-settings';
import WooPaySettings from './woopay-settings';
import AmazonPaySettings from './amazon-pay-settings';
import SettingsLayout from '../settings-layout';
import LoadableSettingsSection from '../loadable-settings-section';
import SaveSettingsSection from '../save-settings-section';
import ErrorBoundary from '../../components/error-boundary';
import { AmazonPayIcon, WooIcon } from 'wcpay/payment-methods-icons';
import methodsConfiguration from 'wcpay/payment-methods-map';

const methods = {
	woopay: {
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
		controls: ( props ) => <WooPaySettings { ...props } />,
	},
	payment_request: {
		title: 'Apple Pay / Google Pay',
		sections: [
			{
				section: 'enable',
				description: () => {
					const {
						icon: ApplePayIcon,
					} = methodsConfiguration.apple_pay;
					const {
						icon: GooglePayIcon,
					} = methodsConfiguration.google_pay;

					return (
						<>
							<div className="express-checkout-settings__icons">
								<div className="express-checkout-settings__icon">
									<ApplePayIcon />
								</div>
								<div className="express-checkout-settings__icon">
									<GooglePayIcon />
								</div>
							</div>
							<p>
								{ __(
									'Allow your customers to collect payments via Apple Pay and Google Pay.',
									'woocommerce-payments'
								) }
							</p>
						</>
					);
				},
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
	amazon_pay: {
		title: 'Amazon Pay',
		sections: [
			{
				section: 'enable',
				description: () => (
					<>
						<div className="express-checkout-settings__icon">
							<AmazonPayIcon />
						</div>
						<p>
							{ __(
								'Allow your customers to collect payments via Amazon Pay.',
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
								'Configure the display of Amazon Pay buttons on your store.',
								'woocommerce-payments'
							) }
						</p>
					</>
				),
			},
		],
		controls: ( props ) => <AmazonPaySettings { ...props } />,
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
		method.title === 'WooPay' &&
		! wcpaySettings.featureFlags.woopayExpressCheckout
	) {
		method.sections = method.sections.filter( ( section ) => {
			return section.section !== 'general';
		} );
	}

	const { sections, controls: Controls } = method;

	return (
		<SettingsLayout>
			{ sections.map( ( { section, description } ) => (
				<SettingsSection
					key={ section }
					description={ description }
					className={ `wcpay-express-checkout__${ section }` }
				>
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
