/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import {
	getQuery,
	updateQueryString,
	getHistory,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { MultiCurrencySettingsPage } from '../';
import MultiCurrencySettingsContext from 'multi-currency/context';
import MultiCurrencySettings from '../settings/multi-currency';
import SingleCurrencySettings from '../settings/single-currency';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
	updateQueryString: jest.fn(),
	getHistory: jest.fn(),
} ) );

jest.mock( '../settings/multi-currency', () => jest.fn() );
jest.mock( '../settings/single-currency', () => jest.fn() );

// Captures the callback registered with getHistory().listen so tests can
// simulate the browser back/forward buttons changing the URL.
let historyListener;
let unlisten;

describe( 'Multi-currency settings page routing', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		getQuery.mockReturnValue( {} );

		unlisten = jest.fn();
		getHistory.mockReturnValue( {
			listen: ( listener ) => {
				historyListener = listener;
				return unlisten;
			},
		} );

		MultiCurrencySettings.mockImplementation( () => {
			const { setCurrencyCodeToShowSettingsFor } = useContext(
				MultiCurrencySettingsContext
			);
			return (
				<button
					onClick={ () => setCurrencyCodeToShowSettingsFor( 'USD' ) }
				>
					manage USD
				</button>
			);
		} );

		SingleCurrencySettings.mockImplementation( () => {
			const {
				currencyCodeToShowSettingsFor,
				setCurrencyCodeToShowSettingsFor,
			} = useContext( MultiCurrencySettingsContext );
			return (
				<div>
					<span>single: { currencyCodeToShowSettingsFor }</span>
					<button
						onClick={ () =>
							setCurrencyCodeToShowSettingsFor( null )
						}
					>
						back to list
					</button>
				</div>
			);
		} );
	} );

	test( 'renders the currency list when no currency is in the URL', () => {
		render( <MultiCurrencySettingsPage /> );

		expect(
			screen.getByRole( 'button', { name: 'manage USD' } )
		).toBeInTheDocument();
		expect( screen.queryByText( /^single:/ ) ).not.toBeInTheDocument();
	} );

	test( 'writes the currency to the URL when managing a currency', () => {
		render( <MultiCurrencySettingsPage /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'manage USD' } ) );

		expect( updateQueryString ).toHaveBeenCalledWith( {
			currency: 'USD',
		} );
	} );

	test( 'renders the single currency view from a deep link', () => {
		getQuery.mockReturnValue( { currency: 'EUR' } );

		render( <MultiCurrencySettingsPage /> );

		expect( screen.getByText( 'single: EUR' ) ).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'manage USD' } )
		).not.toBeInTheDocument();
	} );

	test( 'follows the URL when the browser navigates back to the list', () => {
		getQuery.mockReturnValue( { currency: 'EUR' } );

		render( <MultiCurrencySettingsPage /> );

		expect( screen.getByText( 'single: EUR' ) ).toBeInTheDocument();

		// Simulate the browser back button returning to the list URL.
		getQuery.mockReturnValue( {} );
		act( () => {
			historyListener();
		} );

		expect(
			screen.getByRole( 'button', { name: 'manage USD' } )
		).toBeInTheDocument();
		expect( screen.queryByText( /^single:/ ) ).not.toBeInTheDocument();
	} );

	test( 'strips the currency from the URL when returning to the list', () => {
		getQuery.mockReturnValue( { currency: 'EUR' } );

		render( <MultiCurrencySettingsPage /> );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'back to list' } )
		);

		expect( updateQueryString ).toHaveBeenCalledWith( {
			currency: undefined,
		} );
	} );

	test( 'stops listening to history changes when unmounted', () => {
		const { unmount } = render( <MultiCurrencySettingsPage /> );
		unmount();

		expect( unlisten ).toHaveBeenCalled();
	} );
} );
