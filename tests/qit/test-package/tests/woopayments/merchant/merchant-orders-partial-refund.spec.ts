/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';

import {
	setupProductCheckout,
	placeOrderWithOptions,
} from '../../../utils/shopper';

type ConfigProduct = ( typeof config.products )[ keyof typeof config.products ];
import {
	goToOrder,
	activateMulticurrency,
	deactivateMulticurrency,
	restoreCurrencies,
} from '../../../utils/merchant';

// Needs to be finished.
test.describe( 'Order > Partial refund', { tag: '@merchant' }, () => {
	const product1 = config.products.simple;
	const product2 = config.products.belt;
	const product3 = config.products.hoodie_with_logo;

	const lineItems: [ ConfigProduct, number ][][] = [
		[
			[ product1, 1 ],
			[ product2, 1 ],
		],
		[
			[ product1, 1 ],
			[ product2, 2 ],
			[ product3, 1 ],
		],
	];

	/**
	 * Elements:
	 * - test title
	 * - object containing the items to be ordered, and the quantities and amounts to be refunded
	 */
	const dataTable: Array<
		[
			string,
			{
				lineItems: Array< [ string, number ] >;
				refundInputs: { refundQty: number; refundAmount: number }[];
			},
		]
	> = [
		[
			'Partially refund one product of two product order',
			{
				lineItems: lineItems[ 0 ].map( ( [ item, quantity ] ) => [
					item.name,
					quantity,
				] ),
				refundInputs: [ { refundQty: 0, refundAmount: 5 } ],
			},
		],
		[
			'Refund two products of three product order',
			{
				lineItems: lineItems[ 1 ].map( ( [ item, quantity ] ) => [
					item.name,
					quantity,
				] ),
				refundInputs: [
					{ refundQty: 1, refundAmount: 18 },
					{ refundQty: 1, refundAmount: 55 },
				],
			},
		],
	];

	let orderIds: string[];
	let orderTotal: string;
	let wasMulticurrencyEnabled = false;

	const orderProducts = async ( customerPage, dataTableIndex: number ) => {
		await setupProductCheckout( customerPage, lineItems[ dataTableIndex ] );
		const orderId = await placeOrderWithOptions( customerPage );
		return orderId;
	};

	test.beforeAll( async ( { adminPage, customerPage } ) => {
		test.setTimeout( 120000 ); // Increase timeout for order creation
		wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
		await restoreCurrencies( adminPage );
		const firstOrderId = await orderProducts( customerPage, 0 );
		const secondOrderId = await orderProducts( customerPage, 1 );
		orderIds = [ firstOrderId, secondOrderId ];
	} );

	test.afterAll( async ( { adminPage } ) => {
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( adminPage );
		}
	} );

	dataTable.forEach( ( [ title, { refundInputs } ], i ) => {
		test( title, { tag: '@critical' }, async ( { adminPage } ) => {
			await goToOrder( adminPage, orderIds[ i ] );

			const orderTotalField = adminPage
				.getByRole( 'row', { name: 'Order Total: $' } )
				.locator( 'bdi' );

			// Calculate order total, refund total, and net payment.
			orderTotal = await orderTotalField.innerText();
			const orderTotalNumber = parseFloat( orderTotal.substring( 1 ) );
			const refundTotal = refundInputs
				.map( ( { refundAmount } ) => refundAmount )
				.reduce( ( acc, cur ) => acc + cur );
			const refundTotalString = refundTotal.toFixed( 2 );
			const netPayment = ( orderTotalNumber - refundTotal ).toFixed( 2 );

			await adminPage.getByRole( 'button', { name: 'Refund' } ).click();

			// Fill in the refund inputs.
			for ( let j = 0; j < refundInputs.length; j++ ) {
				const { refundQty, refundAmount } = refundInputs[ j ];
				await adminPage
					.locator( '.refund_order_item_qty' )
					.nth( j )
					.fill( refundQty.toString() );

				await adminPage
					.locator( '.refund_line_total' )
					.nth( j )
					.clear();

				await adminPage
					.locator( '.refund_line_total' )
					.nth( j )
					.fill( refundAmount.toString() );

				await adminPage.keyboard.press( 'Tab' );
			}

			// Check that the refund amount is correct.
			await expect(
				adminPage.getByLabel( 'Refund amount:' )
			).toHaveValue( `${ refundTotalString }` );

			await adminPage
				.getByLabel( 'Reason for refund (optional):' )
				.fill( title );

			// Check that the reason for refund is correct.
			await expect(
				adminPage.getByLabel( 'Reason for refund (optional):' )
			).toHaveValue( title );

			adminPage.on( 'dialog', ( dialog ) => dialog.accept() );

			await adminPage
				.getByRole( 'button', {
					name: `Refund $${ refundTotalString } via WooPayments`,
				} )
				.click();

			// Verify that the refunded amounts are correct.
			for ( let k = 0; k < refundInputs.length; k++ ) {
				const { refundQty, refundAmount } = refundInputs[ k ];

				if ( refundQty ) {
					await expect(
						adminPage.getByText( '-1' ).nth( k )
					).toHaveText( `-${ refundQty.toString() }` );

					await expect(
						adminPage
							.locator( '#order_line_items' )
							.getByText( '-$' )
							.nth( k )
					).toHaveText( `-$${ refundAmount.toFixed( 2 ) }` );
				} else {
					await expect(
						adminPage
							.locator( '#order_line_items' )
							.getByText( '-$' )
					).toHaveText( `-$${ refundAmount.toFixed( 2 ) }` );
				}
			}

			// Check that the refund order note includes the refund amount and reason.
			const refundNote = await adminPage
				.getByText( 'A refund of' )
				.innerText();

			expect( refundNote ).toContain( title );
			expect( refundNote ).toContain( refundTotalString );

			// Check that the refunded amount line item is correct.
			await expect(
				adminPage
					.getByRole( 'row', { name: 'Refunded' } )
					.locator( 'bdi' )
			).toHaveText( `$${ refundTotalString } USD` );

			// Check that the net payment line item is correct.
			await expect(
				adminPage
					.getByRole( 'row', { name: 'Net Payment' } )
					.locator( 'bdi' )
			).toHaveText( `$${ netPayment } USD` );
		} );
	} );
} );
