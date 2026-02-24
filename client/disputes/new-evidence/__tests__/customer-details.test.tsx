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
				address: {
					line1: '123 Main St',
					line2: '',
					city: 'City',
					state: 'ST',
					postal_code: '12345',
					country: 'US',
				},
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
		expect(
			screen.getByText( '123 Main St, City, ST, 12345, US' )
		).toBeInTheDocument();
	} );

	it( 'renders address without empty parts', () => {
		const dispute = {
			...baseDispute,
			charge: {
				...baseDispute.charge,
				billing_details: {
					...baseDispute.charge.billing_details,
					address: {
						line1: '456 Oak Ave',
						line2: 'Apt 2B',
						city: 'Town',
						state: '',
						postal_code: '99999',
						country: 'US',
					},
				},
			},
		};
		render( <CustomerDetails dispute={ dispute as any } /> );
		expect(
			screen.getByText( '456 Oak Ave, Apt 2B, Town, 99999, US' )
		).toBeInTheDocument();
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
