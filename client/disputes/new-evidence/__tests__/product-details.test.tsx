/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProductDetails from '../product-details';

// Mock wcpaySettings global
declare const global: {
	wcpaySettings: {
		featureFlags: {
			isDisputeAdditionalEvidenceTypesEnabled: boolean;
		};
	};
};

describe( 'ProductDetails', () => {
	const baseProps = {
		productType: 'physical_product',
		onProductTypeChange: jest.fn(),
		productDescription: 'A great product',
		onProductDescriptionChange: jest.fn(),
		readOnly: false,
	};

	beforeEach( () => {
		global.wcpaySettings = {
			featureFlags: {
				isDisputeAdditionalEvidenceTypesEnabled: false,
			},
		};
	} );

	it( 'renders product type selector and description', () => {
		render( <ProductDetails { ...baseProps } /> );
		expect(
			screen.getByLabelText( /PRODUCT OR SERVICE TYPE/i )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( /PRODUCT OR SERVICE DESCRIPTION/i )
		).toBeInTheDocument();
		expect(
			screen.getByDisplayValue( 'A great product' )
		).toBeInTheDocument();
	} );

	it( 'disables fields when readOnly', () => {
		render( <ProductDetails { ...baseProps } readOnly={ true } /> );
		expect(
			screen.getByLabelText( /PRODUCT OR SERVICE TYPE/i )
		).toBeDisabled();
		expect(
			screen.getByLabelText( /PRODUCT OR SERVICE DESCRIPTION/i )
		).toBeDisabled();
	} );

	it( 'calls change handlers', () => {
		render( <ProductDetails { ...baseProps } /> );
		fireEvent.change(
			screen.getByLabelText( /PRODUCT OR SERVICE DESCRIPTION/i ),
			{
				target: { value: 'New desc' },
			}
		);
		expect( baseProps.onProductDescriptionChange ).toHaveBeenCalledWith(
			'New desc'
		);
		fireEvent.change( screen.getByLabelText( /PRODUCT OR SERVICE TYPE/i ), {
			target: { value: 'digital_product_or_service' },
		} );
		expect( baseProps.onProductTypeChange ).toHaveBeenCalled();
	} );

	it( 'does not show Booking/Reservation option when feature flag is disabled', () => {
		global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = false;
		render( <ProductDetails { ...baseProps } /> );
		const select = screen.getByLabelText( /PRODUCT OR SERVICE TYPE/i );
		const options = Array.from( select.querySelectorAll( 'option' ) ).map(
			( option ) => ( option as HTMLOptionElement ).value
		);
		expect( options ).not.toContain( 'booking_reservation' );
		expect( options ).toEqual( [
			'physical_product',
			'digital_product_or_service',
			'offline_service',
			'multiple',
		] );
	} );

	it( 'shows Booking/Reservation option when feature flag is enabled', () => {
		global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;
		render( <ProductDetails { ...baseProps } /> );
		const select = screen.getByLabelText( /PRODUCT OR SERVICE TYPE/i );
		const options = Array.from( select.querySelectorAll( 'option' ) ).map(
			( option ) => ( option as HTMLOptionElement ).value
		);
		expect( options ).toContain( 'booking_reservation' );
		expect( options ).toEqual( [
			'physical_product',
			'digital_product_or_service',
			'offline_service',
			'booking_reservation',
			'multiple',
		] );
	} );
} );
