/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import OverviewPage from '../';
import { getTasks } from '../task-list/tasks';
import { getQuery } from '@woocommerce/navigation';

const settingsMock = {
	enabled_payment_method_ids: [ 'foo', 'bar' ],
	is_wcpay_enabled: true,
};

jest.mock( 'wcpay/embedded-components', () => {
	return {
		EmbeddedConnectNotificationBanner: () => (
			<div className="embedded-connec-notification-banner"></div>
		),
	};
} );
jest.mock( '../task-list/tasks', () => ( { getTasks: jest.fn() } ) );
jest.mock( '../inbox-notifications', () =>
	jest.fn().mockImplementation( () => '[inbox-notifications]' )
);
jest.mock( '@woocommerce/experimental', () => {
	return {
		CollapsibleList: () => (
			<div className="woocommerce-experimental-list"></div>
		),
		Text: () => <div>text</div>,
	};
} );
jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
	addHistoryListener: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	registerStore: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: jest.fn( () => ( { updateOptions: jest.fn() } ) ),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
	} ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	createRegistryControl: jest.fn(),
	select: jest.fn(),
	withSelect: jest.fn( () => jest.fn() ),
	useSelect: jest.fn( () => ( { getNotices: jest.fn() } ) ),
} ) );
jest.mock( '@wordpress/data-controls' );
jest.mock( 'wcpay/data', () => ( {
	useGetSettings: jest
		.fn()
		.mockReturnValue( { enabled_payment_method_ids: [ 'foo', 'bar' ] } ),
	useSettings: jest.fn().mockReturnValue( {} ),
	useDisputes: jest
		.fn()
		.mockReturnValue( { disputes: [], isLoading: false } ),
	useDeposits: jest
		.fn()
		.mockReturnValue( { deposits: [], isLoading: false } ),
	useAllDepositsOverviews: jest
		.fn()
		.mockReturnValue( { overviews: { currencies: [] } } ),
	useActiveLoanSummary: jest.fn().mockReturnValue( { isLoading: true } ),
	usePmPromotions: jest
		.fn()
		.mockReturnValue( { pmPromotions: [], isLoading: false } ),
	usePmPromotionActions: jest.fn().mockReturnValue( {
		activatePmPromotion: jest.fn(),
		dismissPmPromotion: jest.fn(),
	} ),
} ) );

select.mockReturnValue( {
	getSettings: () => settingsMock,
} );

const loanOfferErrorText =
	'There was a problem redirecting you to the loan offer. Please check that it is not expired and try again.';

const serverLinkErrorText =
	'There was a problem redirecting you to the requested link. Please check that it is valid and try again.';

describe( 'Overview page', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			accountStatus: {
				status: 'complete',
				paymentsEnabled: 1,
				deposits: {
					status: 'enabled',
					interval: 'weekly',
				},
			},
			accountFees: {
				base: {
					currency: 'EUR',
					percentage_rate: 0.029,
					fixed_rate: 0.3,
				},
				discount: [],
			},
			featureFlags: {
				accountOverviewTaskList: true,
			},
			accountLoans: {},
			accountDetails: {
				account_status: {
					text: 'Complete',
					background_color: 'green',
				},
				payout_status: {
					text: 'Enabled',
					background_color: 'green',
				},
				banner: null,
			},
		};
		getQuery.mockReturnValue( {} );
		getTasks.mockReturnValue( [] );
	} );

	it( 'Skips rendering task list when there are no tasks', () => {
		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.woocommerce-experimental-list' )
		).toBeNull();
	} );

	it( 'Skips rendering task list when accountOverviewTaskList feature flag is off', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			featureFlags: {},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.woocommerce-experimental-list' )
		).toBeNull();
	} );

	it( 'Displays the login error for query param wcpay-login-error=1', () => {
		getQuery.mockReturnValue( { 'wcpay-login-error': '1' } );

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector(
				'.wcpay-banner-notice.is-error.wcpay-login-error'
			)
		).toBeVisible();
	} );

	it( 'Displays the notice for Jetpack Identity Crisis', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			isJetpackIdcActive: 1,
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-jetpack-idc-notice' )
		).toBeVisible();
	} );

	it( 'Displays the server link redirection error message for query param wcpay-server-link-error=1', () => {
		getQuery.mockReturnValue( { 'wcpay-server-link-error': '1' } );
		getTasks.mockReturnValue( [] );

		render( <OverviewPage /> );

		expect(
			screen.queryByText( ( content, element ) => {
				return (
					serverLinkErrorText === content &&
					! element.classList.contains( 'a11y-speak-region' )
				);
			} )
		).toBeVisible();
	} );

	it( 'Does not display the server link redirection error message when there the query parameter is not present', () => {
		getTasks.mockReturnValue( [] );

		render( <OverviewPage /> );

		expect(
			screen.queryByText( ( content, element ) => {
				return (
					serverLinkErrorText === content &&
					! element.classList.contains( 'a11y-speak-region' )
				);
			} )
		).toBeNull();
	} );

	it( 'Displays the view loan error message for query param wcpay-loan-offer-error=1', () => {
		getQuery.mockReturnValue( { 'wcpay-loan-offer-error': '1' } );
		getTasks.mockReturnValue( [] );

		render( <OverviewPage /> );

		expect(
			screen.queryByText( ( content, element ) => {
				return (
					loanOfferErrorText === content &&
					! element.classList.contains( 'a11y-speak-region' )
				);
			} )
		).toBeVisible();
	} );

	it( 'Does not display the view loan error message when there the query parameter is not present', () => {
		getTasks.mockReturnValue( [] );

		render( <OverviewPage /> );

		expect(
			screen.queryByText( ( content, element ) => {
				return (
					loanOfferErrorText === content &&
					! element.classList.contains( 'a11y-speak-region' )
				);
			} )
		).toBeNull();
	} );

	it( 'Does not display loan summary when there is no loan', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			accountLoans: {
				has_active_loan: false,
			},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-loan-summary-header' )
		).toBeNull();
	} );

	it( 'Displays loan summary when there is a loan', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			accountLoans: {
				has_active_loan: true,
			},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-loan-summary-header' )
		).toBeVisible();
	} );

	it( 'displays ConnectionSuccessModal', () => {
		getQuery.mockReturnValue( { 'wcpay-connection-success': '1' } );

		global.wcpaySettings.testModeOnboarding = false;

		render( <OverviewPage /> );

		expect(
			screen.getByText( "You're ready to accept payments!" )
		).toBeInTheDocument();
	} );

	it( 'does not displays ConnectionSuccessModal when no query param', () => {
		const query = () =>
			screen.queryByText( "You're ready to accept payments!" );

		global.wcpaySettings.testModeOnboarding = false;

		render( <OverviewPage /> );

		expect( query() ).not.toBeInTheDocument();
	} );

	it( 'does not displays ConnectionSuccessModal when testModeOnboarding', () => {
		const query = () =>
			screen.queryByText( "You're ready to accept payments!" );
		getQuery.mockReturnValue( { 'wcpay-connection-success': '1' } );

		global.wcpaySettings.testModeOnboarding = true;

		render( <OverviewPage /> );

		expect( query() ).not.toBeInTheDocument();
	} );
} );
