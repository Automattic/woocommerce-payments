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

	it( 'should show the product messaging on the product page', async () => {
		await shopperWCP.goToProductPageBySlug( 'belt' );

		// waiting for the "product messaging" component to be rendered, so we can click on it.
		const paymentMethodMessageFrameHandle = await page.waitForSelector(
			'#payment-method-message iframe'
		);
		const paymentMethodMessageIframe = await paymentMethodMessageFrameHandle.contentFrame();
		const productMessaging = await paymentMethodMessageIframe.waitForSelector(
			'button[aria-label="Open Learn More Modal"]'
		);
		// scrolling vertically to get the "product messaging" into view, since it seems that otherwise clicking on it fails.
		await page.evaluate( () => {
			window.scrollBy( 0, 100 );
		} );
		await productMessaging.click();

		// we need to wait for the iframe to be added by Stripe JS after clicking on the element.
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
		const getNewKlarnaIframe = async () => {
			const klarnaFrameHandle = await page.waitForSelector(
				'#klarna-apf-iframe'
			);
			return await klarnaFrameHandle.contentFrame();
		};

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

		// waiting for the redirect & the Klarna iframe to load within the Stripe test page.
		// this is the "confirm phone number" page - we just click "continue".
		await ( await getNewKlarnaIframe() ).waitForSelector(
			'#collectPhonePurchaseFlow'
		);
		(
			await ( await getNewKlarnaIframe() ).waitForSelector(
				'#onContinue[data-testid="kaf-button"]'
			)
		 ).click();
		// this is where the OTP code is entered.
		await ( await getNewKlarnaIframe() ).waitForSelector( '#phoneOtp' );
		await expect( await getNewKlarnaIframe() ).toFill(
			'[data-testid="kaf-field"]',
			'000000'
		);

		// selecting the installment plan.
		(
			await ( await getNewKlarnaIframe() ).waitForSelector(
				'button[data-testid="select-payment-category"]'
			)
		 ).click();
		// the buttons might be present, but in a "disabled" state.
		// We need to ensure they can be clickable, before performing the "click" action.
		(
			await ( await getNewKlarnaIframe() ).waitForSelector(
				'button[data-testid="pick-plan"]:not(:disabled)'
			)
		 ).click();
		(
			await (
				await getNewKlarnaIframe()
			 ).waitForSelector(
				'button[data-testid="confirm-and-pay"]:not(:disabled)',
				{ timeout: 15000 }
			)
		 ).click();

		// Wait for the order confirmation page to load.
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
