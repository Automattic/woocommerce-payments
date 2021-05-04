/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';

describe( 'GeneralSettings', () => {
	it( 'renders', () => {
		render( <GeneralSettings accountLink="/account-link" /> );

		const manageLink = screen.queryByText( 'Manage in Stripe' );
		expect( manageLink ).toBeInTheDocument();
		expect( manageLink ).toHaveTextContent(
			'Manage in Stripe(opens in a new tab)'
		);
		expect( manageLink.href ).toContain( '/account-link' );

		expect(
			screen.queryByText( 'Enable WooCommerce Payments' )
		).toBeInTheDocument();
	} );

	it( 'displays the length of the bank statement input', async () => {
		render( <GeneralSettings accountLink="/account-link" /> );

		const manageLink = screen.getByText( '0 / 22' );
		expect( manageLink ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Customer bank statement' ), {
			target: { value: 'Statement Name' },
		} );

		expect( manageLink ).toHaveTextContent( '14 / 22' );
	} );
} );
