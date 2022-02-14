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
import PaymentRequestIcon from 'wcpay/gateway-icons/payment-request';
import WooIcon from 'wcpay/gateway-icons/woo';

const methods = {
	platform_checkout: {
		title: 'Platform Checkout',
		sections: [
			{
				section: 'general',
				description: () => (
					<>
						<div className="express-checkout-settings__icon">
							<WooIcon />
						</div>
						<p>
							{ __(
								'Allow your customers to collect payments via Platform Checkout.',
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
						<div className="express-checkout-settings__icon">
							<PaymentRequestIcon />
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
				<SettingsSection key={ section } Description={ description }>
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
