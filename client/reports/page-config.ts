/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const reportsPath = '/payments/reports';
export const reportsNavId = 'wc-payments-reports';

type PageConfig = Record< string, unknown >;

interface ReportsPageConfigArgs {
	container: unknown;
	menuID: string;
	rootLink: [ string, string ];
}

function getReportsPageConfig( {
	container,
	menuID,
	rootLink,
}: ReportsPageConfigArgs ): PageConfig {
	return {
		container,
		path: reportsPath,
		wpOpenMenu: menuID,
		breadcrumbs: [ rootLink, __( 'Reports', 'woocommerce-payments' ) ],
		navArgs: {
			id: reportsNavId,
		},
		capability: 'manage_woocommerce',
	};
}

function isReportsRouteAvailable(): boolean {
	return !! wcpaySettings?.featureFlags?.reportsArea;
}

export function maybeAddReportsPage(
	pages: PageConfig[],
	args: ReportsPageConfigArgs
): PageConfig[] {
	// Keep the feature-gated Reports route aligned with sibling payment routes; PHP controls account gating and redirects.
	if ( isReportsRouteAvailable() ) {
		pages.push( getReportsPageConfig( args ) );
	}

	return pages;
}
