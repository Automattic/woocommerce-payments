/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Deposits from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';
import {
	useDepositStatus,
	useCompletedWaitingPeriod,
	useDepositScheduleInterval,
	useDepositScheduleWeeklyAnchor,
	useDepositScheduleMonthlyAnchor,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
	useDepositStatus: jest.fn(),
	useCompletedWaitingPeriod: jest.fn(),
	useDepositScheduleInterval: jest.fn(),
	useDepositScheduleWeeklyAnchor: jest.fn(),
	useDepositScheduleMonthlyAnchor: jest.fn(),
} ) );

describe( 'Deposits', () => {
	const settingsContext = {
		accountStatus: { accountLink: '/account-link' },
	};

	beforeEach( () => {
		useDepositStatus.mockReturnValue( 'enabled' );
		useCompletedWaitingPeriod.mockReturnValue( true );
		useDepositScheduleInterval.mockReturnValue( [ 'daily', jest.fn() ] );
		useDepositScheduleMonthlyAnchor.mockReturnValue( [ '1', jest.fn() ] );
		useDepositScheduleWeeklyAnchor.mockReturnValue( [
			'monday',
			jest.fn(),
		] );
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
			/Your first deposit will be held for [0-9]+ days. Deposit scheduling will be available after this period./,
			{
				ignore: '.a11y-speak-region',
			}
		);
		expect( depositsMessage ).toBeInTheDocument();
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

	it( 'renders the monthly offset select', () => {
		useDepositScheduleInterval.mockReturnValue( [ 'monthly', jest.fn() ] );
		useDepositScheduleMonthlyAnchor.mockReturnValue( [ 14, jest.fn() ] );

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const frequencySelect = screen.getByLabelText( /Frequency/ );
		expect( frequencySelect ).toHaveValue( 'monthly' );

		const monthlyAnchorSelect = screen.getByLabelText( /Date/ );
		expect( monthlyAnchorSelect ).toHaveValue( '14' );

		const monthlyAnchors = [ /^1st/i, /^28th/i, /Last day of the month/i ];
		for ( const anchor of monthlyAnchors ) {
			within( monthlyAnchorSelect ).getByRole( 'option', {
				name: anchor,
			} );
		}
	} );
} );
