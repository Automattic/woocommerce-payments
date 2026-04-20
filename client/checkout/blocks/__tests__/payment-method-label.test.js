/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodLabel from '../payment-method-label';
import { getUPEConfig } from 'wcpay/utils/checkout';

// Mock dependencies.
jest.mock( 'wcpay/utils/checkout', () => ( {
	getUPEConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/utils/appearance-cache', () => ( {
	getCachedTheme: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/icon-theme', () => ( {
	getIconTheme: jest.fn( () => 'stripe' ),
} ) );

jest.mock( '../payment-methods-logos', () => ( {
	PaymentMethodsLogos: () => <div data-testid="card-logos" />,
} ) );

jest.mock( 'wcpay/utils/card-brands', () => ( {
	getCardBrands: jest.fn( () => [] ),
} ) );

import { getCachedTheme } from 'wcpay/utils/appearance-cache';
import { getIconTheme } from 'wcpay/checkout/utils/icon-theme';

const MockLabel = ( { text, icon } ) => (
	<span>
		{ text }
		{ icon }
	</span>
);

const defaultProps = {
	components: { PaymentMethodLabel: MockLabel },
	title: 'iDEAL',
	paymentMethodId: 'ideal',
	icon: '/light-icon.svg',
	darkIcon: '/dark-icon.svg',
};

describe( 'PaymentMethodLabel', () => {
	beforeEach( () => {
		localStorage.clear();
		getCachedTheme.mockReturnValue( null );
		getIconTheme.mockReturnValue( 'stripe' );
		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'testMode' ) return false;
			if ( key === 'stylesCacheVersion' ) return 'v1';
			return null;
		} );
	} );

	test( 'renders light icon by default', () => {
		render( <PaymentMethodLabel { ...defaultProps } /> );
		const img = screen.getByAltText( 'iDEAL' );
		expect( img.src ).toContain( '/light-icon.svg' );
	} );

	test( 'renders dark icon when cached theme is night', () => {
		getCachedTheme.mockReturnValue( 'night' );
		render( <PaymentMethodLabel { ...defaultProps } /> );
		const img = screen.getByAltText( 'iDEAL' );
		expect( img.src ).toContain( '/dark-icon.svg' );
	} );

	test( 'falls back to light icon when darkIcon is not provided', () => {
		getCachedTheme.mockReturnValue( 'night' );
		render(
			<PaymentMethodLabel { ...defaultProps } darkIcon={ undefined } />
		);
		const img = screen.getByAltText( 'iDEAL' );
		expect( img.src ).toContain( '/light-icon.svg' );
	} );

	test( 'renders dark icon immediately on dark background without cache', () => {
		getIconTheme.mockReturnValue( 'night' );
		render( <PaymentMethodLabel { ...defaultProps } /> );
		const img = screen.getByAltText( 'iDEAL' );
		expect( img.src ).toContain( '/dark-icon.svg' );
	} );

	test( 'swaps to dark icon when appearance-cached event fires', () => {
		render( <PaymentMethodLabel { ...defaultProps } /> );
		const img = screen.getByAltText( 'iDEAL' );
		expect( img.src ).toContain( '/light-icon.svg' );

		// Simulate appearance being computed and cached.
		getCachedTheme.mockReturnValue( 'night' );
		act( () => {
			window.dispatchEvent( new Event( 'wcpay-appearance-cached' ) );
		} );

		expect( img.src ).toContain( '/dark-icon.svg' );
	} );
} );
