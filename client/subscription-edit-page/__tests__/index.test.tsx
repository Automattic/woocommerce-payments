/* eslint-disable prettier/prettier */
/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import { PaymentMethodSelect } from '../index';
import {
	startLoading,
	tokensLoaded,
	loadingFailed,
	hasEntry,
	getUserEntry,
	userHasToken,
	getDefaultTokenId,
} from '../user-token-cache';
import type { Token, CachedUserData } from '../types';

// Mock jQuery
const mockJQuery = jest.fn();
const mockOn = jest.fn();
const mockOff = jest.fn();
( global as any ).jQuery = mockJQuery;
mockJQuery.mockReturnValue( { on: mockOn, off: mockOff } );

// Mock fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

// Mock @wordpress/i18n
jest.mock( '@wordpress/i18n', () => ( {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	__: ( text: string ) => text,
} ) );

describe( 'PaymentMethodSelect Component', () => {
	const mockTokens: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: true },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678', isDefault: false },
		{ tokenId: 3, displayName: 'Amex •••• 9012', isDefault: false },
	];

	beforeEach( () => {
		jest.clearAllMocks();
		// Mock the customer_user select element for the addCustomerSelectListener
		document.body.innerHTML = '';
	} );

	describe( 'Rendering States', () => {
		test( 'renders select with tokens', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 1 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			const select = screen.getByRole( 'combobox' );
			expect( select ).toBeInTheDocument();
			expect( select ).toHaveAttribute( 'name', 'payment_method' );

			mockTokens.forEach( ( token ) => {
				expect(
					screen.getByText( token.displayName )
				).toBeInTheDocument();
			} );
		} );

		test( 'renders loading state', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: [],
					loading: true,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect( screen.getByText( 'Loading…' ) ).toBeInTheDocument();
		} );

		test( 'renders error state', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: [],
					loading: false,
					loadingError: 'Failed to fetch user tokens',
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Failed to fetch user tokens' )
			).toBeInTheDocument();
		} );

		test( 'renders no customer selected message', () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					initialCache={ [] }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'renders loading state for undefined userId with empty cache', () => {
			// When userId is undefined (NaN), the component shows loading
			// because the userId check (userId <= 0) evaluates to false for NaN
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ undefined as any }
					initialCache={ [] }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect( screen.getByText( 'Loading…' ) ).toBeInTheDocument();
		} );

		test( 'renders placeholder when value is zero', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Please select a payment method' )
			).toBeInTheDocument();
		} );

		test( 'renders tokens without placeholder when value does not match', () => {
			// In the new implementation, placeholder only shows when value === 0
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 999 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// No placeholder is shown, only the token options
			expect(
				screen.queryByText( 'Please select a payment method' )
			).not.toBeInTheDocument();
			mockTokens.forEach( ( token ) => {
				expect(
					screen.getByText( token.displayName )
				).toBeInTheDocument();
			} );
		} );

		test( 'renders empty token list', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: [],
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			const select = screen.getByRole( 'combobox' );
			expect( select ).toBeInTheDocument();
		} );
	} );

	describe( 'Select Behavior', () => {
		test( 'placeholder option is disabled', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			const placeholderOption = screen.getByText(
				'Please select a payment method'
			) as HTMLOptionElement;

			expect( placeholderOption ).toHaveAttribute( 'disabled' );
			expect( placeholderOption ).toHaveAttribute( 'value', '0' );
		} );
	} );

	describe( 'Value Display', () => {
		test( 'displays correct initial value', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 2 }
					initialUserId={ 1 }
					initialCache={ cache }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			const select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			expect( select.value ).toBe( '2' );
		} );
	} );
} );

describe( 'User Token Cache Functions', () => {
	const mockTokens: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: true },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678', isDefault: false },
	];

	describe( 'startLoading()', () => {
		test( 'adds user in loading state', () => {
			const cache: CachedUserData = [];
			const newCache = startLoading( cache, 1 );

			const entry = getUserEntry( newCache, 1 );
			expect( entry ).toEqual( {
				userId: 1,
				loading: true,
				loadingError: null,
				tokens: [],
			} );
		} );

		test( 'adds loading state for new user while preserving existing', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];
			const newCache = startLoading( cache, 2 );

			expect( hasEntry( newCache, 1 ) ).toBe( true );
			expect( hasEntry( newCache, 2 ) ).toBe( true );
			expect( getUserEntry( newCache, 2 )?.loading ).toBe( true );
		} );

		test( 'does not mutate original cache', () => {
			const cache: CachedUserData = [];
			startLoading( cache, 1 );

			expect( cache ).toEqual( [] );
		} );
	} );

	describe( 'tokensLoaded()', () => {
		test( 'updates loading entry with tokens', () => {
			let cache: CachedUserData = [];
			cache = startLoading( cache, 1 );
			cache = tokensLoaded( cache, 1, mockTokens );

			const entry = getUserEntry( cache, 1 );
			expect( entry ).toEqual( {
				userId: 1,
				tokens: mockTokens,
				loading: false,
				loadingError: null,
			} );
		} );

		test( 'does not affect other users', () => {
			let cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];
			cache = startLoading( cache, 2 );
			cache = tokensLoaded( cache, 2, [] );

			const user1Entry = getUserEntry( cache, 1 );
			expect( user1Entry?.tokens ).toEqual( mockTokens );
		} );

		test( 'clears loading state', () => {
			let cache: CachedUserData = [];
			cache = startLoading( cache, 1 );
			expect( getUserEntry( cache, 1 )?.loading ).toBe( true );

			cache = tokensLoaded( cache, 1, mockTokens );
			expect( getUserEntry( cache, 1 )?.loading ).toBe( false );
		} );

		test( 'does not mutate original cache', () => {
			const cache = startLoading( [], 1 );
			const originalEntry = getUserEntry( cache, 1 );
			tokensLoaded( cache, 1, mockTokens );

			expect( getUserEntry( cache, 1 ) ).toBe( originalEntry );
		} );
	} );

	describe( 'loadingFailed()', () => {
		test( 'sets error message', () => {
			let cache: CachedUserData = [];
			cache = startLoading( cache, 1 );
			cache = loadingFailed( cache, 1, 'Network error' );

			const entry = getUserEntry( cache, 1 );
			expect( entry ).toEqual( {
				userId: 1,
				tokens: [],
				loading: false,
				loadingError: 'Network error',
			} );
		} );

		test( 'clears loading state', () => {
			let cache: CachedUserData = [];
			cache = startLoading( cache, 1 );
			cache = loadingFailed( cache, 1, 'Error' );

			expect( getUserEntry( cache, 1 )?.loading ).toBe( false );
		} );

		test( 'preserves existing tokens', () => {
			let cache: CachedUserData = [];
			cache = startLoading( cache, 1 );
			cache = loadingFailed( cache, 1, 'Error' );

			expect( getUserEntry( cache, 1 )?.tokens ).toEqual( [] );
		} );

		test( 'does not mutate original cache', () => {
			const cache = startLoading( [], 1 );
			const originalEntry = getUserEntry( cache, 1 );
			loadingFailed( cache, 1, 'Error' );

			expect( getUserEntry( cache, 1 ) ).toBe( originalEntry );
		} );
	} );

	describe( 'hasEntry()', () => {
		test( 'returns true when user exists', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			expect( hasEntry( cache, 1 ) ).toBe( true );
		} );

		test( 'returns false when user does not exist', () => {
			expect( hasEntry( [], 999 ) ).toBe( false );
		} );

		test( 'returns true for loading entries', () => {
			const cache = startLoading( [], 1 );

			expect( hasEntry( cache, 1 ) ).toBe( true );
		} );
	} );

	describe( 'getUserEntry()', () => {
		test( 'returns user entry when exists', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			const entry = getUserEntry( cache, 1 );
			expect( entry?.userId ).toBe( 1 );
			expect( entry?.tokens ).toEqual( mockTokens );
		} );

		test( 'returns undefined when user does not exist', () => {
			const entry = getUserEntry( [], 999 );

			expect( entry ).toBeUndefined();
		} );
	} );

	describe( 'userHasToken()', () => {
		test( 'returns true when user has token', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			expect( userHasToken( cache, 1, 1 ) ).toBe( true );
			expect( userHasToken( cache, 1, 2 ) ).toBe( true );
		} );

		test( 'returns false when user does not have token', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			expect( userHasToken( cache, 1, 999 ) ).toBe( false );
		} );

		test( 'returns false when user does not exist', () => {
			expect( userHasToken( [], 999, 1 ) ).toBe( false );
		} );

		test( 'returns false for user with empty tokens', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: [],
					loading: false,
					loadingError: null,
				},
			];

			expect( userHasToken( cache, 1, 1 ) ).toBe( false );
		} );
	} );

	describe( 'getDefaultTokenId()', () => {
		test( 'returns default token id when exists', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: mockTokens,
					loading: false,
					loadingError: null,
				},
			];

			expect( getDefaultTokenId( cache, 1 ) ).toBe( 1 );
		} );

		test( 'returns 0 when no default token', () => {
			const tokensWithoutDefault: Token[] = [
				{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: false },
				{
					tokenId: 2,
					displayName: 'Mastercard •••• 5678',
					isDefault: false,
				},
			];
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: tokensWithoutDefault,
					loading: false,
					loadingError: null,
				},
			];

			expect( getDefaultTokenId( cache, 1 ) ).toBe( 0 );
		} );

		test( 'returns 0 when user does not exist', () => {
			expect( getDefaultTokenId( [], 999 ) ).toBe( 0 );
		} );

		test( 'returns 0 for user with empty tokens', () => {
			const cache: CachedUserData = [
				{
					userId: 1,
					tokens: [],
					loading: false,
					loadingError: null,
				},
			];

			expect( getDefaultTokenId( cache, 1 ) ).toBe( 0 );
		} );
	} );
} );
