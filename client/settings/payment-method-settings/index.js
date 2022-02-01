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
import SettingsLayout from '../settings-layout';
import LoadableSettingsSection from '../loadable-settings-section';
import SaveSettingsSection from '../save-settings-section';
import ErrorBoundary from '../../components/error-boundary';
import AppleIcon from '../../gateway-icons/apple';
import GoogleIcon from '../../gateway-icons/google';

const methods = {
	payment_request: {
		title: 'Apple Pay / Google Pay',
		sections: [
			{
				section: 'enable',
				description: () => (
					<>
						<div className="payment-request__icon">
							<AppleIcon /> <GoogleIcon />
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

const PaymentMethodSettings = ( { methodId } ) => {
	const method = methods[ methodId ];

	if ( ! method ) {
		return (
			<p>
				{ __(
					'Invalid payment method ID specified.',
					'woocommerce-payments'
				) }
			</p>
		);
	}

	const { title, sections, controls: Controls } = method;

	return (
		<SettingsLayout>
			<h2 className="payment-method-settings__breadcrumbs">
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

export default PaymentMethodSettings;
