/**
 * External dependencies
 */
import { addAction, addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getFields, EntityField } from './fields';

type Entity = { kind: string; name: string; baseURL: string };
type Page = { id: string; label: string; url: string; entity?: Entity };

const entity: Entity = {
	kind: 'woo_settings',
	name: 'woopayments',
	baseURL: '/wc/v3/payments/settings-dataform',
};

addFilter(
	'woocommerce.settingsDataform.pages',
	'woopayments/settings-dataform',
	( pages: Page[] ) => [
		...pages,
		{
			id: 'woopayments',
			label: __( 'WooPayments', 'woocommerce-payments' ),
			url: wcpaySettingsDataform.classicUrl,
			entity,
		},
	]
);

addAction(
	'woocommerce.settingsDataform.registerFields',
	'woopayments/settings-dataform',
	(
		current: Entity,
		register: ( kind: string, name: string, field: EntityField ) => void
	) => {
		if ( current.kind === entity.kind && current.name === entity.name ) {
			getFields().forEach( ( field ) =>
				register( entity.kind, entity.name, field )
			);
		}
	}
);
