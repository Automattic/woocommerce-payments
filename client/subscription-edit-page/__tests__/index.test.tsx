/* eslint-disable prettier/prettier */
/** @format */
/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';

/**
 * Internal dependencies
 */
import { PaymentMethodSelect } from '../index';
import UserTokenCache from '../user-token-cache';
import type { Token } from '../types';

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

	let cache: UserTokenCache;
	let mockOnChange: jest.Mock;

	beforeEach( () => {
		jest.clearAllMocks();
		cache = new UserTokenCache();
		mockOnChange = jest.fn();
	} );

	describe( 'Rendering States', () => {
		test( 'renders select with tokens', () => {
			cache.add( 1, mockTokens );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 1 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			const select = screen.getByRole( 'combobox' );
			expect( select ).toBeInTheDocument();
			expect( select ).toHaveAttribute( 'name', 'payment_method' );
			expect( select ).toHaveValue( '1' );

			mockTokens.forEach( ( token ) => {
				expect(
					screen.getByText( token.displayName )
				).toBeInTheDocument();
			} );
		} );

		test( 'renders loading state', () => {
			cache.startLoading( 1 );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 0 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			expect( screen.getByText( 'Loading…' ) ).toBeInTheDocument();
		} );

		test( 'renders error state', () => {
			cache.startLoading( 1 );
			cache.loadingFailed( 1, 'Failed to fetch user tokens' );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 0 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
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
					value={ 0 }
					userId={ 0 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'renders no customer selected message for undefined userId', () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 0 }
					userId={ undefined as any }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'renders placeholder when no tokens match value', () => {
			cache.add( 1, mockTokens );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 999 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			expect(
				screen.getByText( 'Please select a payment method' )
			).toBeInTheDocument();
		} );

		test( 'renders empty token list', () => {
			cache.add( 1, [] );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 0 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			const select = screen.getByRole( 'combobox' );
			expect( select ).toBeInTheDocument();
		} );
	} );

	describe( 'User Interaction', () => {
		test( 'calls onChange when user selects a payment method', async () => {
			cache.add( 1, mockTokens );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 1 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			const select = screen.getByRole( 'combobox' ) as HTMLSelectElement;

			await userEvent.selectOptions( select, '2' );

			expect( mockOnChange ).toHaveBeenCalledWith( 2 );
		} );

		test( 'placeholder option is disabled', () => {
			cache.add( 1, mockTokens );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 999 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
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
			cache.add( 1, mockTokens );

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 2 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			const select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			expect( select.value ).toBe( '2' );
		} );

		test( 'updates when value prop changes', () => {
			cache.add( 1, mockTokens );

			const { rerender } = render(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 1 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			let select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			expect( select.value ).toBe( '1' );

			rerender(
				<PaymentMethodSelect
					inputName="payment_method"
					value={ 2 }
					userId={ 1 }
					cache={ cache }
					onChange={ mockOnChange }
				/>
			);

			select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			expect( select.value ).toBe( '2' );
		} );
	} );
} );

describe( 'UserTokenCache', () => {
	let cache: UserTokenCache;
	const mockTokens: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: true },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678', isDefault: false },
	];

	beforeEach( () => {
		cache = new UserTokenCache();
	} );

	describe( 'add()', () => {
		test( 'adds user with tokens to cache', () => {
			cache.add( 1, mockTokens );

			expect( cache.hasEntry( 1 ) ).toBe( true );
			const entry = cache.getUserEntry( 1 );
			expect( entry ).toEqual( {
				userId: 1,
				tokens: mockTokens,
				loading: false,
				loadingError: null,
			} );
		} );

		test( 'adds user with empty tokens', () => {
			cache.add( 1, [] );

			expect( cache.hasEntry( 1 ) ).toBe( true );
			const entry = cache.getUserEntry( 1 );
			expect( entry?.tokens ).toEqual( [] );
		} );

		test( 'can add multiple users', () => {
			cache.add( 1, mockTokens );
			cache.add( 2, [] );

			expect( cache.hasEntry( 1 ) ).toBe( true );
			expect( cache.hasEntry( 2 ) ).toBe( true );
		} );
	} );

	describe( 'startLoading()', () => {
		test( 'adds user in loading state', () => {
			cache.startLoading( 1 );

			const entry = cache.getUserEntry( 1 );
			expect( entry ).toEqual( {
				userId: 1,
				loading: true,
				loadingError: null,
				tokens: [],
			} );
		} );

		test( 'adds loading state for new user', () => {
			cache.add( 1, mockTokens );
			cache.startLoading( 2 );

			expect( cache.hasEntry( 1 ) ).toBe( true );
			expect( cache.hasEntry( 2 ) ).toBe( true );
			expect( cache.getUserEntry( 2 )?.loading ).toBe( true );
		} );
	} );

	describe( 'tokensLoaded()', () => {
		test( 'updates loading entry with tokens', () => {
			cache.startLoading( 1 );
			cache.tokensLoaded( 1, mockTokens );

			const entry = cache.getUserEntry( 1 );
			expect( entry ).toEqual( {
				userId: 1,
				tokens: mockTokens,
				loading: false,
				loadingError: null,
			} );
		} );

		test( 'does not affect other users', () => {
			cache.add( 1, mockTokens );
			cache.startLoading( 2 );
			cache.tokensLoaded( 2, [] );

			const user1Entry = cache.getUserEntry( 1 );
			expect( user1Entry?.tokens ).toEqual( mockTokens );
		} );

		test( 'clears loading state', () => {
			cache.startLoading( 1 );
			expect( cache.getUserEntry( 1 )?.loading ).toBe( true );

			cache.tokensLoaded( 1, mockTokens );
			expect( cache.getUserEntry( 1 )?.loading ).toBe( false );
		} );
	} );

	describe( 'loadingFailed()', () => {
		test( 'sets error message', () => {
			cache.startLoading( 1 );
			cache.loadingFailed( 1, 'Network error' );

			const entry = cache.getUserEntry( 1 );
			expect( entry ).toEqual( {
				userId: 1,
				tokens: [],
				loading: false,
				loadingError: 'Network error',
			} );
		} );

		test( 'clears loading state', () => {
			cache.startLoading( 1 );
			cache.loadingFailed( 1, 'Error' );

			expect( cache.getUserEntry( 1 )?.loading ).toBe( false );
		} );

		test( 'preserves existing tokens', () => {
			cache.startLoading( 1 );
			cache.loadingFailed( 1, 'Error' );

			expect( cache.getUserEntry( 1 )?.tokens ).toEqual( [] );
		} );
	} );

	describe( 'hasEntry()', () => {
		test( 'returns true when user exists', () => {
			cache.add( 1, mockTokens );

			expect( cache.hasEntry( 1 ) ).toBe( true );
		} );

		test( 'returns false when user does not exist', () => {
			expect( cache.hasEntry( 999 ) ).toBe( false );
		} );

		test( 'returns true for loading entries', () => {
			cache.startLoading( 1 );

			expect( cache.hasEntry( 1 ) ).toBe( true );
		} );
	} );

	describe( 'getUserEntry()', () => {
		test( 'returns user entry when exists', () => {
			cache.add( 1, mockTokens );

			const entry = cache.getUserEntry( 1 );
			expect( entry?.userId ).toBe( 1 );
			expect( entry?.tokens ).toEqual( mockTokens );
		} );

		test( 'returns undefined when user does not exist', () => {
			const entry = cache.getUserEntry( 999 );

			expect( entry ).toBeUndefined();
		} );
	} );

	describe( 'userHasToken()', () => {
		test( 'returns true when user has token', () => {
			cache.add( 1, mockTokens );

			expect( cache.userHasToken( 1, 1 ) ).toBe( true );
			expect( cache.userHasToken( 1, 2 ) ).toBe( true );
		} );

		test( 'returns false when user does not have token', () => {
			cache.add( 1, mockTokens );

			expect( cache.userHasToken( 1, 999 ) ).toBe( false );
		} );

		test( 'returns false when user does not exist', () => {
			expect( cache.userHasToken( 999, 1 ) ).toBe( false );
		} );

		test( 'returns false for user with empty tokens', () => {
			cache.add( 1, [] );

			expect( cache.userHasToken( 1, 1 ) ).toBe( false );
		} );
	} );

	describe( 'subscribe()', () => {
		test( 'calls subscriber when cache updates', () => {
			const subscriber = jest.fn();
			cache.subscribe( subscriber );

			cache.add( 1, mockTokens );

			expect( subscriber ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'calls all subscribers on update', () => {
			const subscriber1 = jest.fn();
			const subscriber2 = jest.fn();

			cache.subscribe( subscriber1 );
			cache.subscribe( subscriber2 );

			cache.add( 1, mockTokens );

			expect( subscriber1 ).toHaveBeenCalledTimes( 1 );
			expect( subscriber2 ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'calls subscribers on startLoading', () => {
			const subscriber = jest.fn();
			cache.subscribe( subscriber );

			cache.startLoading( 1 );

			expect( subscriber ).toHaveBeenCalled();
		} );

		test( 'calls subscribers on tokensLoaded', () => {
			const subscriber = jest.fn();
			cache.subscribe( subscriber );

			cache.startLoading( 1 );
			subscriber.mockClear();

			cache.tokensLoaded( 1, mockTokens );

			expect( subscriber ).toHaveBeenCalled();
		} );

		test( 'calls subscribers on loadingFailed', () => {
			const subscriber = jest.fn();
			cache.subscribe( subscriber );

			cache.startLoading( 1 );
			subscriber.mockClear();

			cache.loadingFailed( 1, 'Error' );

			expect( subscriber ).toHaveBeenCalled();
		} );
	} );

	describe( 'getCache()', () => {
		test( 'returns current cache state', () => {
			cache.add( 1, mockTokens );
			cache.add( 2, [] );

			const cacheState = cache.getCache();

			expect( cacheState ).toHaveLength( 2 );
			expect( cacheState[ 0 ].userId ).toBe( 1 );
			expect( cacheState[ 1 ].userId ).toBe( 2 );
		} );

		test( 'returns empty array for new cache', () => {
			const cacheState = cache.getCache();

			expect( cacheState ).toEqual( [] );
		} );
	} );
} );
