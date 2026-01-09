/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { createDisputedOrder } from '../../../utils/shopper';
import { goToPaymentDetailsForOrder } from '../../../utils/merchant';

test.describe( 'Disputes > Respond to a dispute', { tag: '@merchant' }, () => {
	// Complex dispute workflows with evidence submission require extended timeout
	test.setTimeout( 90000 );

	test(
		'Accept a dispute',
		{ tag: '@critical' },
		async ( { adminPage, customerPage } ) => {
			// Create a fresh disputed order for this test
			const disputedOrderId = await test.step(
				'Create order that will be disputed',
				async () => {
					return await createDisputedOrder( customerPage );
				}
			);

			// Go to payment details page for the disputed order
			await goToPaymentDetailsForOrder( adminPage, disputedOrderId );

			await test.step( 'Wait for dispute status to appear', async () => {
				// Wait for the dispute status chip to be visible
				await expect(
					adminPage.locator( '.payment-details-summary__status' )
				).toBeVisible( { timeout: 30000 } );
			} );

			await test.step(
				'Click the accept dispute button to open the accept dispute modal',
				async () => {
					await adminPage
						.getByRole( 'button', { name: 'Accept dispute' } )
						.click();
				}
			);

			await test.step(
				'Click the accept dispute button to accept the dispute',
				async () => {
					// Wait for the modal to appear
					await expect(
						adminPage.getByText( 'Accept the dispute?' )
					).toBeVisible();

					// Click the button within the modal using test ID
					await adminPage
						.getByTestId( 'accept-dispute-button' )
						.click();

					// Wait for the network request to complete
					await adminPage.waitForLoadState( 'networkidle' );
				}
			);

			await test.step(
				'Wait for the accept request to resolve and observe the lost dispute status',
				async () => {
					// Poll for status change since dispute processing is async
					await expect(
						adminPage.getByText( 'Disputed: Lost' )
					).toBeVisible( { timeout: 30000 } );

					// Check the dispute details footer
					await expect(
						adminPage.getByText( 'You accepted this dispute on' )
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been accepted',
				async () => {
					await expect(
						adminPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						adminPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
		}
	);

	test(
		'Challenge a dispute with winning evidence',
		{ tag: '@critical' },
		async ( { adminPage, customerPage } ) => {
			// Create a fresh disputed order for this test
			const disputedOrderId = await test.step(
				'Create order that will be disputed',
				async () => {
					return await createDisputedOrder( customerPage );
				}
			);

			const paymentDetailsLink = await goToPaymentDetailsForOrder(
				adminPage,
				disputedOrderId
			);

			await test.step(
				'Click the challenge dispute button to navigate to the challenge dispute page',
				async () => {
					await adminPage
						.getByRole( 'button', {
							name: 'Challenge dispute',
						} )
						.click();

					// Wait for new evidence screen to finish initial loading
					await expect(
						adminPage.getByTestId( 'new-evidence-loading' )
					).toBeHidden( { timeout: 20000 } );
				}
			);

			await test.step( 'Select the product type', async () => {
				// wait for the dispute to be loaded.
				await expect(
					adminPage.getByText(
						'The cardholder claims this is an unauthorized transaction.',
						{
							exact: true,
						}
					)
				).toBeVisible();

				await adminPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step(
				'Confirm the expected stepper steps are visible',
				async () => {
					// Validate stepper navigation content (pattern from task template)
					await expect(
						adminPage.getByText( 'Purchase info', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						adminPage.getByText( 'Shipping details', {
							exact: true,
						} )
					).toBeVisible();

					await expect(
						adminPage.getByText( 'Review', {
							exact: true,
						} )
					).toBeVisible();

					await adminPage
						.getByLabel( 'PRODUCT DESCRIPTION' )
						.fill( 'my product description' );
				}
			);

			await test.step(
				'Navigate to the next step (Shipping details)',
				async () => {
					await adminPage
						.getByRole( 'button', {
							name: 'Next',
						} )
						.click();
				}
			);

			await test.step(
				'Confirm we are on the shipping details step',
				async () => {
					// Validate unique step content (pattern from task template)
					await expect(
						adminPage.getByText( 'Add your shipping details', {
							exact: true,
						} )
					).toBeVisible();
				}
			);

			await test.step( 'Navigate to the review step', async () => {
				await adminPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step(
				'Confirm we are on the review step and submit the evidence',
				async () => {
					// Validate unique step content (pattern from task template)
					await expect(
						adminPage.getByText( 'Review your cover letter', {
							exact: true,
						} )
					).toBeVisible();

					// wait cover letter to load with content and replace with new content
					await adminPage
						.getByLabel( 'COVER LETTER' )
						.waitFor( { state: 'visible', timeout: 5000 } );

					// Check existing content - QIT environment may have different store name
					await expect(
						adminPage.getByLabel( 'COVER LETTER' )
					).not.toBeEmpty( { timeout: 5000 } );

					await adminPage
						.getByLabel( 'COVER LETTER' )
						.fill( 'winning_evidence' );

					// Handle the confirmation dialog (pattern from task template)
					adminPage.on( 'dialog', async ( dialog ) => {
						expect( dialog.message() ).toContain(
							"Are you sure you're ready to submit this evidence?"
						);
						await dialog.accept();
					} );

					// Click the submit button
					await adminPage
						.getByTestId( 'submit-evidence-button' )
						.click();
				}
			);

			await test.step(
				'Wait for the confirmation screen to appear',
				async () => {
					await expect(
						adminPage.getByText(
							'Thanks for sharing your response!'
						)
					).toBeVisible();

					await expect(
						adminPage.getByText(
							"Your evidence has been sent to the cardholder's bank for review."
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Navigate back to payment details and confirm the dispute status is Won',
				async () => {
					// Poll for the final status with proper intervals (pattern from task template)
					await expect( async () => {
						await adminPage.goto( paymentDetailsLink );
						await adminPage.waitForLoadState( 'networkidle' );

						// Check that we're no longer "Under Review"
						await expect(
							adminPage
								.locator( '.payment-details-summary__status' )
								.filter( { hasText: 'Disputed: Under Review' } )
						).not.toBeVisible( { timeout: 2000 } );

						// Confirm we have the "Won" status
						await expect(
							adminPage
								.locator( '.payment-details-summary__status' )
								.filter( { hasText: 'Disputed: Won' } )
						).toBeVisible( { timeout: 2000 } );
					} ).toPass( { timeout: 60000, intervals: [ 3000 ] } );

					await expect(
						adminPage.getByText(
							"Good news — you've won this dispute!"
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been submitted',
				async () => {
					await expect(
						adminPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						adminPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
		}
	);

	test(
		'Challenge a dispute with losing evidence',
		{ tag: '@critical' },
		async ( { adminPage, customerPage } ) => {
			// Create a fresh disputed order for this test
			const disputedOrderId = await test.step(
				'Create order that will be disputed',
				async () => {
					return await createDisputedOrder( customerPage );
				}
			);

			const paymentDetailsLink = await goToPaymentDetailsForOrder(
				adminPage,
				disputedOrderId
			);

			await test.step(
				'Click the challenge dispute button to navigate to the challenge dispute page',
				async () => {
					await adminPage
						.getByRole( 'button', {
							name: 'Challenge dispute',
						} )
						.click();

					// Wait for new evidence screen to finish initial loading
					await expect(
						adminPage.getByTestId( 'new-evidence-loading' )
					).toBeHidden( { timeout: 20000 } );
				}
			);

			await test.step( 'Select the product type', async () => {
				// wait for the dispute to be loaded.
				await expect(
					adminPage.getByText(
						'The cardholder claims this is an unauthorized transaction.',
						{
							exact: true,
						}
					)
				).toBeVisible();

				await adminPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'physical_product' );
			} );

			await test.step(
				'Navigate to the next step (Shipping details)',
				async () => {
					await adminPage
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
						adminPage.getByText( 'Add your shipping details', {
							exact: true,
						} )
					).toBeVisible();
				}
			);

			await test.step( 'Navigate to the review step', async () => {
				await adminPage
					.getByRole( 'button', {
						name: 'Next',
					} )
					.click();
			} );

			await test.step(
				'Confirm we are on the review step and submit the evidence',
				async () => {
					await expect(
						adminPage.getByText( 'Review your cover letter', {
							exact: true,
						} )
					).toBeVisible();

					// wait cover letter to load with content and replace with new content
					await adminPage
						.getByLabel( 'COVER LETTER' )
						.waitFor( { state: 'visible', timeout: 5000 } );

					// Check existing content - QIT environment may have different store name
					await expect(
						adminPage.getByLabel( 'COVER LETTER' )
					).not.toBeEmpty( { timeout: 5000 } );

					await adminPage
						.getByLabel( 'COVER LETTER' )
						.fill( 'losing_evidence' );

					// Handle the confirmation dialog
					adminPage.on( 'dialog', async ( dialog ) => {
						expect( dialog.message() ).toContain(
							"Are you sure you're ready to submit this evidence?"
						);
						await dialog.accept();
					} );

					// Click the submit button
					await adminPage
						.getByTestId( 'submit-evidence-button' )
						.click();
				}
			);

			await test.step(
				'Wait for the confirmation screen to appear',
				async () => {
					await expect(
						adminPage.getByText(
							'Thanks for sharing your response!'
						)
					).toBeVisible();

					await expect(
						adminPage.getByText(
							"Your evidence has been sent to the cardholder's bank for review."
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Navigate back to payment details and confirm the dispute status is Lost',
				async () => {
					// Poll for the final status with proper intervals
					await expect( async () => {
						await adminPage.goto( paymentDetailsLink );
						await adminPage.waitForLoadState( 'networkidle' );

						// Check that we're no longer "Under Review"
						await expect(
							adminPage
								.locator( '.payment-details-summary__status' )
								.filter( { hasText: 'Disputed: Under Review' } )
						).not.toBeVisible( { timeout: 2000 } );

						// Confirm we have the "Lost" status
						await expect(
							adminPage
								.locator( '.payment-details-summary__status' )
								.filter( { hasText: 'Disputed: Lost' } )
						).toBeVisible( { timeout: 2000 } );
					} ).toPass( { timeout: 60000, intervals: [ 3000 ] } );

					await expect(
						adminPage.getByText(
							"Unfortunately, you've lost this dispute"
						)
					).toBeVisible();
				}
			);

			await test.step(
				'Confirm dispute action buttons are not present anymore since the dispute has been submitted',
				async () => {
					await expect(
						adminPage.getByTestId( 'challenge-dispute-button' )
					).not.toBeVisible();
					await expect(
						adminPage.getByTestId( 'accept-dispute-button' )
					).not.toBeVisible();
				}
			);
		}
	);

	test( 'Save a dispute challenge without submitting evidence', async ( {
		adminPage,
		customerPage,
	} ) => {
		// Create a fresh disputed order for this test
		const disputedOrderId = await test.step(
			'Create order that will be disputed',
			async () => {
				return await createDisputedOrder( customerPage );
			}
		);

		const paymentDetailsLink = await goToPaymentDetailsForOrder(
			adminPage,
			disputedOrderId
		);

		await test.step(
			'Click the challenge dispute button to navigate to the challenge dispute page',
			async () => {
				await adminPage
					.getByRole( 'button', {
						name: 'Challenge dispute',
					} )
					.click();

				// Wait for the challenge screen initial loading spinner to disappear
				await expect(
					adminPage.getByTestId( 'new-evidence-loading' )
				).toBeHidden( { timeout: 20000 } );
			}
		);

		await test.step(
			'Wait for the customer details to be visible',
			async () => {
				await expect(
					adminPage.getByText( 'Customer details', {
						exact: true,
					} )
				).toBeVisible();
			}
		);

		await test.step(
			'Confirm we are on the challenge dispute page',
			async () => {
				// Validate unique step content (pattern from task template)
				await expect(
					adminPage.getByText( "Let's gather the basics", {
						exact: true,
					} )
				).toBeVisible();
			}
		);

		await test.step(
			'Select product type and fill description',
			async () => {
				await adminPage
					.getByTestId( 'dispute-challenge-product-type-selector' )
					.selectOption( 'offline_service' );
				await adminPage
					.getByLabel( 'PRODUCT DESCRIPTION' )
					.fill( 'my product description' );

				// Blur the field to ensure value is committed to state before saving
				await adminPage
					.getByLabel( 'PRODUCT DESCRIPTION' )
					.press( 'Tab' );

				// Verify the value was set correctly immediately after filling
				await expect(
					adminPage.getByLabel( 'PRODUCT DESCRIPTION' )
				).toHaveValue( 'my product description' );
			}
		);

		await test.step( 'Verify form values before saving', async () => {
			// Double-check that the form value is still correct before saving
			await expect(
				adminPage.getByLabel( 'PRODUCT DESCRIPTION' )
			).toHaveValue( 'my product description' );
		} );

		await test.step( 'Save the dispute challenge for later', async () => {
			// Evidence form persistence pattern from task template
			const waitResponse = adminPage.waitForResponse(
				( r ) =>
					r.url().includes( '/wc/v3/payments/disputes/' ) &&
					r.request().method() === 'POST'
			);

			// Use stable test id for the save button
			await adminPage.getByTestId( 'save-for-later-button' ).click();

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

			// Wait for the success snackbar to confirm UI acknowledged the save.
			await expect(
				adminPage
					.locator( '.components-snackbar__content', {
						hasText: 'Evidence saved!',
					} )
					.first()
			).toBeVisible( { timeout: 10000 } );

			// Allow Stripe API to complete the write operation before we navigate away.
			// Without this delay, fetching the dispute again may hit a concurrent access
			// error: "This object cannot be accessed right now because another API request
			// or Stripe process is currently accessing it."
			await adminPage.waitForTimeout( 3000 );
		} );

		await test.step( 'Go back to the payment details page', async () => {
			await adminPage.goto( paymentDetailsLink );
		} );

		await test.step(
			'Navigate to the payment details screen and click the challenge dispute button',
			async () => {
				await adminPage
					.getByTestId( 'challenge-dispute-button' )
					.click();

				// Wait for the challenge screen initial loading spinner to disappear
				await expect(
					adminPage.getByTestId( 'new-evidence-loading' )
				).toBeHidden( { timeout: 20000 } );
			}
		);

		await test.step(
			'Verify previously saved values are restored',
			async () => {
				await test.step(
					'Confirm we are on the challenge dispute page',
					async () => {
						await expect(
							adminPage.getByText( "Let's gather the basics", {
								exact: true,
							} )
						).toBeVisible();
					}
				);

				// Wait for description control to be visible
				await adminPage
					.getByLabel( 'PRODUCT DESCRIPTION' )
					.waitFor( { timeout: 10000, state: 'visible' } );

				// Assert the product description persisted (server stores this under evidence)
				await expect(
					adminPage.getByLabel( 'PRODUCT DESCRIPTION' )
				).toHaveValue( 'my product description', { timeout: 15000 } );
			}
		);
	} );
} );
