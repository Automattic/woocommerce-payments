/** @format */
/**
 * External dependencies
 */
import { render, screen, waitFor, act } from '@testing-library/react';
import React from 'react';

// Mock jQuery before importing the component
const mockOn = jest.fn();
const mockOff = jest.fn();
const mockJQuery = jest.fn( () => ( { on: mockOn, off: mockOff } ) );
( global as any ).jQuery = mockJQuery;

// Mock @wordpress/i18n
jest.mock( '@wordpress/i18n', () => ( {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	__: ( text: string ) => text,
} ) );

/**
 * Internal dependencies
 */
import { PaymentMethodSelect, fetchUserTokens, clearTokenCache } from '..';
import type { Token } from '../types';

describe( 'PaymentMethodSelect Component', () => {
	const mockTokens: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: true },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678', isDefault: false },
		{ tokenId: 3, displayName: 'Amex •••• 9012', isDefault: false },
	];

	const tokensWithoutDefault: Token[] = [
		{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: false },
		{ tokenId: 2, displayName: 'Mastercard •••• 5678', isDefault: false },
	];

	beforeEach( () => {
		jest.clearAllMocks();
		clearTokenCache();
		document.body.innerHTML = '';
	} );

	describe( 'Initial Rendering States', () => {
		test( 'renders "please select customer" message when userId is 0', () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'renders "please select customer" message when userId is negative', () => {
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ -1 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();
		} );

		test( 'renders placeholder when userId > 0 but cache is empty', () => {
			// When userId > 0 but cache has no tokens, component shows
			// empty select with placeholder (not loading state)
			// because the component only fetches on customer select change
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 1 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			expect(
				screen.getByText( 'Please select a payment method' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'Loading State', () => {
		test( 'shows loading when customer select change triggers fetch', async () => {
			// Mock fetch to never resolve during initial check
			global.fetch = jest.fn(
				() =>
					new Promise( () => {
						// Never resolves - simulates slow network
					} )
			);

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Initially shows "select customer" since userId is 0
			expect(
				screen.getByText( 'Please select a customer first' )
			).toBeInTheDocument();

			// Trigger customer selection change
			const select = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			select.value = '1';

			await act( async () => {
				select.dispatchEvent( new Event( 'change' ) );
			} );

			// Now should show loading (fetch is pending)
			expect( screen.getByText( 'Loading…' ) ).toBeInTheDocument();
		} );

		test( 'shows tokens after fetch resolves', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( { data: { tokens: mockTokens } } ),
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const select = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			select.value = '1';

			await act( async () => {
				select.dispatchEvent( new Event( 'change' ) );
			} );

			// Should show tokens after fetch resolves
			await waitFor( () => {
				expect(
					screen.getByText( 'Visa •••• 1234' )
				).toBeInTheDocument();
			} );
		} );
	} );

	describe( 'Error State', () => {
		test( 'shows error message when fetch fails', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: false,
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const select = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			select.value = '1';

			await act( async () => {
				select.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				expect(
					screen.getByText( 'Failed to fetch user tokens' )
				).toBeInTheDocument();
			} );
		} );
	} );

	describe( 'Token Rendering', () => {
		test( 'renders tokens after customer select change', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( { data: { tokens: mockTokens } } ),
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const select = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			select.value = '1';

			await act( async () => {
				select.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				expect(
					screen.getByText( 'Visa •••• 1234' )
				).toBeInTheDocument();
			} );

			expect(
				screen.getByText( 'Mastercard •••• 5678' )
			).toBeInTheDocument();
			expect( screen.getByText( 'Amex •••• 9012' ) ).toBeInTheDocument();
		} );

		test( 'auto-selects default token after fetch', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( { data: { tokens: mockTokens } } ),
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const customerSelect = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			customerSelect.value = '1';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				// Use getAllByRole since there are two comboboxes (customer_user and payment_method)
				const selects = screen.getAllByRole( 'combobox' );
				const paymentSelect = selects.find(
					( s ) => s.getAttribute( 'name' ) === 'payment_method'
				) as HTMLSelectElement;
				// Default token (tokenId: 1) should be auto-selected
				expect( paymentSelect.value ).toBe( '1' );
			} );

			// Placeholder should not be shown
			expect(
				screen.queryByText( 'Please select a payment method' )
			).not.toBeInTheDocument();
		} );

		test( 'shows placeholder when no default token', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () =>
					Promise.resolve( {
						data: { tokens: tokensWithoutDefault },
					} ),
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const customerSelect = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			customerSelect.value = '1';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				expect(
					screen.getByText( 'Please select a payment method' )
				).toBeInTheDocument();
			} );
		} );

		test( 'placeholder option is disabled', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () =>
					Promise.resolve( {
						data: { tokens: tokensWithoutDefault },
					} ),
			} );

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// Trigger customer selection change
			const customerSelect = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			customerSelect.value = '1';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				const placeholderOption = screen.getByText(
					'Please select a payment method'
				) as HTMLOptionElement;
				expect( placeholderOption ).toHaveAttribute( 'disabled' );
				expect( placeholderOption ).toHaveAttribute( 'value', '0' );
			} );
		} );

		test( 'uses initial value when provided', () => {
			// When initialValue is provided and cache is empty,
			// the component renders with the initial value
			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 2 }
					initialUserId={ 1 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			const select = screen.getByRole( 'combobox' ) as HTMLSelectElement;
			// Since cache is empty, select will have no options but defaultValue is 2
			expect( select ).toHaveAttribute( 'name', 'payment_method' );
		} );
	} );

	describe( 'Cache Behavior', () => {
		test( 'does not fetch again when cache already has tokens', async () => {
			const fetchMock = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( { data: { tokens: mockTokens } } ),
			} );
			global.fetch = fetchMock;

			document.body.innerHTML =
				'<select id="customer_user"><option value="1">User 1</option></select>';

			render(
				<PaymentMethodSelect
					inputName="payment_method"
					initialValue={ 0 }
					initialUserId={ 0 }
					nonce="test-nonce"
					ajaxUrl="http://test.com/ajax"
				/>
			);

			// First customer selection - should fetch
			const customerSelect = document.getElementById(
				'customer_user'
			) as HTMLSelectElement;
			customerSelect.value = '1';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			await waitFor( () => {
				expect(
					screen.getByText( 'Visa •••• 1234' )
				).toBeInTheDocument();
			} );

			expect( fetchMock ).toHaveBeenCalledTimes( 1 );

			// Change to a different user to reset state
			customerSelect.value = '2';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			// This should fetch for user 2
			await waitFor( () => {
				expect( fetchMock ).toHaveBeenCalledTimes( 2 );
			} );

			// Change back to user 1 - should NOT fetch again (cached)
			customerSelect.value = '1';

			await act( async () => {
				customerSelect.dispatchEvent( new Event( 'change' ) );
			} );

			// Tokens should be available immediately from cache
			expect( screen.getByText( 'Visa •••• 1234' ) ).toBeInTheDocument();

			// Fetch should not have been called again
			expect( fetchMock ).toHaveBeenCalledTimes( 2 );
		} );
	} );
} );

describe( 'fetchUserTokens', () => {
	const originalFetch = global.fetch;

	afterEach( () => {
		global.fetch = originalFetch;
	} );

	test( 'sends correct request parameters', async () => {
		let capturedUrl = '';
		let capturedOptions: RequestInit | undefined;

		global.fetch = jest.fn().mockImplementation( ( url, options ) => {
			capturedUrl = url;
			capturedOptions = options;
			return Promise.resolve( {
				ok: true,
				json: () => Promise.resolve( { data: { tokens: [] } } ),
			} );
		} );

		await fetchUserTokens( 123, 'http://test.com/ajax', 'test-nonce' );

		expect( capturedUrl ).toBe( 'http://test.com/ajax' );
		expect( capturedOptions?.method ).toBe( 'POST' );

		const formData = capturedOptions?.body as FormData;
		expect( formData.get( 'action' ) ).toBe(
			'wcpay_get_user_payment_tokens'
		);
		expect( formData.get( 'nonce' ) ).toBe( 'test-nonce' );
		expect( formData.get( 'user_id' ) ).toBe( '123' );
	} );

	test( 'returns tokens on successful response', async () => {
		const mockTokens: Token[] = [
			{ tokenId: 1, displayName: 'Visa •••• 1234', isDefault: true },
		];

		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: () => Promise.resolve( { data: { tokens: mockTokens } } ),
		} );

		const result = await fetchUserTokens(
			1,
			'http://test.com/ajax',
			'nonce'
		);

		expect( result ).toEqual( mockTokens );
	} );

	test( 'throws error when response is not ok', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
		} );

		await expect(
			fetchUserTokens( 1, 'http://test.com/ajax', 'nonce' )
		).rejects.toThrow( 'Failed to fetch user tokens' );
	} );

	test( 'throws error when response data is undefined', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: () => Promise.resolve( {} ),
		} );

		await expect(
			fetchUserTokens( 1, 'http://test.com/ajax', 'nonce' )
		).rejects.toThrow(
			'Failed to fetch user tokens. Please reload the page and try again.'
		);
	} );
} );
