/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { getMerchant, getShopper } from '../../utils/helpers';
import {
	fillCardDetails,
	placeOrder,
	setupProductCheckout,
} from '../../utils/shopper';
import { goToShop } from '../../utils/shopper-navigation';
import { goToOrder } from '../../utils/merchant-navigation';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	restoreCurrencies,
} from '../../utils/merchant';

// Needs to be finished.
test.describe( 'Order > Partial refund', () => {
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

	let firstOrderId: string;
	let orderTotal: string;
	let wasMulticurrencyEnabled = false;
	let merchantPage: Page, shopperPage: Page;

	const orderProducts = async ( { browser }, dataTableIndex: number ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		const lineItems = dataTable[ dataTableIndex ][ 1 ].lineItems;
		await goToShop( shopperPage );
		await setupProductCheckout( shopperPage, lineItems );
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
		firstOrderId = await orderProducts( { browser }, 0 );
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );
		await restoreCurrencies( merchantPage );
	} );

	test.afterAll( async () => {
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test( 'Partially refund one product of two product order', async ( {
		browser,
	} ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		const { refundInputs } = dataTable[ 0 ][ 1 ];
		await goToOrder( merchantPage, firstOrderId );

		const orderTotalField = merchantPage
			.getByRole( 'row', { name: 'Order Total: $' } )
			.locator( 'bdi' );

		orderTotal = await orderTotalField.innerText();
		const orderTotalNumber = parseFloat( orderTotal.substring( 1 ) );
		const { refundQty, refundAmount } = refundInputs[ 0 ];
		const netPayment = orderTotalNumber - refundAmount;

		await merchantPage.getByRole( 'button', { name: 'Refund' } ).click();
		await merchantPage
			.locator( '.refund_order_item_qty' )
			.first()
			.fill( refundQty.toString() );

		await merchantPage.locator( '.refund_line_total' ).first().clear();

		await merchantPage
			.locator( '.refund_line_total' )
			.first()
			.fill( refundAmount.toString() );

		await merchantPage.keyboard.press( 'Tab' );

		await expect( merchantPage.getByLabel( 'Refund amount:' ) ).toHaveValue(
			'5.00'
		);

		await merchantPage
			.getByLabel( 'Reason for refund (optional):' )
			.fill( 'Refund a single line item' );

		merchantPage.on( 'dialog', ( dialog ) => dialog.accept() );

		await merchantPage
			.getByRole( 'button', { name: 'Refund $5.00 via WooPayments' } )
			.click();

		await expect(
			merchantPage
				.getByRole( 'row', { name: 'Net Payment' } )
				.locator( 'bdi' )
		).toHaveText( `$${ netPayment.toFixed( 2 ) } USD` );
	} );
} );
