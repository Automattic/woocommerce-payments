/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Deposits from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';
import {
	useDepositStatus,
	useDepositRestrictions,
	useCompletedWaitingPeriod,
	useDepositScheduleInterval,
	useDepositScheduleWeeklyAnchor,
	useDepositScheduleMonthlyAnchor,
	useAllDepositsOverviews,
} from 'wcpay/data';

jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(), // Add this line
		onHistoryChange: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
	useDepositStatus: jest.fn(),
	useDepositRestrictions: jest.fn(),
	useCompletedWaitingPeriod: jest.fn(),
	useDepositScheduleInterval: jest.fn(),
	useDepositScheduleWeeklyAnchor: jest.fn(),
	useDepositScheduleMonthlyAnchor: jest.fn(),
	useAllDepositsOverviews: jest.fn(),
} ) );

describe( 'Deposits', () => {
	const settingsContext = {
		accountStatus: { accountLink: '/account-link' },
	};

	beforeEach( () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useDepositRestrictions.mockReturnValue( 'deposits_unrestricted' );
		useCompletedWaitingPeriod.mockReturnValue( true );
		useDepositScheduleInterval.mockReturnValue( [ 'daily', jest.fn() ] );
		useDepositScheduleMonthlyAnchor.mockReturnValue( [ '1', jest.fn() ] );
		useDepositScheduleWeeklyAnchor.mockReturnValue( [
			'monday',
			jest.fn(),
		] );
		select.mockImplementation( () => ( {
			getSettings: jest.fn().mockReturnValue( {
				account_country: 'US',
			} ),
		} ) );
		useAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: {
					default_external_accounts: [
						{
							currency: 'usd',
							status: 'enabled',
						},
					],
				},
			},
		} );
	} );

	it( 'renders', () => {
		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const manageLink = screen.getByText( /Manage in Stripe/ );
		expect( manageLink ).toBeInTheDocument();
		expect( manageLink ).toHaveTextContent(
			'Manage in Stripe(opens in a new tab)'
		);
		expect( manageLink.href ).toContain( '/account-link' );
	} );

	it( 'renders the deposits blocked message', () => {
		useDepositStatus.mockReturnValue( 'blocked' );
		useCompletedWaitingPeriod.mockReturnValue( true );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Deposit scheduling is currently unavailable for your store/,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );

	it( 'renders the deposits blocked message when deposits are not blocked, but restricted', () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useDepositRestrictions.mockReturnValue( 'schedule_restricted' );
		useCompletedWaitingPeriod.mockReturnValue( true );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Deposit scheduling is currently unavailable for your store/,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );

	it( 'renders the deposits blocked message for an unkown deposit status', () => {
		useDepositStatus.mockReturnValue( 'asdf' );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Deposit scheduling is currently unavailable for your store/,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );

	it( 'renders the deposits within waiting period message', () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useCompletedWaitingPeriod.mockReturnValue( false );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Your first deposit will be held for/,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );

	it( `doesn't render the deposits within waiting period message for accounts not within waiting period`, () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useCompletedWaitingPeriod.mockReturnValue( true );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText( /Your first deposit will be held for/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeFalsy();
	} );

	it( 'renders the frequency select', () => {
		useDepositScheduleInterval.mockReturnValue( [ 'daily', jest.fn() ] );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const frequencySelect = screen.getByLabelText( /Frequency/ );
		expect( frequencySelect ).toHaveValue( 'daily' );

		within( frequencySelect ).getByRole( 'option', { name: /Daily/ } );
		within( frequencySelect ).getByRole( 'option', { name: /Weekly/ } );
		within( frequencySelect ).getByRole( 'option', { name: /Monthly/ } );
	} );

	it( 'renders the frequency select without daily for Japan', () => {
		useDepositScheduleInterval.mockReturnValue( [ 'daily', jest.fn() ] );

		select.mockImplementation( () => ( {
			getSettings: jest.fn().mockReturnValue( {
				account_country: 'JP',
			} ),
		} ) );
		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const frequencySelect = screen.getByLabelText( /Frequency/ );
		expect( frequencySelect ).toHaveValue( 'weekly' );

		within( frequencySelect ).getByRole( 'option', { name: /Weekly/ } );
		within( frequencySelect ).getByRole( 'option', { name: /Monthly/ } );
	} );

	it( 'renders the weekly offset select', () => {
		useDepositScheduleInterval.mockReturnValue( [ 'weekly', jest.fn() ] );
		useDepositScheduleWeeklyAnchor.mockReturnValue( [
			'friday',
			jest.fn(),
		] );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const frequencySelect = screen.getByLabelText( /Frequency/ );
		expect( frequencySelect ).toHaveValue( 'weekly' );

		const weeklyAnchorSelect = screen.getByLabelText( /Day/ );
		expect( weeklyAnchorSelect ).toHaveValue( 'friday' );

		// Weekdays only
		const weeklyAnchors = [
			/monday/i,
			/tuesday/i,
			/wednesday/i,
			/thursday/i,
			/friday/i,
		];
		for ( const anchor of weeklyAnchors ) {
			within( weeklyAnchorSelect ).getByRole( 'option', {
				name: anchor,
			} );
		}
	} );

	it( 'renders the deposit failure notice if there is an errored bank account', () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useCompletedWaitingPeriod.mockReturnValue( true );
		useAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: {
					default_external_accounts: [
						{
							currency: 'usd',
							status: 'errored',
						},
					],
				},
			},
		} );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Deposits are currently paused because a recent deposit failed./,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();

		expect(
			screen.queryByText(
				/Manage and update your deposit account information to receive payments and deposits./,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeFalsy();
	} );

	it( 'does not render the deposit failure notice if there is no errored bank account', () => {
		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText(
				/Deposits are currently paused because a recent deposit failed./,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeFalsy();

		const depositsMessage = screen.getByText(
			/Manage and update your deposit account information to receive payments and deposits./,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );

	it( 'renders deposit failure notice with at least one errored default account in multicurrency', () => {
		useAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: {
					default_external_accounts: [
						{
							currency: 'usd',
							status: 'errored',
						},
						{
							currency: 'eur',
							status: 'enabled',
						},
					],
				},
			},
		} );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const depositsMessage = screen.getByText(
			/Deposits are currently paused because a recent deposit failed./,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();

		expect(
			screen.queryByText(
				/Manage and update your deposit account information to receive payments and deposits./,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeFalsy();
	} );

	it( 'renders deposit failure notice with all enabled default accounts in multicurrency', () => {
		useAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: {
					default_external_accounts: [
						{
							currency: 'usd',
							status: 'enabled',
						},
						{
							currency: 'eur',
							status: 'enabled',
						},
					],
				},
			},
		} );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText(
				/Deposits are currently paused because a recent deposit failed./,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeFalsy();

		const depositsMessage = screen.getByText(
			/Manage and update your deposit account information to receive payments and deposits./,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
	} );
} );
