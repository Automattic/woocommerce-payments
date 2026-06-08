/** @format */

/**
 * Internal dependencies
 */
import { maybeAddReportsPage } from '../page-config';

declare const global: {
	wcpaySettings: {
		featureFlags: {
			reportsArea?: boolean;
		};
		isJetpackConnected: boolean;
		isAccountValid: boolean;
		accountStatus: {
			status?: string;
		};
	};
};

describe( 'Reports page config', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			featureFlags: {},
			isJetpackConnected: true,
			isAccountValid: true,
			accountStatus: {
				status: 'complete',
			},
		};
	} );

	it( 'does not register the Reports route when the feature flag is disabled', () => {
		const pages: Record< string, unknown >[] = [];

		maybeAddReportsPage( pages, {
			container: jest.fn(),
			menuID: 'toplevel_page_wc-admin-path--payments-overview',
			rootLink: [ '/payments/overview', 'Payments' ],
		} );

		expect( pages ).toHaveLength( 0 );
	} );

	it( 'registers the Reports route when the feature flag is enabled', () => {
		const ReportsContainer = jest.fn();
		const pages: Record< string, unknown >[] = [];
		global.wcpaySettings.featureFlags.reportsArea = true;

		maybeAddReportsPage( pages, {
			container: ReportsContainer,
			menuID: 'toplevel_page_wc-admin-path--payments-overview',
			rootLink: [ '/payments/overview', 'Payments' ],
		} );

		expect( pages ).toContainEqual(
			expect.objectContaining( {
				container: ReportsContainer,
				path: '/payments/reports',
				wpOpenMenu: 'toplevel_page_wc-admin-path--payments-overview',
				breadcrumbs: [
					[ '/payments/overview', 'Payments' ],
					'Reports',
				],
				navArgs: { id: 'wc-payments-reports' },
				capability: 'manage_woocommerce',
			} )
		);
	} );

	it( 'registers the feature-gated Reports route regardless of account state', () => {
		const pages: Record< string, unknown >[] = [];
		global.wcpaySettings.featureFlags.reportsArea = true;
		global.wcpaySettings.isAccountValid = false;
		global.wcpaySettings.isJetpackConnected = false;
		global.wcpaySettings.accountStatus.status = 'rejected.other';

		maybeAddReportsPage( pages, {
			container: jest.fn(),
			menuID: 'toplevel_page_wc-admin-path--payments-overview',
			rootLink: [ '/payments/overview', 'Payments' ],
		} );

		expect( pages ).toContainEqual(
			expect.objectContaining( {
				path: '/payments/reports',
			} )
		);
	} );
} );
