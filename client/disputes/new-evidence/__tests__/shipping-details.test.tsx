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
			shipping_date: '2023-01-01',
		},
	};

	it( 'renders all shipping fields', () => {
		render(
			<ShippingDetails
				shippingCarrier={ baseDispute.evidence.shipping_carrier }
				shippingDate={ baseDispute.evidence.shipping_date }
				shippingTrackingNumber={
					baseDispute.evidence.shipping_tracking_number
				}
				shippingAddress={ baseDispute.evidence.shipping_address }
				onShippingCarrierChange={ jest.fn() }
				onShippingDateChange={ jest.fn() }
				onShippingTrackingNumberChange={ jest.fn() }
				onShippingAddressChange={ jest.fn() }
			/>
		);
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
		const dispute = {
			evidence: {
				shipping_carrier: '',
				shipping_date: '',
				shipping_tracking_number: '',
				shipping_address: '',
			},
		};
		render(
			<ShippingDetails
				shippingCarrier={ dispute.evidence.shipping_carrier }
				shippingDate={ dispute.evidence.shipping_date }
				shippingTrackingNumber={
					dispute.evidence.shipping_tracking_number
				}
				shippingAddress={ dispute.evidence.shipping_address }
				onShippingCarrierChange={ jest.fn() }
				onShippingDateChange={ jest.fn() }
				onShippingTrackingNumberChange={ jest.fn() }
				onShippingAddressChange={ jest.fn() }
			/>
		);
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
		render(
			<ShippingDetails
				shippingCarrier={ baseDispute.evidence.shipping_carrier }
				shippingDate={ baseDispute.evidence.shipping_date }
				shippingTrackingNumber={
					baseDispute.evidence.shipping_tracking_number
				}
				shippingAddress={ baseDispute.evidence.shipping_address }
				readOnly={ true }
				onShippingCarrierChange={ jest.fn() }
				onShippingDateChange={ jest.fn() }
				onShippingTrackingNumberChange={ jest.fn() }
				onShippingAddressChange={ jest.fn() }
			/>
		);
		expect( screen.getByLabelText( /SHIPPING CARRIER/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /SHIPPING DATE/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /TRACKING NUMBER/i ) ).toBeDisabled();
		expect( screen.getByLabelText( /SHIPPING ADDRESS/i ) ).toBeDisabled();
	} );
} );
