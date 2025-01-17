/**
 * External dependencies
 */
import { test, expect, Page, Browser } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { getMerchant, useShopper } from '../../utils/helpers';
import { placeOrder, setupProductCheckout } from '../../utils/shopper';
import * as merchant from '../../utils/merchant';
import { goToShop } from '../../utils/shopper-navigation';

// Needs to be finished.
test.skip( 'Order > Partial refund', () => {
	useShopper();
	const product1 = config.products.simple.name;
	const product2 = 'Belt';
	const product3 = 'Hoodie';

	/**
	 * Elements:
	 * - test title
	 * - object containing the items to be ordered, and the quantities and amounts to be refunded
	 */
	const dataTable: [
		string,
		{
			lineItems: Array< [ string, number ] >;
			refundInputs: { refundQty: number; refundAmount: number }[];
		}
	][] = [
		[
			'a single line item',
			{
				lineItems: [
					[ product1, 1 ],
					[ product2, 1 ],
				],
				refundInputs: [ { refundQty: 0, refundAmount: 5 } ],
			},
		],
		[
			'several line items',
			{
				lineItems: [
					[ product1, 1 ],
					[ product2, 2 ],
					[ product3, 1 ],
				],
				refundInputs: [
					{ refundQty: 1, refundAmount: 18 },
					{ refundQty: 1, refundAmount: 55 },
				],
			},
		],
	];

	let orderId;
	let orderTotal;

	test( 'Refund one product of two product order', async ( { page } ) => {
		const { lineItems, refundInputs } = dataTable[ 0 ][ 1 ];
		await goToShop( page );
		await setupProductCheckout( page, lineItems );
		await placeOrder( page );
	} );
} );
