/**
 * Internal dependencies
 */
import { normalizeShippingAddress } from '../normalize';

describe( 'Express checkout normalization', () => {
	describe( 'normalizeShippingAddress', () => {
		test( 'should normalize shipping address with all fields present', () => {
			const shippingAddress = {
				recipient: 'John Doe',
				addressLine: [ '123 Main St', 'Apt 4B' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe',
				company: '',
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with only recipient name', () => {
			const shippingAddress = {
				recipient: 'John',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: '',
				company: '',
				address_1: '',
				address_2: '',
				city: '',
				state: '',
				country: '',
				postcode: '',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with missing recipient name', () => {
			const shippingAddress = {
				addressLine: [ '123 Main St' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '123 Main St',
				address_2: '',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize shipping address with empty addressLine', () => {
			const shippingAddress = {
				recipient: 'John Doe',
				addressLine: [],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe',
				company: '',
				address_1: '',
				address_2: '',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize an empty shipping address', () => {
			const shippingAddress = {};

			const expectedNormalizedAddress = {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '',
				address_2: '',
				city: '',
				state: '',
				country: '',
				postcode: '',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );

		test( 'should normalize a shipping address with a multi-word recipient name', () => {
			const shippingAddress = {
				recipient: 'John Doe Smith',
				addressLine: [ '123 Main St', 'Apt 4B' ],
				city: 'New York',
				state: 'NY',
				country: 'US',
				postal_code: '10001',
			};

			const expectedNormalizedAddress = {
				first_name: 'John',
				last_name: 'Doe Smith',
				company: '',
				address_1: '123 Main St',
				address_2: 'Apt 4B',
				city: 'New York',
				state: 'NY',
				country: 'US',
				postcode: '10001',
			};

			expect( normalizeShippingAddress( shippingAddress ) ).toEqual(
				expectedNormalizedAddress
			);
		} );
	} );
} );
