/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';

/**
 * Internal dependencies
 */
import {
	PaymentMethodSelect,
	generateInitialCache,
	addLoadingState,
	userTokensLoaded,
	userTokensLoadingFailed,
	userHasEntryInCache,
	getUserTokensFromCache,
	userHasToken,
} from '../index';
import type { Token } from '../types';

// Mock jQuery
const mockJQuery = jest.fn();
const mockOn = jest.fn();
const mockOff = jest.fn();
( global as any ).jQuery = mockJQuery;
mockJQuery.mockReturnValue( { on: mockOn, off: mockOff } );

// Mock fetch
global.fetch = jest.fn();

// Mock @wordpress/i18n
jest.mock( '@wordpress/i18n', () => ( {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	__: ( text: string ) => text,
} ) );

describe( 'PaymentMethodSelect', () => {
	const mockTokens: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234' },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678' },
	];

	beforeEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
	} );

	describe( 'Select Rendering', () => {
		test( 'renders select with tokens', () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 1 }
					initialUser={ 1 }
					tokens={ mockTokens }
					ajaxUrl="/ajax"
					nonce="nonce"
				/>
			);

			expect( screen.getByRole( 'combobox' ) ).toBeInTheDocument();
			expect( screen.getByText( 'Visa •••• 1234' ) ).toBeInTheDocument();
			expect(
				screen.getByText( 'Mastercard •••• 5678' )
			).toBeInTheDocument();
		} );

		test( 'renders with no customer selected message', async () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUser={ undefined }
					tokens={ [] }
					ajaxUrl="/ajax"
					nonce="nonce"
				/>
			);

			expect(
				await screen.findByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'allows selecting a payment method', async () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 1 }
					initialUser={ 1 }
					tokens={ mockTokens }
					ajaxUrl="/ajax"
					nonce="nonce"
				/>
			);

			const select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			await userEvent.selectOptions( select, '2' );

			expect( select.value ).toBe( '2' );
		} );
	} );

	describe( 'Customer Select Listener', () => {
		test( 'sets up event listeners when customer select exists', () => {
			const customerSelect = document.createElement( 'select' );
			customerSelect.id = 'customer_user';
			customerSelect.value = '1';
			document.body.appendChild( customerSelect );

			const addEventListenerSpy = jest.spyOn(
				customerSelect,
				'addEventListener'
			);

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUser={ 1 }
					tokens={ [] }
					ajaxUrl="/ajax"
					nonce="nonce"
				/>
			);

			// Verify change listener
			expect( addEventListenerSpy ).toHaveBeenCalledWith(
				'change',
				expect.any( Function )
			);

			// Verify jQuery select2 listener
			expect( mockJQuery ).toHaveBeenCalledWith( customerSelect );
			expect( mockOn ).toHaveBeenCalledWith(
				'select2:select',
				expect.any( Function )
			);
		} );

		test( 'does not throw when customer select is missing', () => {
			expect( () => {
				render(
					<PaymentMethodSelect
						inputName="payment_method"
						initialValue={ 0 }
						initialUser={ 1 }
						tokens={ [] }
						ajaxUrl="/ajax"
						nonce="nonce"
					/>
				);
			} ).not.toThrow();
		} );
	} );

	describe( 'Cache Functions', () => {
		describe( 'generateInitialCache', () => {
			test( 'creates cache with initial user and tokens', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( cache ).toHaveLength( 1 );
				expect( cache[ 0 ] ).toEqual( {
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				} );
			} );

			test( 'creates empty cache when no user provided', () => {
				const cache = generateInitialCache( undefined, [] );

				expect( cache ).toHaveLength( 0 );
			} );
		} );

		describe( 'addLoadingState', () => {
			test( 'adds loading entry for new user', () => {
				const initialCache = generateInitialCache( 1, mockTokens );
				const updatedCache = addLoadingState( initialCache, 2 );

				expect( updatedCache ).toHaveLength( 2 );
				expect( updatedCache[ 1 ] ).toEqual( {
					userId: 2,
					loading: true,
					loadingError: null,
					tokens: [],
				} );
			} );
		} );

		describe( 'userTokensLoaded', () => {
			test( 'updates cache with loaded tokens', () => {
				const initialCache = generateInitialCache( 1, [] );
				const loadingCache = addLoadingState( initialCache, 2 );
				const updatedCache = userTokensLoaded(
					loadingCache,
					2,
					mockTokens
				);

				const user2Entry = updatedCache.find(
					( entry ) => entry.userId === 2
				);
				expect( user2Entry ).toEqual( {
					userId: 2,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				} );
			} );

			test( 'does not affect other users in cache', () => {
				const initialCache = generateInitialCache( 1, mockTokens );
				const loadingCache = addLoadingState( initialCache, 2 );
				const updatedCache = userTokensLoaded( loadingCache, 2, [] );

				const user1Entry = updatedCache.find(
					( entry ) => entry.userId === 1
				);
				expect( user1Entry?.tokens ).toEqual( mockTokens );
			} );
		} );

		describe( 'userTokensLoadingFailed', () => {
			test( 'sets error message for user', () => {
				const initialCache = generateInitialCache( 1, [] );
				const loadingCache = addLoadingState( initialCache, 2 );
				const errorCache = userTokensLoadingFailed(
					loadingCache,
					2,
					'Failed to load'
				);

				const user2Entry = errorCache.find(
					( entry ) => entry.userId === 2
				);
				expect( user2Entry ).toEqual( {
					userId: 2,
					tokens: [],
					loading: false,
					loadingError: 'Failed to load',
				} );
			} );
		} );

		describe( 'userHasEntryInCache', () => {
			test( 'returns true when user exists in cache', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( userHasEntryInCache( cache, 1 ) ).toBe( true );
			} );

			test( 'returns false when user not in cache', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( userHasEntryInCache( cache, 2 ) ).toBe( false );
			} );
		} );

		describe( 'getUserTokensFromCache', () => {
			test( 'returns tokens for user in cache', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( getUserTokensFromCache( cache, 1 ) ).toEqual(
					mockTokens
				);
			} );

			test( 'returns empty array when user not in cache', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( getUserTokensFromCache( cache, 2 ) ).toEqual( [] );
			} );
		} );

		describe( 'userHasToken', () => {
			test( 'returns true when user has token', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( userHasToken( cache, 1, 1 ) ).toBe( true );
				expect( userHasToken( cache, 1, 2 ) ).toBe( true );
			} );

			test( 'returns false when user does not have token', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( userHasToken( cache, 1, 999 ) ).toBe( false );
			} );

			test( 'returns false when user not in cache', () => {
				const cache = generateInitialCache( 1, mockTokens );

				expect( userHasToken( cache, 2, 1 ) ).toBe( false );
			} );
		} );
	} );
} );
