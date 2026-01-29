/**
 * Internal dependencies
 */
import { normalizeLineItems, normalizeShippingAddress } from '../normalize';

describe( 'Express checkout normalization', () => {
	describe( 'normalizeLineItems', () => {
		test( 'normalizes blocks array properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					value: 100,
				},
				{
					label: 'Item 2',
					value: 200,
				},
				{
					label: 'Item 3',
					valueWithTax: 300,
					value: 200,
				},
			];

			// Extra items in the array are expected since they're not stripped.
			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 200,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );

		test( 'normalizes shortcode array properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					amount: 100,
				},
				{
					label: 'Item 2',
					amount: 200,
				},
				{
					label: 'Item 3',
					amount: 300,
				},
			];

			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 300,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );

		test( 'normalizes discount line item properly', () => {
			const displayItems = [
				{
					label: 'Item 1',
					amount: 100,
				},
				{
					label: 'Item 2',
					amount: 200,
				},
				{
					label: 'Item 3',
					amount: 300,
				},
				{
					key: 'total_discount',
					label: 'Discount',
					amount: 50,
				},
			];

			const expected = [
				{
					name: 'Item 1',
					amount: 100,
				},
				{
					name: 'Item 2',
					amount: 200,
				},
				{
					name: 'Item 3',
					amount: 300,
				},
				{
					name: 'Discount',
					amount: -50,
				},
			];

			expect( normalizeLineItems( displayItems ) ).toStrictEqual(
				expected
			);
		} );
	} );

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
