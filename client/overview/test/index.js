/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OverviewPage from '../';
import { getTasks } from '../task-list/tasks';
import { getQuery } from '@woocommerce/navigation';

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
jest.mock( '@woocommerce/navigation', () => ( { getQuery: jest.fn() } ) );

const loanOfferErrorText =
	'There was a problem redirecting you to the loan offer. Please check that it is not expired and try again.';

describe( 'Overview page', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			accountStatus: {
				status: 'complete',
				paymentsEnabled: 1,
				depositsStatus: 'weekly',
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
				capital: true,
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
				'.wcpay-login-error.components-notice.is-error'
			)
		).toBeVisible();
	} );

	it( 'Displays the success message for query param wcpay-connection-success=1', () => {
		getQuery.mockReturnValue( { 'wcpay-connection-success': '1' } );

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector(
				'.wcpay-connection-success.components-notice.is-success'
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

	it( 'Does not display the view loan error message when Capital is disabled', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			featureFlags: {},
		};

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
		).toBeNull();
	} );

	it( 'Does not display loan summary when Capital is disabled', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			accountStatus: {
				...global.wcpaySettings.accountStatus,
				hasActiveLoan: true,
			},
			featureFlags: {},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-loan-summary-header' )
		).toBeNull();
	} );

	it( 'Does not display loan summary when there is no loan', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			accountStatus: {
				...global.wcpaySettings.accountStatus,
				hasActiveLoan: false,
			},
			featureFlags: {
				capital: true,
			},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-loan-summary-header' )
		).toBeNull();
	} );

	it( 'Displays loan summary when there is a loan and Capital is enabled', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			accountStatus: {
				...global.wcpaySettings.accountStatus,
				hasActiveLoan: true,
			},
			featureFlags: {
				capital: true,
			},
		};

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.wcpay-loan-summary-header' )
		).toBeVisible();
	} );
} );
