/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * Internal dependencies
 */
import SavedCards from 'saved-cards';

const savedCardsContainer = document.getElementById(
	'wcpay-saved-cards-container'
);

if ( savedCardsContainer ) {
	const cards =
		JSON.parse( savedCardsContainer.getAttribute( 'data-cards' ) ) || [];
	const customerId =
		savedCardsContainer.getAttribute( 'data-customer' ) || null;
	ReactDOM.render(
		<SavedCards cards={ cards } customerId={ customerId } />,
		savedCardsContainer
	);
}
