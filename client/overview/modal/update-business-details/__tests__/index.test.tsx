/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import UpdateBusinessDetailsModal from '..';

// Utility function for accessing the modal content in assertions
const modalContent = () => {
	return document.body.querySelector(
		'.wcpay-update-business-details-modal'
	);
};

const mockErrorMessages = [
	'The provided document was from an unsupported country.',
	'The representative must have an address in the same country as the company.',
];

const mockAccountLink = 'http://express.stripe.com/dashboard';
const mockCurrentDeadline = 1640995200;

describe( 'Overview: update business details modal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when opened for restricted account', () => {
		render(
			<UpdateBusinessDetailsModal
				errorMessages={ mockErrorMessages }
				accountStatus="restricted"
				accountLink={ mockAccountLink }
			/>
		);

		expect( modalContent() ).toHaveTextContent(
			'Payments and payouts are disabled for this account until missing information is updated.'
		);
		mockErrorMessages.forEach( ( message ) =>
			expect( modalContent() ).toHaveTextContent( message )
		);
	} );

	test( 'renders correctly when opened for restricted soon account with current deadline', () => {
		render(
			<UpdateBusinessDetailsModal
				errorMessages={ mockErrorMessages }
				accountStatus="restricted_soon"
				accountLink={ mockAccountLink }
				currentDeadline={ mockCurrentDeadline }
			/>
		);

		expect( modalContent() ).toHaveTextContent(
			'Additional information is required to verify your business. Update by 7pm Dec 31, 2021 to avoid a disruption in payouts.'
		);
		mockErrorMessages.forEach( ( message ) =>
			expect( modalContent() ).toHaveTextContent( message )
		);
	} );

	test( 'renders correctly when opened for restricted soon account without current deadline', () => {
		render(
			<UpdateBusinessDetailsModal
				errorMessages={ mockErrorMessages }
				accountStatus="restricted_soon"
				accountLink={ mockAccountLink }
			/>
		);

		expect( modalContent() ).toHaveTextContent(
			'Payments and payouts are disabled for this account until missing information is updated.'
		);
	} );
} );
