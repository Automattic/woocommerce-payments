/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { registerEntityField } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { getFieldParts, FieldParts } from './field-parts';
import './style.scss';

type Field = Record< string, unknown > & { id: string };

declare global {
	const wcpaySettingsScreenConfig: {
		kind: string;
		baseURL: string;
		entities: { name: string; label: string; fields: Field[] }[];
		fraudRulesUrl: string;
	};
	interface Window {
		wcpaySettingsScreen?: { fieldParts: Record< string, FieldParts > };
	}
}

const { kind, baseURL, entities } = wcpaySettingsScreenConfig;
const fieldParts = getFieldParts();
window.wcpaySettingsScreen = { fieldParts };

// WooCommerce shows a page for each entity of its kind, so they're registered before the screen loads.
dispatch( 'core' ).addEntities(
	entities.map( ( { name, label } ) => ( {
		kind,
		name,
		baseURL,
		key: 'id',
		label,
		getTitle: () => label,
	} ) )
);
entities.forEach( ( { name, fields } ) => {
	fields.forEach( ( field ) => {
		registerEntityField( kind, name, {
			...field,
			...fieldParts[ field.id ],
		} );
	} );
} );
