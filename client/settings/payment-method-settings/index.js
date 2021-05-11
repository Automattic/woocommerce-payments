/** @format */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';
import SettingsSection from '../settings-section';
import { getPaymentSettingsUrl } from '../../utils';
import GiropaySettings from './giropay-settings';
import DigitalWalletsSettings from './digital-wallets-settings';
import Banner from '../../banner';

/* eslint-disable camelcase */
const methods = {
	woocommerce_payments_giropay: {
		title: 'giropay',
		description: () => (
			<>
				{ /* Whoever picks this up will need to translate these strings */ }
				<h2>giropay</h2>
				<p>giropay description.</p>
			</>
		),
		controls: () => <GiropaySettings />,
	},
	woocommerce_payments_digital_wallets: {
		title: 'Digital wallets & express payment methods',
		description: () => (
			<>
				{ /* Whoever picks this up will need to translate these strings */ }
				<h2>Digital wallets &amp; saved cards</h2>
				<p>digital wallets description.</p>
			</>
		),
		controls: () => <DigitalWalletsSettings />,
	},
};
/* eslint-enable camelcase */

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

	const { title, description: Description, controls: Controls } = method;

	return (
		<div className="payment-method-settings">
			<Banner />

			<h2 className="payment-method-settings__breadcrumbs">
				<a href={ getPaymentSettingsUrl() }>WooCommerce Payments</a>{ ' ' }
				&gt; <span>{ title }</span>
			</h2>

			<SettingsSection Description={ Description }>
				<Controls />
			</SettingsSection>
		</div>
	);
};

export default PaymentMethodSettings;
