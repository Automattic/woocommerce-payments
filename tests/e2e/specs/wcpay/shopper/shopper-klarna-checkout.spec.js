/**
 * External dependencies
 */
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );
import config from 'config';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils';
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
		await shopperWCP.changeAccountCurrencyTo(
			config.get( 'addresses.customer.billing' ),
			'USD'
		);
	} );

	afterAll( async () => {
		await shopperWCP.emptyCart();
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
	} );

	it( 'should show the product messaging on the product page', async () => {
		await shopperWCP.goToProductPageBySlug( 'belt' );

		// waiting for the "product messaging" component to be rendered, so we can click on it.
		const paymentMethodMessageFrameHandle = await page.waitForSelector(
			'#payment-method-message iframe'
		);
		const paymentMethodMessageIframe = await paymentMethodMessageFrameHandle.contentFrame();

		// Click on Klarna link to open the modal.
		await paymentMethodMessageIframe.evaluate( ( selector ) => {
			const element = document.querySelector( selector );
			if ( element ) {
				element.click();
			}
		}, 'button[aria-label="Open Learn More Modal"]' );

		// Wait for the iframe to be added by Stripe JS after clicking on the element.
		await page.waitFor( 1000 );

		const paymentMethodMessageModalIframeHandle = await page.waitForSelector(
			'iframe[src*="js.stripe.com/v3/elements-inner-payment-method-messaging-modal"]'
		);
		const paymentMethodMessageModalIframe = await paymentMethodMessageModalIframeHandle.contentFrame();

		await expect( paymentMethodMessageModalIframe ).toMatchElement(
			'[data-testid="ModalHeader"] > p',
			{
				text: 'Buy Now. Pay Later.',
			}
		);

		await expect( paymentMethodMessageModalIframe ).toMatchElement(
			'[data-testid="ModalDescription"] > p',
			{
				text:
					'Select Klarna as your payment method at checkout to pay in installments.',
			}
		);
	} );

	it( `should successfully place an order with Klarna`, async () => {
		await setupProductCheckout(
			{
				...config.get( 'addresses.customer.billing' ),
				// these are Klarna-specific values:
				// https://docs.klarna.com/resources/test-environment/sample-customer-data/#united-states-of-america
				email: 'customer@email.us',
				phone: '+13106683312',
			},
			[ [ 'Beanie', 3 ] ]
		);

		await uiUnblocked();

		await page.waitForXPath( checkoutPaymentMethodSelector );
		const [ paymentMethodLabel ] = await page.$x(
			checkoutPaymentMethodSelector
		);
		await paymentMethodLabel.click();
		await shopper.placeOrder();

		// Klarna is rendered in an iframe, so we need to get its reference.
		// Sometimes the iframe is updated (or removed from the page),
		// this function has been created so that we always get the most updated reference.
		const getNewKlarnaIframe = async () => {
			const klarnaFrameHandle = await page.waitForSelector(
				'#klarna-apf-iframe'
			);

			return await klarnaFrameHandle.contentFrame();
		};

		let klarnaIframe = await getNewKlarnaIframe();

		const frameNavigationHandler = async ( frame ) => {
			const newKlarnaIframe = await getNewKlarnaIframe();
			if ( frame === newKlarnaIframe ) {
				klarnaIframe = newKlarnaIframe;
			}
		};

		// Add frame navigation event listener.
		page.on( 'framenavigated', frameNavigationHandler );

		// waiting for the redirect & the Klarna iframe to load within the Stripe test page.
		// this is the "confirm phone number" page - we just click "continue".
		await klarnaIframe.waitForSelector( '#collectPhonePurchaseFlow' );
		(
			await klarnaIframe.waitForSelector(
				'#onContinue[data-testid="kaf-button"]'
			)
		 ).click();
		// this is where the OTP code is entered.
		await klarnaIframe.waitForSelector( '#phoneOtp' );
		await expect( klarnaIframe ).toFill(
			'[data-testid="kaf-field"]',
			'000000'
		);

		await klarnaIframe.waitForSelector(
			'button[data-testid="select-payment-category"'
		);

		await klarnaIframe.waitForSelector( '.skeleton-wrapper' );
		await klarnaIframe.waitFor(
			() => ! document.querySelector( '.skeleton-wrapper' )
		);

		// Select Payment Plan - 4 weeks & click continue.
		await klarnaIframe
			.waitForSelector( 'input[type="radio"][id*="pay_in_n"]' )
			.then( ( input ) => input.click() );
		await klarnaIframe
			.waitForSelector( 'button[data-testid="select-payment-category"' )
			.then( ( button ) => button.click() );

		// Payment summary page. Click continue.
		await klarnaIframe
			.waitForSelector( 'button[data-testid="pick-plan"]' )
			.then( ( button ) => button.click() );

		// at this point, the event listener is not needed anymore.
		page.removeListener( 'framenavigated', frameNavigationHandler );

		// Confirm payment.
		await klarnaIframe
			.waitForSelector(
				'button[data-testid="confirm-and-pay"]:not(:disabled)'
			)
			.then( ( button ) => button.click() );

		// Wait for the order confirmation page to load.
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
