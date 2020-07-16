export async function fillCardDetails( page, card ) {
	const frameHandle = await page.waitForSelector( 'iframe[name^="__privateStripeFrame"]' );
	const stripeFrame = await frameHandle.contentFrame();
	const inputs = await stripeFrame.$$( '.InputElement.Input' );

	const [ cardNumberInput, cardDateInput, cardCvcInput ] = inputs;
	await cardNumberInput.type( card.number, { delay: 20 } );
	await cardDateInput.type( card.expires.month + card.expires.year, { delay: 20 } );
	await cardCvcInput.type( card.cvc, { delay: 20 } );
}
