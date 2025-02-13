/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config, Product } from '../../../config/default';
import { getMerchant, getShopper } from '../../../utils/helpers';
import {
	fillCardDetails,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToOrder } from '../../../utils/merchant-navigation';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	restoreCurrencies,
} from '../../../utils/merchant';

// Needs to be finished.
test.describe( 'Order > Partial refund', () => {
	const product1 = config.products.simple;
	const product2 = config.products.belt;
	const product3 = config.products.hoodie_with_logo;

	const lineItems: [ Product, number ][][] = [
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
	const dataTable: Array< [
		string,
		{
			lineItems: Array< [ string, number ] >;
			refundInputs: { refundQty: number; refundAmount: number }[];
		}
	] > = [
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
	let merchantPage: Page, shopperPage: Page;

	const orderProducts = async ( dataTableIndex: number ) => {
		await setupProductCheckout( shopperPage, lineItems[ dataTableIndex ] );
		await fillCardDetails( shopperPage );
		await placeOrder( shopperPage );
		await expect(
			shopperPage.getByRole( 'heading', { name: 'Order received' } )
		).toBeVisible();

		const orderIdField = shopperPage.locator(
			'.woocommerce-order-overview__order.order > strong'
		);
		const orderId = await orderIdField.innerText();

		return orderId;
	};

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );
		await restoreCurrencies( merchantPage );
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		const firstOrderId = await orderProducts( 0 );
		const secondOrderId = await orderProducts( 1 );
		orderIds = [ firstOrderId, secondOrderId ];
	} );

	test.afterAll( async () => {
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	dataTable.forEach( ( [ title, { refundInputs } ], i ) => {
		test( title, { tag: '@critical' }, async ( { browser } ) => {
			merchantPage = ( await getMerchant( browser ) ).merchantPage;
			await goToOrder( merchantPage, orderIds[ i ] );

			const orderTotalField = merchantPage
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

			await merchantPage
				.getByRole( 'button', { name: 'Refund' } )
				.click();

			// Fill in the refund inputs.
			for ( let j = 0; j < refundInputs.length; j++ ) {
				const { refundQty, refundAmount } = refundInputs[ j ];
				await merchantPage
					.locator( '.refund_order_item_qty' )
					.nth( j )
					.fill( refundQty.toString() );

				await merchantPage
					.locator( '.refund_line_total' )
					.nth( j )
					.clear();

				await merchantPage
					.locator( '.refund_line_total' )
					.nth( j )
					.fill( refundAmount.toString() );

				await merchantPage.keyboard.press( 'Tab' );
			}

			// Check that the refund amount is correct.
			await expect(
				merchantPage.getByLabel( 'Refund amount:' )
			).toHaveValue( `${ refundTotalString }` );

			await merchantPage
				.getByLabel( 'Reason for refund (optional):' )
				.fill( title );

			// Check that the reason for refund is correct.
			await expect(
				merchantPage.getByLabel( 'Reason for refund (optional):' )
			).toHaveValue( title );

			merchantPage.on( 'dialog', ( dialog ) => dialog.accept() );

			await merchantPage
				.getByRole( 'button', {
					name: `Refund $${ refundTotalString } via WooPayments`,
				} )
				.click();

			// Verify that the refunded amounts are correct.
			for ( let k = 0; k < refundInputs.length; k++ ) {
				const { refundQty, refundAmount } = refundInputs[ k ];

				if ( refundQty ) {
					await expect(
						merchantPage.getByText( '-1' ).nth( k )
					).toHaveText( `-${ refundQty.toString() }` );

					await expect(
						merchantPage
							.locator( '#order_line_items' )
							.getByText( '-$' )
							.nth( k )
					).toHaveText( `-$${ refundAmount.toFixed( 2 ) }` );
				} else {
					await expect(
						merchantPage
							.locator( '#order_line_items' )
							.getByText( '-$' )
					).toHaveText( `-$${ refundAmount.toFixed( 2 ) }` );
				}
			}

			// Check that the refund order note includes the refund amount and reason.
			const refundNote = await merchantPage
				.getByText( 'A refund of' )
				.innerText();

			expect( refundNote ).toContain( title );
			expect( refundNote ).toContain( refundTotalString );

			// Check that the refunded amount line item is correct.
			await expect(
				merchantPage
					.getByRole( 'row', { name: 'Refunded' } )
					.locator( 'bdi' )
			).toHaveText( `$${ refundTotalString } USD` );

			// Check that the net payment line item is correct.
			await expect(
				merchantPage
					.getByRole( 'row', { name: 'Net Payment' } )
					.locator( 'bdi' )
			).toHaveText( `$${ netPayment } USD` );
		} );
	} );
} );
