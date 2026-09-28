/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import SettingsScreenBody from './body';
import { getFieldParts, FieldParts } from './field-parts';
import './style.scss';

declare global {
	interface Window {
		wcpaySettingsScreen?: { fieldParts: Record< string, FieldParts > };
	}
}

// Read by the fields' script module, which WooCommerce loads after this script.
window.wcpaySettingsScreen = { fieldParts: getFieldParts() };

addFilter(
	'woocommerce.experimentalPaymentSettings.body',
	'woocommerce-payments/settings-screen',
	( body: unknown, screenId: string ) =>
		screenId === 'woopayments' ? SettingsScreenBody : body
);
