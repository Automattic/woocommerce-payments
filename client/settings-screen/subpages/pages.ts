/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { FieldParts } from '../field-parts';

type Field = Record< string, unknown > & { id: string };

export interface SubPage {
	name: string;
	label: string;
	fields: Field[];
}

export interface Crumb {
	label: string;
	to?: string;
}

/**
 * The main WooPayments page. Its entity name is also the ID of the settings record every page edits.
 */
export const getMainPage = (): SubPage | undefined =>
	wcpaySettingsScreenConfig.entities.find(
		( { name } ) => name === wcpaySettingsScreenConfig.recordId
	);

/**
 * Find a sub-page by its path below the main page, such as `woopay`.
 */
export const getSubPage = ( path?: string ): SubPage | undefined =>
	path
		? wcpaySettingsScreenConfig.entities.find(
				( { name } ) =>
					name === `${ wcpaySettingsScreenConfig.recordId }/${ path }`
			)
		: undefined;

/**
 * Merge a page's fields with the parts PHP can't describe, as the main page does.
 */
export const getFields = (
	page: SubPage,
	fieldParts: Record< string, FieldParts > = {}
) =>
	page.fields.map( ( field ) => ( { ...field, ...fieldParts[ field.id ] } ) );

/**
 * Breadcrumbs for a sub-page: the classic Payments settings, the main WooPayments page, then the
 * sub-page itself. They match the crumbs WooCommerce shows on the main page.
 */
export function getCrumbs( page: SubPage, baseUrl: string ): Crumb[] {
	const main = getMainPage();
	return [
		{
			label: __( 'Payments', 'woocommerce-payments' ),
			// The router only leaves the screen for an absolute URL.
			to: new URL(
				'admin.php?page=wc-settings&tab=checkout',
				baseUrl
			).toString(),
		},
		{
			label: main?.label ?? wcpaySettingsScreenConfig.recordId,
			to: `/settings/payments/${ wcpaySettingsScreenConfig.recordId }`,
		},
		{ label: page.label },
	];
}
