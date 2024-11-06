/**
 * External dependencies
 */
import { test, expect, Page, Browser } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../utils/shopper';
import { config } from '../../config/default';
import { getAnonymousShopper, getMerchant } from '../../utils/helpers';
import { goToOrder, goToPaymentDetails } from '../../utils/merchant-navigation';

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
			await shopperPage.goto( '/cart/' );
			await shopper.addCartProduct( shopperPage );

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
						merchantPage.getByText(
							'This dispute was accepted and lost'
						)
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
				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step(
				'Confirm the expected challenge form sections are visible',
				async () => {
					await expect(
						merchantPage.getByText( 'General evidence', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						merchantPage.getByText( 'Shipping information', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						merchantPage
							.getByText( 'Additional details', {
								exact: true,
							} )
							.first()
					).toBeVisible();
				}
			);

			await test.step(
				'Fill in the additional details field with the `winning_evidence` text',
				async () => {
					await merchantPage
						.getByLabel( 'Additional details' )
						.fill( 'winning_evidence' );
				}
			);

			await test.step(
				'Submit the evidence and accept the dialog',
				async () => {
					// Prepare to accept the dialog before clicking the submit button
					merchantPage.on( 'dialog', ( dialog ) => dialog.accept() );

					// Click the submit button
					await merchantPage
						.getByRole( 'button', {
							name: 'Submit evidence',
						} )
						.click();

					// Wait for the dispute list page to load.
					await expect(
						merchantPage
							.getByRole( 'heading', {
								name: 'Disputes',
							} )
							.first()
					).toBeVisible();
				}
			);

			await test.step(
				'Navigate to the payment details screen and confirm the dispute status is Won',
				async () => {
					await merchantPage.goto( paymentDetailsLink );

					await expect(
						merchantPage.getByText( 'Disputed: Won', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						merchantPage.getByText(
							'Good news! You won this dispute'
						)
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
				await merchantPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step(
				'Fill in the additional details field with the `losing_evidence` text',
				async () => {
					await merchantPage
						.getByLabel( 'Additional details', {
							exact: true,
						} )
						.fill( 'losing_evidence' );
				}
			);

			await test.step(
				'Submit the evidence and accept the dialog',
				async () => {
					// Prepare to accept the dialog before clicking the submit button
					merchantPage.on( 'dialog', ( dialog ) => dialog.accept() );

					// Click the submit button
					await merchantPage
						.getByRole( 'button', {
							name: 'Submit evidence',
						} )
						.click();

					// Wait for the dispute list page to load.
					await expect(
						merchantPage
							.getByRole( 'heading', {
								name: 'Disputes',
							} )
							.first()
					).toBeVisible();
				}
			);

			await test.step(
				'Navigate to the payment details screen and confirm the dispute status is Lost',
				async () => {
					await merchantPage.goto( paymentDetailsLink );

					await expect(
						merchantPage.getByText( 'Disputed: Lost', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						merchantPage.getByText( 'This dispute was lost' )
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

		await test.step( 'Select the product type', async () => {
			await merchantPage
				.getByTestId( 'dispute-challenge-product-type-selector' )
				.selectOption( 'offline_service' );

			await expect(
				merchantPage.getByTestId(
					'dispute-challenge-product-type-selector'
				)
			).toHaveValue( 'offline_service' );
		} );

		await test.step( 'Save the dispute challenge for later', async () => {
			await merchantPage
				.getByRole( 'button', {
					name: 'Save for later',
				} )
				.click();

			// Wait for the redirect to the dispute list page.
			await expect(
				merchantPage
					.getByRole( 'heading', {
						name: 'Disputes',
					} )
					.first()
			).toBeVisible();
		} );

		await test.step(
			'Navigate to the payment details screen and click the challenge dispute button',
			async () => {
				await merchantPage.goto( paymentDetailsLink );

				await merchantPage
					.getByTestId( 'challenge-dispute-button' )
					.click();
			}
		);

		await test.step(
			'Verify the previously selected challenge product type is saved',
			async () => {
				await expect(
					merchantPage.getByTestId(
						'dispute-challenge-product-type-selector'
					)
				).toHaveValue( 'offline_service' );
			}
		);
	} );
} );
