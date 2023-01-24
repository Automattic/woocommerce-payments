// Waits for the element to exist as in the Blocks checkout, sometimes the field is not immediately available.
export const getTargetElement = ( selector ) => {
	return new Promise( ( resolve ) => {
		if ( document.querySelector( selector ) ) {
			return resolve( document.querySelector( selector ) );
		}

		const checkoutBlock = document.querySelector(
			'[data-block-name="woocommerce/checkout"]'
		);

		if ( ! checkoutBlock ) {
			return resolve( null );
		}

		const observer = new MutationObserver( ( mutationList, obs ) => {
			if ( document.querySelector( selector ) ) {
				resolve( document.querySelector( selector ) );
				obs.disconnect();
			}
		} );

		observer.observe( checkoutBlock, {
			childList: true,
			subtree: true,
		} );
	} );
};
