/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';

describe( 'GeneralSettings', () => {
	it( 'renders', async () => {
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
} );
