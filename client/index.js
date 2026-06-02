/** @format **/

/**
 * External dependencies
 */
import { createElement, lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
// Create a dependency on wp-mediaelement. Necessary to prevent a type of JS error.
// See discussion in WCPay PR #1263 in GitHub.
// eslint-disable-next-line import/no-unresolved
import 'wp-mediaelement';

/**
 * Internal dependencies
 */
import { getTasks } from 'overview/task-list/tasks';
import { maybeAddReportsPage } from 'reports/page-config';

const lazyRoute = ( importer ) => {
	const LazyComponent = lazy( importer );
	return ( props ) =>
		createElement( Suspense, null, createElement( LazyComponent, props ) );
};

const ConnectAccountPage = lazyRoute( () => import( 'connect-account-page' ) );
const DepositsPage = lazyRoute( () => import( 'deposits' ) );
const DepositDetailsPage = lazyRoute( () => import( 'deposits/details' ) );
const TransactionsPage = lazyRoute( () => import( 'transactions' ) );
const PaymentDetailsPage = lazyRoute( () => import( 'payment-details' ) );
const DisputesPage = lazyRoute( () => import( 'disputes' ) );
const RedirectToTransactionDetails = lazyRoute( () =>
	import( 'disputes/redirect-to-transaction-details' )
);
const DisputeNewEvidencePage = lazyRoute( () =>
	import( 'wcpay/disputes/new-evidence' )
);
const MultiCurrencySetupPage = lazyRoute( () =>
	import( 'multi-currency/setup' )
);
const CardReadersPage = lazyRoute( () => import( 'card-readers' ) );
const CapitalPage = lazyRoute( () => import( 'capital' ) );
const OverviewPage = lazyRoute( () => import( 'overview' ) );
const DocumentsPage = lazyRoute( () => import( 'documents' ) );
const ReportsPage = lazyRoute( () => import( 'reports' ) );
const OnboardingPage = lazyRoute( () => import( 'onboarding' ) );
const OnboardingKycPage = lazyRoute( () => import( 'onboarding/kyc' ) );
const FraudProtectionAdvancedSettingsPage = lazyRoute( () =>
	import( './settings/fraud-protection/advanced-settings' )
);

addFilter(
	'woocommerce_admin_pages_list',
	'woocommerce-payments',
	( pages ) => {
		const { menuID, rootLink } = getMenuSettings();

		const isNavigationEnabled =
			window.wcAdminFeatures && window.wcAdminFeatures.navigation;
		const connectionPageTitle = isNavigationEnabled
			? 'WooPayments'
			: __( 'Connect', 'woocommerce-payments' );

		pages.push( {
			container: ConnectAccountPage,
			path: '/payments/connect',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, connectionPageTitle ],
			navArgs: {
				id: 'wc-payments',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: OnboardingPage,
			path: '/payments/onboarding',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Onboarding', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-onboarding',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: OnboardingKycPage,
			path: '/payments/onboarding/kyc',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Continue onboarding', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-continue-onboarding',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: OverviewPage,
			path: '/payments/overview',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Overview', 'woocommerce-payments' ) ],
			navArgs: {
				id: 'wc-payments-overview',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: DepositsPage,
			path: '/payments/payouts',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Payouts', 'woocommerce-payments' ) ],
			navArgs: {
				id: 'wc-payments-deposits',
			},
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: DepositDetailsPage,
			path: '/payments/payouts/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/payouts',
					__( 'Payouts', 'woocommerce-payments' ),
				],
				__( 'Payout details', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-deposit-details',
				parentPath: '/payments/payouts',
			},
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: TransactionsPage,
			path: '/payments/transactions',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Transactions', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-transactions',
			},
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: PaymentDetailsPage,
			path: '/payments/transactions/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/transactions',
					__( 'Transactions', 'woocommerce-payments' ),
				],
				__( 'Payment details', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-transaction-details',
				parentPath: '/payments/transactions',
			},
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: DisputesPage,
			path: '/payments/disputes',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Disputes', 'woocommerce-payments' ) ],
			navArgs: {
				id: 'wc-payments-disputes',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: RedirectToTransactionDetails,
			path: '/payments/disputes/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/disputes',
					__( 'Disputes', 'woocommerce-payments' ),
				],
				__( 'Dispute details', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-disputes-details-legacy-redirect',
				parentPath: '/payments/disputes',
			},
			capability: 'manage_woocommerce',
		} );

		pages.push( {
			container: DisputeNewEvidencePage,
			path: '/payments/disputes/challenge',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/disputes',
					__( 'Disputes', 'woocommerce-payments' ),
				],
				__( 'Challenge dispute', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-disputes-challenge',
				parentPath: '/payments/disputes',
			},
			capability: 'manage_woocommerce',
		} );
		// Reports has additional feature-flag setup, so its route config lives with the Reports shell.
		maybeAddReportsPage( pages, {
			container: ReportsPage,
			menuID,
			rootLink,
		} );

		pages.push( {
			container: MultiCurrencySetupPage,
			path: '/payments/multi-currency-setup',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Set up multiple currencies', 'woocommerce-payments' ),
			],
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: CardReadersPage,
			path: '/payments/card-readers',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Card readers', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-card-readers',
			},
			capability: 'manage_woocommerce',
		} );
		pages.push( {
			container: CapitalPage,
			path: '/payments/loans',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Capital Loans', 'woocommerce-payments' ),
			],
			navArgs: {
				id: 'wc-payments-capital',
			},
			capability: 'manage_woocommerce',
		} );
		if ( wcpaySettings && wcpaySettings.featureFlags.documents ) {
			pages.push( {
				container: DocumentsPage,
				path: '/payments/documents',
				wpOpenMenu: menuID,
				breadcrumbs: [
					rootLink,
					__( 'Documents', 'woocommerce-payments' ),
				],
				navArgs: {
					id: 'wc-payments-documents',
				},
				capability: 'manage_woocommerce',
			} );
		}
		if ( wcpaySettings ) {
			pages.push( {
				container: FraudProtectionAdvancedSettingsPage,
				path: '/payments/fraud-protection',
				wpOpenMenu: menuID,
				breadcrumbs: [ rootLink, 'Settings' ], // to align with the WooPayments settings pages.
				capability: 'manage_woocommerce',
			} );
		}
		return pages;
	}
);

/**
 * Get menu settings based on the top level link being connect or overview
 *
 * @return { { menuID, rootLink } }  Object containing menuID and rootLink
 */
function getMenuSettings() {
	const connectPage = document.querySelector(
		'#toplevel_page_wc-admin-path--payments-connect'
	);
	const topLevelPage = connectPage ? 'connect' : 'overview';

	return {
		menuID: `toplevel_page_wc-admin-path--payments-${ topLevelPage }`,
		rootLink: [
			`/payments/${ topLevelPage }`,
			__( 'Payments', 'woocommerce-payments' ),
		],
	};
}

addFilter(
	'woocommerce_admin_onboarding_task_list',
	'woocommerce-payments',
	( tasks ) => {
		const { showUpdateDetailsTask, wpcomReconnectUrl } = wcpaySettings;

		const wcPayTasks = getTasks( {
			showUpdateDetailsTask: showUpdateDetailsTask,
			wpcomReconnectUrl: wpcomReconnectUrl,
			showGoLiveTask: true,
		} );

		return [ ...tasks, ...wcPayTasks ];
	}
);
