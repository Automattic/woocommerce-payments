/**
 * Internal dependencies
 */
import { normalizeLineItems } from '../normalize';

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
} );
