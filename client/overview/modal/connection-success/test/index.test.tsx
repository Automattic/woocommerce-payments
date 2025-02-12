/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ConnectionSuccessModal from '../index';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

declare const global: {
	wcpaySettings: {
		isConnectionSuccessModalDismissed: boolean;
	};
};

describe( 'Connection Success Modal', () => {
	global.wcpaySettings = {
		isConnectionSuccessModalDismissed: false,
	};

	it( 'modal is open by default', () => {
		render( <ConnectionSuccessModal /> );

		const queryHeading = () =>
			screen.queryByRole( 'heading', {
				name: "You're ready to accept payments!",
			} );

		expect( queryHeading() ).toBeInTheDocument();
	} );

	it( 'closes modal when dismiss button is clicked', () => {
		global.wcpaySettings = {
			isConnectionSuccessModalDismissed: false,
		};

		render( <ConnectionSuccessModal /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Dismiss',
			} )
		);

		expect(
			screen.queryByRole( 'heading', {
				name: "You're ready to accept payments!",
			} )
		).not.toBeInTheDocument();
	} );
} );
