/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

type PageConfig = Record< string, unknown >;

interface ReportsPageConfigArgs {
	container: unknown;
	menuID: string;
	rootLink: [ string, string ];
}

export function maybeAddReportsPage(
	pages: PageConfig[],
	{ container, menuID, rootLink }: ReportsPageConfigArgs
): PageConfig[] {
	// Keep the feature-gated Reports route aligned with sibling payment routes; PHP controls account gating and redirects.
	if ( wcpaySettings?.featureFlags?.reportsArea ) {
		pages.push( {
			container,
			path: '/payments/reports',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Reports', 'woocommerce-payments' ) ],
			navArgs: {
				id: 'wc-payments-reports',
			},
			capability: 'manage_woocommerce',
		} );
	}

	return pages;
}
