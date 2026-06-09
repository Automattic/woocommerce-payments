/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CustomerDetails from '../customer-details';

describe( 'CustomerDetails', () => {
	const baseDispute = {
		charge: {
			billing_details: {
				name: 'John Doe',
				phone: '123-456-7890',
				email: 'john@example.com',
				formatted_address: '123 Main St, City, ST 12345, US',
			},
		},
		order: { ip_address: '1.2.3.4' },
	};

	it( 'renders all customer fields', () => {
		render( <CustomerDetails dispute={ baseDispute as any } /> );
		expect( screen.getByText( 'Customer details' ) ).toBeInTheDocument();
		expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
		expect( screen.getByText( '123-456-7890' ) ).toBeInTheDocument();
		expect( screen.getByText( 'john@example.com' ) ).toBeInTheDocument();
		expect( screen.getByText( '1.2.3.4' ) ).toBeInTheDocument();
	} );

	it( 'renders formatted billing address as single line', () => {
		render( <CustomerDetails dispute={ baseDispute as any } /> );
		expect(
			screen.getByText( '123 Main St, City, ST 12345, US' )
		).toBeInTheDocument();
	} );

	it( 'renders dash when formatted address is missing', () => {
		const dispute = {
			charge: {
				billing_details: {
					name: 'Jane Doe',
					phone: '555-000-1234',
					email: 'jane@example.com',
				},
			},
			order: { ip_address: '5.6.7.8' },
		};
		render( <CustomerDetails dispute={ dispute as any } /> );
		const addressEl = document.querySelector(
			'.wcpay-dispute-evidence-customer-details__billing-value'
		);
		expect( addressEl?.textContent ).toBe( '-' );
	} );

	it( 'renders dashes for missing data', () => {
		const dispute = { charge: {}, order: {} };
		render( <CustomerDetails dispute={ dispute as any } /> );
		expect( screen.getAllByText( '-' ).length ).toBeGreaterThan( 0 );
	} );

	it( 'renders email as mailto link', () => {
		render( <CustomerDetails dispute={ baseDispute as any } /> );
		const emailLink = screen.getByRole( 'link', {
			name: 'john@example.com',
		} );
		expect( emailLink ).toHaveAttribute(
			'href',
			'mailto:john@example.com'
		);
	} );
} );
