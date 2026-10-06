/**
 * Internal dependencies
 */
import { getFieldParts, FieldParts } from './field-parts';
import './style.scss';

declare global {
	interface Window {
		wcpaySettingsScreen?: { fieldParts: Record< string, FieldParts > };
	}
}

// Read by the fields' script module, which WooCommerce loads after this script.
window.wcpaySettingsScreen = { fieldParts: getFieldParts() };
