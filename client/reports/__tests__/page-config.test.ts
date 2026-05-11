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

	it( 'does not register the Reports route when the account is not valid', () => {
		const pages: Record< string, unknown >[] = [];
		global.wcpaySettings.featureFlags.reportsArea = true;
		global.wcpaySettings.isAccountValid = false;

		maybeAddReportsPage( pages, {
			container: jest.fn(),
			menuID: 'toplevel_page_wc-admin-path--payments-overview',
			rootLink: [ '/payments/overview', 'Payments' ],
		} );

		expect( pages ).toHaveLength( 0 );
	} );

	it( 'does not register the Reports route when Jetpack is disconnected', () => {
		const pages: Record< string, unknown >[] = [];
		global.wcpaySettings.featureFlags.reportsArea = true;
		global.wcpaySettings.isJetpackConnected = false;

		maybeAddReportsPage( pages, {
			container: jest.fn(),
			menuID: 'toplevel_page_wc-admin-path--payments-overview',
			rootLink: [ '/payments/overview', 'Payments' ],
		} );

		expect( pages ).toHaveLength( 0 );
	} );

	it.each( [ 'rejected.fraud', 'rejected.other', 'under_review' ] )(
		'does not register the Reports route for %s accounts',
		( status ) => {
			const pages: Record< string, unknown >[] = [];
			global.wcpaySettings.featureFlags.reportsArea = true;
			global.wcpaySettings.accountStatus.status = status;

			maybeAddReportsPage( pages, {
				container: jest.fn(),
				menuID: 'toplevel_page_wc-admin-path--payments-overview',
				rootLink: [ '/payments/overview', 'Payments' ],
			} );

			expect( pages ).toHaveLength( 0 );
		}
	);
} );
