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
 * Navigates to the payment details page for a given disputed order.
 */
async function goToPaymentDetailsForOrder(
	/** The merchant page object. */
	merchantPage: Page,
	/** The ID of the disputed order. */
	orderId: string
): Promise< string > {
	const paymentDetailsLink = await test.step(
		'Navigate to the payment details page',
		async () => {
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
		}
	);

	return paymentDetailsLink;
}

async function createDisputedOrder( browser: Browser ) {
	const { shopperPage } = await getAnonymousShopper( browser );

	const orderId = await test.step(
		'Place an order as shopper, to be automatically disputed',
		async () => {
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
		}
	);

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

			await test.step(
				'Click the dispute accept button to open the accept dispute modal',
				async () => {
					// View the modal.
					await merchantPage
						.getByRole( 'button', {
							name: 'Accept dispute',
						} )
						.click();
				}
			);

			await test.step(
				'Click the accept dispute button to accept the dispute',
				async () => {
					await merchantPage
						.getByTestId( 'accept-dispute-button' )
						.click();
				}
			);

			await test.step(
				'Wait for the accept request to resolve and observe the lost dispute status',
				async () => {
					expect(
						merchantPage.getByText( 'Disputed: Lost' )
					).toBeVisible();

					// Check the dispute details footer
					expect(
						merchantPage.getByText( 'You accepted this dispute on' )
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been accepted',
				async () => {
					await expect(
						merchantPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						merchantPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
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

			await test.step(
				'Click the challenge dispute button to navigate to the challenge dispute page',
				async () => {
					await merchantPage
						.getByRole( 'button', {
							name: 'Challenge dispute',
						} )
						.click();
				}
			);

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

			await test.step(
				'Confirm the expected stepper steps are visible',
				async () => {
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
						.getByLabel( 'PRODUCT DESCRIPTION' )
						.fill( 'my product description' );
				}
			);

			await test.step(
				'Navigate to the next step (Shipping details)',
				async () => {
					await merchantPage
						.getByRole( 'button', {
							name: 'Next',
						} )
						.click();
				}
			);

			await test.step(
				'Confirm we are on the shipping details step',
				async () => {
					await expect(
						merchantPage.getByText( 'Add your shipping details', {
							exact: true,
						} )
					).toBeVisible();
				}
			);

			await test.step( 'Navigate to the review step', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step(
				'Confirm we are on the review step and submit the evidence',
				async () => {
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
						.getByRole( 'button', {
							name: 'Submit',
						} )
						.click();
				}
			);

			await test.step(
				'Wait for the confirmation screen to appear',
				async () => {
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
				}
			);

			await test.step(
				'Navigate back to payment details and confirm the dispute status is Won',
				async () => {
					// Navigate back to the payment details page
					await merchantPage.goto( paymentDetailsLink );

					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Won' } )
					).toBeVisible();

					await expect(
						merchantPage.getByText(
							"Good news — you've won this dispute!"
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been submitted',
				async () => {
					await expect(
						merchantPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						merchantPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
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

			await test.step(
				'Click the challenge dispute button to navigate to the challenge dispute page',
				async () => {
					await merchantPage
						.getByRole( 'button', {
							name: 'Challenge dispute',
						} )
						.click();
				}
			);

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

			await test.step(
				'Navigate to the next step (Shipping details)',
				async () => {
					await merchantPage
						.getByRole( 'button', {
							name: 'Next',
						} )
						.click();
				}
			);

			await test.step(
				'Confirm we are on the shipping details step',
				async () => {
					await expect(
						merchantPage.getByText( 'Add your shipping details', {
							exact: true,
						} )
					).toBeVisible();
				}
			);

			await test.step( 'Navigate to the review step', async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step(
				'Confirm we are on the review step and submit the evidence',
				async () => {
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
						.getByRole( 'button', {
							name: 'Submit',
						} )
						.click();
				}
			);

			await test.step(
				'Wait for the confirmation screen to appear',
				async () => {
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
				}
			);

			await test.step(
				'Navigate back to payment details and confirm the dispute status is Lost',
				async () => {
					// Navigate back to the payment details page
					await merchantPage.goto( paymentDetailsLink );

					await expect(
						merchantPage
							.locator( '.payment-details-summary__status' )
							.filter( { hasText: 'Disputed: Lost' } )
					).toBeVisible();

					await expect(
						merchantPage.getByText(
							"Unfortunately, you've lost this dispute"
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been submitted',
				async () => {
					await expect(
						merchantPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						merchantPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
		}
	);

	test( 'Save a dispute challenge without submitting evidence', async ( {
		browser,
	} ) => {
		const { merchantPage } = await getMerchant( browser );

		const orderId = await createDisputedOrder( browser );

		const paymentDetailsLink = await goToPaymentDetailsForOrder(
			merchantPage,
			orderId
		);

		await test.step(
			'Click the challenge dispute button to navigate to the challenge dispute page',
			async () => {
				await merchantPage
					.getByRole( 'button', {
						name: 'Challenge dispute',
					} )
					.click();
			}
		);

		// wait for the customer details to be visible
		await test.step(
			'Wait for the customer details to be visible',
			async () => {
				await expect(
					merchantPage.getByText( 'Customer details', {
						exact: true,
					} )
				).toBeVisible();
			}
		);

		await test.step(
			'Confirm we are on the challenge dispute page',
			async () => {
				await expect(
					merchantPage.getByText( "Let's gather the basics", {
						exact: true,
					} )
				).toBeVisible();
			}
		);

		await test.step(
			'Fill in the product type and product description',
			async () => {
				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'offline_service' );
				await merchantPage
					.getByLabel( 'PRODUCT DESCRIPTION' )
					.fill( 'my product description' );
			}
		);

		await test.step( 'Save the dispute challenge for later', async () => {
			await merchantPage
				.getByRole( 'button', {
					name: 'Save for later',
				} )
				.click();
		} );

		await test.step( 'Go back to the payment details page', async () => {
			await merchantPage.goto( paymentDetailsLink );
		} );

		await test.step(
			'Navigate to the payment details screen and click the challenge dispute button',
			async () => {
				await merchantPage
					.getByTestId( 'challenge-dispute-button' )
					.click();
			}
		);

		await test.step(
			'Verify the previously selected challenge product type is saved',
			async () => {
				await test.step(
					'Confirm we are on the challenge dispute page',
					async () => {
						await expect(
							merchantPage.getByText( "Let's gather the basics", {
								exact: true,
							} )
						).toBeVisible();
					}
				);

				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.waitFor( { timeout: 5000, state: 'visible' } );

				await expect(
					merchantPage.getByTestId(
						'dispute-challenge-product-type-selector'
					)
				).toHaveValue( 'offline_service' );
			}
		);
	} );
} );
