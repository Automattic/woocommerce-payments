/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ShippingDetails from '../shipping-details';

describe( 'ShippingDetails', () => {
	const baseDispute = {
		evidence: {
			shipping_carrier: 'UPS',
			shipping_tracking_number: '1Z999',
			shipping_address: '123 Ship St',
			shipping_date: new Date( '2023-01-01' ),
		},
	};

	beforeAll( () => {
		// eslint-disable-next-line @typescript-eslint/no-empty-function
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	} );
	afterAll( () => {
		// eslint-disable-next-line no-console
		( console.error as jest.Mock ).mockRestore();
	} );

	it( 'renders all shipping fields', () => {
		render( <ShippingDetails dispute={ baseDispute } /> );
		expect(
			screen.getByLabelText( /SHIPPING CARRIER/i )
		).toBeInTheDocument();
		expect( screen.getByLabelText( /SHIPPING DATE/i ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( /TRACKING NUMBER/i )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( /SHIPPING ADDRESS/i )
		).toBeInTheDocument();
		expect( screen.getByDisplayValue( 'UPS' ) ).toBeInTheDocument();
		expect( screen.getByDisplayValue( '1Z999' ) ).toBeInTheDocument();
		expect( screen.getByDisplayValue( '123 Ship St' ) ).toBeInTheDocument();
	} );

	it( 'renders empty strings for missing data', () => {
		const dispute = { evidence: {} };
		render( <ShippingDetails dispute={ dispute } /> );
		const inputs = screen.getAllByRole( 'textbox' );
		// SHIPPING CARRIER, TRACKING NUMBER, SHIPPING ADDRESS should be empty
		expect( inputs[ 0 ] ).toHaveValue( '' ); // SHIPPING CARRIER
		expect( inputs[ 1 ] ).toHaveValue( '' ); // TRACKING NUMBER
		expect( inputs[ 2 ] ).toHaveValue( '' ); // SHIPPING ADDRESS
		// SHIPPING DATE should be today's date
		const dateInput = screen.getByLabelText( /SHIPPING DATE/i );
		const today = new Date().toISOString().split( 'T' )[ 0 ];
		expect( dateInput ).toHaveValue( today ); // SHIPPING DATE
	} );

	it( 'disables inputs when readOnly', () => {
		render( <ShippingDetails dispute={ baseDispute } readOnly={ true } /> );
		expect( screen.getByLabelText( /SHIPPING CARRIER/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /SHIPPING DATE/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /TRACKING NUMBER/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /SHIPPING ADDRESS/i ) ).toBeDisabled();
	} );
} );
