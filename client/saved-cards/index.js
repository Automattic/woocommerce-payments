/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export const SavedCards = ( { cards } ) => {
	return (
		<div class="wcpay-saved-cards">
			<pre>{ JSON.stringify( cards, null, 2 ) }</pre>
		</div>
	);
};

export default SavedCards;
