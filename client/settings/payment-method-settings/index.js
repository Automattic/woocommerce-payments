/** @format */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';
import SettingsSection from '../settings-section';
import { getPaymentSettingsUrl } from '../../utils';
import GiropaySettings from './giropay-settings';

const PaymentMethodSettings = ( { methodId } ) => {
	const methods = [
		{
			id: 'woocommerce_payments_giropay',
			title: 'giropay',
			description: 'giropay description.',
			controls: <GiropaySettings />,
		},
	];

	const method = methods.find( ( { id } ) => id === methodId );

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

	return (
		<div className="payment-method-settings">
			<h2 className="payment-method-settings__breadcrumbs">
				<a href={ getPaymentSettingsUrl() }>WooCommerce Payments</a>{ ' ' }
				&gt; <span>{ method.title }</span>
			</h2>

			<SettingsSection { ...method }>{ method.controls }</SettingsSection>
		</div>
	);
};

export default PaymentMethodSettings;
