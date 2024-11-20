/**
 * Normalizes incoming cart total items for use as a displayItems with the Stripe api.
 *
 * @param {Array} displayItems Items to normalize.
 * @param {boolean} pending Whether to mark items as pending or not.
 *
 * @return {Array} An array of PaymentItems
 */
export const normalizeLineItems = ( displayItems ) => {
	return displayItems.map( ( displayItem ) => {
		let amount = displayItem?.amount ?? displayItem?.value;
		if ( displayItem.key === 'total_discount' ) {
			amount = -amount;
		}

		return {
			name: displayItem.label,
			amount,
		};
	} );
};
