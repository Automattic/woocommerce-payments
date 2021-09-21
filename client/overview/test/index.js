/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

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
			},
		};
		getQuery.mockReturnValue( {} );
	} );

	it( 'Skips rendering task list when there are no tasks', () => {
		getTasks.mockReturnValue( [] );
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

		getTasks.mockReturnValue( [] );
		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.woocommerce-experimental-list' )
		).toBeNull();
	} );

	it( 'Displays the login error for query param wcpay-login-error=1', () => {
		getQuery.mockReturnValue( { 'wcpay-login-error': '1' } );
		getTasks.mockReturnValue( [] );

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector(
				'.wcpay-login-error.components-notice.is-error'
			)
		).toBeVisible();
	} );

	it( 'Displays the success message for query param wcpay-connection-success=1', () => {
		getQuery.mockReturnValue( { 'wcpay-connection-success': '1' } );
		getTasks.mockReturnValue( [] );

		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector(
				'.wcpay-connection-success.components-notice.is-success'
			)
		).toBeVisible();
	} );
} );
