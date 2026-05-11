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

export function getReportsPageConfig( {
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
	const accountStatus = wcpaySettings?.accountStatus?.status;
	const isAccountRejected =
		typeof accountStatus === 'string' &&
		accountStatus.startsWith( 'rejected' );

	return !! (
		wcpaySettings?.featureFlags?.reportsArea &&
		wcpaySettings?.isJetpackConnected &&
		wcpaySettings?.isAccountValid &&
		! isAccountRejected &&
		accountStatus !== 'under_review'
	);
}

export function maybeAddReportsPage(
	pages: PageConfig[],
	args: ReportsPageConfigArgs
): PageConfig[] {
	if ( isReportsRouteAvailable() ) {
		pages.push( getReportsPageConfig( args ) );
	}

	return pages;
}
