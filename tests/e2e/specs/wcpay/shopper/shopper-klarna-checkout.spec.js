/**
 * External dependencies
 */
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );
import config from 'config';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { setupProductCheckout } from '../../../utils/payments';

const UPE_METHOD_CHECKBOXES = [
	"//label[contains(text(), 'Klarna')]/preceding-sibling::span/input[@type='checkbox']",
];

const checkoutPaymentMethodSelector = `//*[@id='payment']/ul/li/label[contains(text(), 'Klarna')]`;

describe( 'Klarna checkout', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
		await shopper.login();
	} );

	beforeEach( async () => {
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
	} );

	afterEach( async () => {
		await shopperWCP.emptyCart();
	} );

	afterAll( async () => {
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
	} );

	it( 'should not show Klarna at checkout if the order amount is less than the minimum threshold', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ 'Beanie', 1 ] ]
		);

		await uiUnblocked();

		expect( await page.$x( checkoutPaymentMethodSelector ) ).toBeNull();
	} );

	it.only( 'should show the product messaging on the product page', async () => {
		await shopperWCP.goToProductPageBySlug( 'belt' );

		// waiting for the "product messaging" component to be rendered, so we can click on it.
		const paymentMethodMessageFrameHandle = await page.waitForSelector(
			'#payment-method-message iframe'
		);
		const paymentMethodMessageIframe = await paymentMethodMessageFrameHandle.contentFrame();
		const productMessaging = await paymentMethodMessageIframe.waitForSelector(
			'button[aria-label="Open Learn More Modal"]',
			{ timeout: 30000 }
		);
		console.log( '###', paymentMethodMessageIframe, productMessaging );
		// const productMessaging = await paymentMethodMessageIframe.$(
		// 	'button[aria-label="Open Learn More Modal"]'
		// );
		await productMessaging.click();

		const paymentMethodMessageModalIframeHandle = await page.waitForSelector(
			'iframe[src*="js.stripe.com/v3/elements-inner-payment-method-messaging-modal"]',
			{ timeout: 30000 }
		);
		const paymentMethodMessageModalIframe = await paymentMethodMessageModalIframeHandle.contentFrame();

		await expect( paymentMethodMessageModalIframe ).toMatchElement(
			'[data-testid="ModalHeader"]',
			{
				text: 'Buy Now. Pay Later.',
			}
		);
		await expect( paymentMethodMessageModalIframe ).toMatchElement(
			'[data-testid="ModalDescription"]',
			{
				text:
					'Select Klarna as your payment method at checkout to pay in installments.',
			}
		);
	} );

	it( `should successfully place an order with Klarna`, async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ 'Beanie', 3 ] ]
		);

		await uiUnblocked();
		// Select BNPL provider as payment method.
		await page.waitForXPath( checkoutPaymentMethodSelector );
		const [ paymentMethodLabel ] = await page.$x(
			checkoutPaymentMethodSelector
		);
		await paymentMethodLabel.click();
		await shopper.placeOrder();

		// waiting for the redirect & the Klarna iframe to load within the Stripe test page
		const klarnaFrameHandle = await page.waitForSelector(
			'#klarna-apf-iframe'
		);
		const klarnaIframe = await klarnaFrameHandle.contentFrame();
		(
			await klarnaIframe.waitForSelector(
				'[data-testid="select-payment-category"]'
			)
		 ).click();
		(
			await klarnaIframe.waitForSelector( '[data-testid="pick-plan"]' )
		 ).click();
		(
			await klarnaIframe.waitForSelector(
				'[data-testid="confirm-and-pay"]'
			)
		 ).click();

		// Wait for the order confirmation page to load.
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
