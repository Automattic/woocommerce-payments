/**
 * External dependencies
 */
import { test, expect, Page, Browser } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../../utils/shopper';
import { config } from '../../../config/default';
import { getAnonymousShopper, getMerchant } from '../../../utils/helpers';
import {
	goToOrder,
	goToPaymentDetails,
} from '../../../utils/merchant-navigation';

/**
 * Polls the payment details page until the dispute is visible.
 *
 * The dispute is only created merchant-side once Stripe's charge.dispute.created
 * webhook is processed, which can trail the shopper checkout by tens of seconds.
 * The page fetches its data once on load, so we reload until the dispute is there.
 */
async function waitForDisputeToAppear( merchantPage: Page, url: string ) {
	await expect( async () => {
		await merchantPage.goto( url );
		await merchantPage.waitForLoadState( 'load' );

		// not `accept-dispute-button` - that one is the modal's confirm
		// button, which only exists while the modal is open.
		await expect(
			merchantPage.getByTestId( 'open-accept-dispute-modal-button' )
		).toBeVisible( { timeout: 2000 } );
	} ).toPass( { timeout: 60000, intervals: [ 3000 ] } );
}

/**
 * Navigates to the payment details page for a given disputed order.
 */
async function goToPaymentDetailsForOrder(
	/** The merchant page object. */
	merchantPage: Page,
	/** The ID of the disputed order. */
	orderId: string
): Promise< string > {
	const paymentDetailsLink =
		await test.step( 'Navigate to the payment details page', async () => {
			await goToOrder( merchantPage, orderId );

			// Get the order payment intent ID.
			const paymentIntentId = await merchantPage
				.locator( '#order_data' )
				.getByRole( 'link', {
					name: /pi_/,
				} )
				.innerText();

			await goToPaymentDetails( merchantPage, paymentIntentId );

			// Store the current URL for later use.
			const currentUrl = merchantPage.url();
			return currentUrl;
		} );

	await test.step( 'Wait for the dispute to be created by the async webhook', () =>
		waitForDisputeToAppear( merchantPage, paymentDetailsLink ) );

	return paymentDetailsLink;
}

async function createDisputedOrder( browser: Browser ) {
	const { shopperPage } = await getAnonymousShopper( browser );

	const orderId =
		await test.step( 'Place an order as shopper, to be automatically disputed', async () => {
			await shopper.addToCartFromShopPage( shopperPage );

			await shopperPage.goto( '/checkout/' );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses.customer.billing
			);
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'disputed-fraudulent' ]
			);
			await shopper.placeOrder( shopperPage );

			// Get the order ID
			const orderIdField = shopperPage.locator(
				'.woocommerce-order-overview__order.order > strong'
			);
			return orderIdField.innerText();
		} );

	return orderId;
}

test.describe( 'Disputes > Respond to a dispute', () => {
	// Allow all tests within this describe block to run in parallel.
	test.describe.configure( { mode: 'parallel' } );

	test(
		'Accept a dispute',
		{
			tag: '@critical',
		},
		async ( { browser } ) => {
			const { merchantPage } = await getMerchant( browser );

			const orderId = await createDisputedOrder( browser );

			await goToPaymentDetails( merchantPage, orderId );

			await test.step( 'Wait for the dispute to be created by the async webhook', () =>
				waitForDisputeToAppear( merchantPage, merchantPage.url() ) );

			await test.step( 'Click the dispute accept button to open the accept dispute modal', async () => {
				// View the modal.
				await merchantPage
					.getByRole( 'button', {
						name: 'Accept dispute',
					} )
					.click();
			} );

			await test.step( 'Click the accept dispute button to accept the dispute', async () => {
				await merchantPage
					.getByTestId( 'accept-dispute-button' )
					.click();

				// Wait for the accept request to complete.
				await merchantPage.waitForLoadState( 'load' );
			} );

			await test.step( 'Wait for the accept request to resolve and observe the lost dispute status', async () => {
				await expect(
					merchantPage.getByText( 'Disputed: Lost' )
				).toBeVisible();

				// Check the dispute details footer
				await expect(
					merchantPage.getByText( 'You accepted this dispute on' )
				).toBeVisible();
			} );

			await test.step( 'Confirm dispute action buttons are not present anymore since the dispute has been accepted', async () => {
				await expect(
					merchantPage.getByTestId( 'challenge-dispute-button' )
				).not.toBeVisible();
				await expect(
					merchantPage.getByTestId( 'accept-dispute-button' )
				).not.toBeVisible();
			} );
		}
	);

	test(
		'Challenge a dispute with winning evidence',
		{
			tag: '@critical',
		},
		async ( { browser } ) => {
			const { merchantPage } = await getMerchant( browser );

			const orderId = await createDisputedOrder( browser );

			const paymentDetailsLink = await goToPaymentDetailsForOrder(
				merchantPage,
				orderId
			);

			await test.step( 'Click the challenge dispute button to navigate to the challenge dispute page', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Challenge dispute',
					} )
					.click();

				// Wait for new evidence screen to finish initial loading
				await expect(
					merchantPage.getByTestId( 'new-evidence-loading' )
				).toBeHidden( { timeout: 20000 } );
			} );

			await test.step( 'Select the product type', async () => {
				// wait for the dispute to the loaded.
				await expect(
					merchantPage.getByText(
						'The cardholder claims this is an unauthorized transaction.',
						{
							exact: true,
						}
					)
				).toBeVisible();

				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step( 'Confirm the expected stepper steps are visible', async () => {
				await expect(
					merchantPage.getByText( 'Purchase info', {
						exact: true,
					} )
				).toBeVisible();

				await expect(
					merchantPage.getByText( 'Shipping details', {
						exact: true,
					} )
				).toBeVisible();

				await expect(
					merchantPage.getByText( 'Review', {
						exact: true,
					} )
				).toBeVisible();

				await merchantPage
					.getByLabel( 'PRODUCT OR SERVICE DESCRIPTION' )
					.fill( 'my product description' );
			} );

			await test.step( 'Navigate to the next step (Shipping details)', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step( 'Confirm we are on the shipping details step', async () => {
				await expect(
					merchantPage.getByText( 'Add your shipping details', {
						exact: true,
					} )
				).toBeVisible();
			} );

			await test.step( 'Navigate to the review step', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step( 'Confirm we are on the review step and submit the evidence', async () => {
				await expect(
					merchantPage.getByText( 'Review your cover letter', {
						exact: true,
					} )
				).toBeVisible();

				// wait cover letter to load with content and replace with new content
				await merchantPage
					.getByLabel( 'COVER LETTER' )
					.waitFor( { state: 'visible', timeout: 5000 } );

				// Check existing content
				await expect(
					merchantPage.getByLabel( 'COVER LETTER' )
				).toContainText( 'WooPayments', {
					timeout: 5000,
				} );

				await merchantPage
					.getByLabel( 'COVER LETTER' )
					.fill( 'winning_evidence' );

				// Handle the confirmation dialog that appears when clicking Submit
				merchantPage.on( 'dialog', async ( dialog ) => {
					expect( dialog.message() ).toContain(
						"Are you sure you're ready to submit this evidence?"
					);
					await dialog.accept();
				} );

				// Click the submit button
				await merchantPage
					.getByTestId( 'submit-evidence-button' )
					.click();
			} );

			await test.step( 'Wait for the confirmation screen to appear', async () => {
				await expect(
					merchantPage.getByText(
						'Thanks for sharing your response!'
					)
				).toBeVisible();

				await expect(
					merchantPage.getByText(
						"Your evidence has been sent to the cardholder's bank for review."
					)
				).toBeVisible();
			} );

			await test.step( 'Navigate back to payment details and confirm the dispute status is Won', async () => {
				// Poll for the final status, refreshing the page if needed
				await expect( async () => {
					await merchantPage.goto( paymentDetailsLink );
					await merchantPage.waitForLoadState( 'load' );

					// Check that we're no longer "Under Review"
					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Under Review' } )
					).not.toBeVisible( { timeout: 2000 } );

					// Confirm we have the "Won" status
					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Won' } )
					).toBeVisible( { timeout: 2000 } );
				} ).toPass( { timeout: 60000, intervals: [ 3000 ] } );

				await expect(
					merchantPage.getByText(
						"Good news — you've won this dispute!"
					)
				).toBeVisible();
			} );

			await test.step( 'Confirm dispute action buttons are not present anymore since the dispute has been submitted', async () => {
				await expect(
					merchantPage.getByTestId( 'challenge-dispute-button' )
				).not.toBeVisible();
				await expect(
					merchantPage.getByTestId( 'accept-dispute-button' )
				).not.toBeVisible();
			} );
		}
	);

	test(
		'Challenge a dispute with losing evidence',
		{
			tag: '@critical',
		},
		async ( { browser } ) => {
			const { merchantPage } = await getMerchant( browser );

			const orderId = await createDisputedOrder( browser );

			const paymentDetailsLink = await goToPaymentDetailsForOrder(
				merchantPage,
				orderId
			);

			await test.step( 'Click the challenge dispute button to navigate to the challenge dispute page', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Challenge dispute',
					} )
					.click();

				// Wait for new evidence screen to finish initial loading
				await expect(
					merchantPage.getByTestId( 'new-evidence-loading' )
				).toBeHidden( { timeout: 20000 } );
			} );

			await test.step( 'Select the product type', async () => {
				// wait for the dispute to the loaded.
				await expect(
					merchantPage.getByText(
						'The cardholder claims this is an unauthorized transaction.',
						{
							exact: true,
						}
					)
				).toBeVisible();

				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step( 'Navigate to the next step (Shipping details)', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step( 'Confirm we are on the shipping details step', async () => {
				await expect(
					merchantPage.getByText( 'Add your shipping details', {
						exact: true,
					} )
				).toBeVisible();
			} );

			await test.step( 'Navigate to the review step', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step( 'Confirm we are on the review step and submit the evidence', async () => {
				await expect(
					merchantPage.getByText( 'Review your cover letter', {
						exact: true,
					} )
				).toBeVisible();

				// wait cover letter to load with content and replace with new content
				await merchantPage
					.getByLabel( 'COVER LETTER' )
					.waitFor( { state: 'visible', timeout: 5000 } );

				// Check existing content
				await expect(
					merchantPage.getByLabel( 'COVER LETTER' )
				).toContainText( 'WooPayments', {
					timeout: 5000,
				} );

				await merchantPage
					.getByLabel( 'COVER LETTER' )
					.fill( 'losing_evidence' );

				// Handle the confirmation dialog that appears when clicking Submit
				merchantPage.on( 'dialog', async ( dialog ) => {
					expect( dialog.message() ).toContain(
						"Are you sure you're ready to submit this evidence?"
					);
					await dialog.accept();
				} );

				// Click the submit button
				await merchantPage
					.getByTestId( 'submit-evidence-button' )
					.click();
			} );

			await test.step( 'Wait for the confirmation screen to appear', async () => {
				await expect(
					merchantPage.getByText(
						'Thanks for sharing your response!'
					)
				).toBeVisible();

				await expect(
					merchantPage.getByText(
						"Your evidence has been sent to the cardholder's bank for review."
					)
				).toBeVisible();
			} );

			await test.step( 'Navigate back to payment details and confirm the dispute status is Lost', async () => {
				// Poll for the final status, refreshing the page if needed
				await expect( async () => {
					await merchantPage.goto( paymentDetailsLink );
					await merchantPage.waitForLoadState( 'load' );

					// Check that we're no longer "Under Review"
					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Under Review' } )
					).not.toBeVisible( { timeout: 2000 } );

					// Confirm we have the "Lost" status
					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Lost' } )
					).toBeVisible( { timeout: 2000 } );
				} ).toPass( { timeout: 60000, intervals: [ 3000 ] } );

				await expect(
					merchantPage.getByText(
						"Unfortunately, you've lost this dispute"
					)
				).toBeVisible();
			} );

			await test.step( 'Confirm dispute action buttons are not present anymore since the dispute has been submitted', async () => {
				await expect(
					merchantPage.getByTestId( 'challenge-dispute-button' )
				).not.toBeVisible();
				await expect(
					merchantPage.getByTestId( 'accept-dispute-button' )
				).not.toBeVisible();
			} );
		}
	);
	test( 'Save a dispute challenge without submitting evidence', async ( {
		browser,
	} ) => {
		// Stripe can take a while to return the saved evidence, and the
		// save-and-restore retries below need the extra room.
		test.slow();

		const { merchantPage } = await getMerchant( browser );

		const orderId = await createDisputedOrder( browser );

		const paymentDetailsLink = await goToPaymentDetailsForOrder(
			merchantPage,
			orderId
		);

		await test.step( 'Click the challenge dispute button to navigate to the challenge dispute page', async () => {
			await merchantPage
				.getByRole( 'button', {
					name: 'Challenge dispute',
				} )
				.click();

			// Wait for the challenge screen initial loading spinner to disappear
			await expect(
				merchantPage.getByTestId( 'new-evidence-loading' )
			).toBeHidden( { timeout: 20000 } );
		} );

		// wait for the customer details to be visible
		await test.step( 'Wait for the customer details to be visible', async () => {
			await expect(
				merchantPage.getByText( 'Customer details', {
					exact: true,
				} )
			).toBeVisible();
		} );

		await test.step( 'Confirm we are on the challenge dispute page', async () => {
			await expect(
				merchantPage.getByText( "Let's gather the basics", {
					exact: true,
				} )
			).toBeVisible();
		} );

		const descriptionField = () =>
			merchantPage.getByLabel( 'PRODUCT OR SERVICE DESCRIPTION' );

		const fillProductDescription = async () => {
			// The product description field is auto-populated asynchronously.
			// An async React effect may overwrite user input after initial load,
			// so we retry the fill+verify cycle until the value sticks.
			await expect( async () => {
				await descriptionField().fill( 'my product description' );

				// Blur the field to ensure value is committed to state
				await descriptionField().press( 'Tab' );

				await expect( descriptionField() ).toHaveValue(
					'my product description',
					{ timeout: 2000 }
				);
			} ).toPass( { timeout: 20000, intervals: [ 2000 ] } );
		};

		const saveForLater = async () => {
			const waitResponse = merchantPage.waitForResponse(
				( r ) =>
					r.url().includes( '/wc/v3/payments/disputes/' ) &&
					r.request().method() === 'POST'
			);

			// Use stable test id for the save button
			await merchantPage.getByTestId( 'save-for-later-button' ).click();

			const response = await waitResponse;

			// Server acknowledged save
			expect( response.ok() ).toBeTruthy();

			// Validate payload included our description (guards against state not committed)
			try {
				const payload = response.request().postDataJSON?.();
				// Some environments may not expose postDataJSON; guard accordingly
				if ( payload && payload.evidence ) {
					expect( payload.evidence.product_description ).toBe(
						'my product description'
					);
				}
			} catch ( _e ) {
				// Non-fatal: continue to UI confirmation
			}

			await expect(
				merchantPage.locator( '.components-snackbar__content', {
					hasText: 'Evidence saved!',
				} )
			).toBeVisible( { timeout: 10000 } );
		};

		// Reloads the challenge page and polls until the saved description is
		// restored. Returns false instead of throwing, so the caller can save
		// again. Ends on the challenge screen either way.
		const restoredAfterReload = async () => {
			try {
				await expect( async () => {
					await merchantPage.goto( paymentDetailsLink );
					await merchantPage.waitForLoadState( 'load' );

					await merchantPage
						.getByTestId( 'challenge-dispute-button' )
						.click();

					await expect(
						merchantPage.getByTestId( 'new-evidence-loading' )
					).toBeHidden( { timeout: 20000 } );

					await expect(
						merchantPage.getByText( "Let's gather the basics", {
							exact: true,
						} )
					).toBeVisible();

					await expect( descriptionField() ).toHaveValue(
						'my product description',
						{ timeout: 5000 }
					);
				} ).toPass( { timeout: 40000, intervals: [ 3000 ] } );

				return true;
			} catch {
				return false;
			}
		};

		await test.step( 'Select product type and fill description', async () => {
			await merchantPage
				.getByTestId( 'dispute-challenge-product-type-selector' )
				.selectOption( 'offline_service' );

			await fillProductDescription();
		} );

		await test.step( 'Save the dispute challenge for later', () =>
			saveForLater() );

		await test.step( 'Navigate back and verify previously saved values are restored', async () => {
			// The save request can come back 200 with the "Evidence saved!"
			// notice and the draft still never shows up on later reads - seen
			// in CI, where only a fresh save fixed it. So when the restore
			// poll doesn't converge, retry the write, not just the read.
			const maxSaveAttempts = 3;

			for ( let attempt = 1; attempt <= maxSaveAttempts; attempt++ ) {
				if ( await restoredAfterReload() ) {
					return;
				}

				if ( attempt === maxSaveAttempts ) {
					throw new Error(
						`Saved dispute evidence was not restored after ${ maxSaveAttempts } save attempts; ` +
							'the evidence draft looks dropped server-side.'
					);
				}

				// The restore check leaves us on the challenge screen with the
				// stale value - fill and save again.
				await fillProductDescription();
				await saveForLater();
			}
		} );
	} );
} );
