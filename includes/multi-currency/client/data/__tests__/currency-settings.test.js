/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getCurrencySettings } from '../resolvers';
import { submitCurrencySettings } from '../actions';

jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn(),
	select: jest.fn(),
} ) );

const mockCreateErrorNotice = jest.fn();

describe( 'single currency settings requests', () => {
	const currencyCode = 'EUR';
	const settings = { exchange_rate_type: 'automatic' };

	beforeEach( () => {
		jest.clearAllMocks();
		dispatch.mockReturnValue( {
			createErrorNotice: mockCreateErrorNotice,
		} );
	} );

	test( 'getCurrencySettings requests the currency', () => {
		const generator = getCurrencySettings( currencyCode );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/multi-currency/currencies/EUR',
			} )
		);
	} );

	test( 'submitCurrencySettings posts to the currency', () => {
		const generator = submitCurrencySettings( currencyCode, settings );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/multi-currency/currencies/EUR',
				method: 'POST',
				data: { exchange_rate_type: 'automatic' },
			} )
		);
	} );

	describe.each( [
		'../../../wp/v2/users/me',
		'EUR/x',
		'EUR?x=1',
		'EURO',
		'..',
		'',
		undefined,
	] )( 'with the currency code %p', ( invalidCode ) => {
		test( 'getCurrencySettings does not send a request', () => {
			const generator = getCurrencySettings( invalidCode );
			generator.next();

			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Error retrieving single currency settings.'
			);
			expect( generator.next().done ).toBe( true );
		} );

		test( 'submitCurrencySettings does not send a request', () => {
			const generator = submitCurrencySettings( invalidCode, settings );
			generator.next();

			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Error updating currency settings.'
			);
			expect( generator.next().done ).toBe( true );
		} );
	} );
} );
