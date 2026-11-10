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
		recordId: string;
		entities: { name: string; label: string; fields: Field[] }[];
		fraudRulesUrl: string;
	};
	interface Window {
		wcpaySettingsScreen?: { fieldParts: Record< string, FieldParts > };
	}
}

const { kind, baseURL, recordId, entities } = wcpaySettingsScreenConfig;
const fieldParts = getFieldParts();
window.wcpaySettingsScreen = { fieldParts };

// WooCommerce shows the main page's entity, so the entities are registered before the screen loads.
// Sub-pages are named after their path below the main page and shown by WooPayments' own route
// (`subpages/`). Every page edits the same settings record.
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
entities
	.find( ( { name } ) => name === recordId )
	?.fields.forEach( ( field ) => {
		registerEntityField( kind, recordId, {
			...field,
			...fieldParts[ field.id ],
		} );
	} );
