/* Migrated from ../../../../e2e/specs/wcpay/shopper/shopper-wc-blocks-checkout-failures.spec.ts for QIT */
/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	cards,
	defaultBillingAddress,
	addToCartFromShopPage,
	setupCheckout,
	emptyCart,
	fillCardDetails,
	placeOrder,
	confirmCardAuthentication,
} from '../../../helpers/checkout.js';

const failureCases = [
	{ key: 'declined', error: 'Your card was declined.' },
	{
		key: 'invalid-exp-date',
		error: 'Your card\u2019s expiration year is in the past.',
	},
	{
		key: 'invalid-cvv-number',
		error: 'Your card\u2019s security code is incomplete.',
		stripeErrorSelector: '#Field-cvcError',
	},
	{
		key: 'declined-funds',
		error: 'Your card has insufficient funds.',
	},
	{ key: 'declined-expired', error: 'Your card has expired.' },
	{
		key: 'declined-cvc',
		error: "Your card's security code is incorrect.",
	},
	{
		key: 'declined-processing',
		error:
			'An error occurred while processing your card. Try again in a little bit.',
	},
	{
		key: 'declined-incorrect',
		error: 'Your card number is invalid.',
		stripeErrorSelector: '#Field-numberError',
	},
	{
		key: 'declined-3ds',
		error: 'Your card has been declined.',
		alternateErrors: [
			'We are unable to authenticate your payment method. Please choose a different payment method and try again.',
			'Your card was declined.',
		],
		requires3ds: {
			authorize: true,
			waitFor: 'error',
		},
	},
];

const expectStripeFieldError = async ( page, selector, message ) => {
	const frameLocator = page.frameLocator(
		'iframe[name^="__privateStripeFrame"][title="Secure payment input frame"]'
	);
	const errorLocator = frameLocator.locator( selector ).first();
	await expect( errorLocator ).toBeVisible();
	await expect( errorLocator ).toContainText( message );
};

const expectBlocksErrorMessage = async ( page, messages ) => {
	await page
		.waitForSelector(
			'.wcpay-payment-error, .wc-block-components-notice-banner__content, .wc-block-components-notice-banner',
			{ timeout: 20000 }
		)
		.catch( () => {} );
	const expectedMessages = Array.isArray( messages )
		? messages
		: [ messages ];
	const noticeSelectors = [
		'.wc-block-components-notice-banner__content',
		'.wc-block-components-notice-banner',
		'.wc-block-checkout__form',
		'.wcpay-payment-error',
	].join( ', ' );
	const noticeArea = page.locator( noticeSelectors ).first();
	await expect( noticeArea ).toBeVisible();
	const noticeText = ( await noticeArea.innerText() ).toLowerCase().trim();
	const matched = expectedMessages.some( ( candidate ) => {
		const normalized = candidate?.toString().toLowerCase();
		return (
			normalized &&
			( noticeText.includes( normalized ) ||
				noticeText.includes( `error: ${ normalized }` ) )
		);
	} );
	expect( matched ).toBeTruthy();
};

test.describe(
	'QIT WooCommerce Blocks Checkout - Failure handling @critical @blocks @shopper @shopper-wc-blocks-checkout-failures',
	() => {
		let shopperPage;

		test.beforeAll( async ( { browser } ) => {
			shopperPage = await browser.newPage();
		} );

		test.afterAll( async () => {
			await shopperPage?.close();
		} );

		test.beforeEach( async () => {
			await emptyCart( shopperPage ).catch( () => {} );
			await addToCartFromShopPage( shopperPage );
			await setupCheckout(
				shopperPage,
				defaultBillingAddress,
				'/checkout'
			);
		} );

		test.afterEach( async () => {
			await emptyCart( shopperPage ).catch( () => {} );
		} );

		for ( const {
			key,
			error,
			stripeErrorSelector,
			requires3ds,
			alternateErrors,
		} of failureCases ) {
			test( `should surface an error – ${ error }`, async () => {
				await fillCardDetails( shopperPage, cards[ key ] );
				await placeOrder( shopperPage );

				if ( requires3ds ) {
					const { authorize = false, waitFor = 'error' } =
						typeof requires3ds === 'object' ? requires3ds : {};
					await confirmCardAuthentication( shopperPage, authorize, {
						waitFor,
					} );
				}

				if ( stripeErrorSelector ) {
					await expectStripeFieldError(
						shopperPage,
						stripeErrorSelector,
						error
					);
				} else {
					const possibleErrors = [
						error,
						...( Array.isArray( alternateErrors )
							? alternateErrors
							: [] ),
					];
					await expectBlocksErrorMessage(
						shopperPage,
						possibleErrors
					);
				}
			} );
		}
	}
);
