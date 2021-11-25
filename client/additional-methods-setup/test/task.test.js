/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import MethodSelector from '../methods-selector';
import UpePreviewMethodSelector from '../upe-preview-methods-selector';
import { getPath, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import AdditionalMethodsPage from '../';

jest.mock( '../methods-selector', () => jest.fn() );
jest.mock( '../upe-preview-methods-selector', () => jest.fn() );
jest.mock( '@woocommerce/navigation', () => ( {
	getPath: jest.fn(),
	updateQueryString: jest.fn(),
} ) );

describe( 'AdditionalMethodsPage', () => {
	beforeEach( () => {
		MethodSelector.mockReturnValue( <p>Non-UPE method selector</p> );
		UpePreviewMethodSelector.mockReturnValue(
			<p>UPE preview method selector</p>
		);
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'returns task', () => {
		const result = AdditionalMethodsPage( {} );

		expect( result.key ).toEqual(
			'woocommerce-payments--additional-payment-methods'
		);
	} );

	describe( 'if UPE settings preview is disabled', () => {
		it( 'renders "Set up additional payment methods" task', () => {
			const result = AdditionalMethodsPage( {
				isUpeSettingsPreviewEnabled: false,
			} );

			render( result.container );

			expect(
				screen.queryByText( 'Set up additional payment methods' )
			).toBeInTheDocument();

			expect(
				screen.queryByText( 'Non-UPE method selector' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'if UPE settings preview is enabled', () => {
		it( 'renders "Boost your sales by accepting new payment methods" task', () => {
			const result = AdditionalMethodsPage( {
				isUpeSettingsPreviewEnabled: true,
			} );

			render( result.container );

			expect(
				screen.queryByText(
					'Enable the new WooCommerce Payments checkout experience'
				)
			).toBeInTheDocument();

			expect(
				screen.queryByText( 'UPE preview method selector' )
			).toBeInTheDocument();
		} );
	} );

	it( 'adds onClick redirect on WCPay > Overview page', () => {
		getPath.mockReturnValue( '/payments/overview' );

		const result = AdditionalMethodsPage( {} );

		expect( result ).toHaveProperty( 'onClick' );
		result.onClick();
		expect( updateQueryString ).toHaveBeenCalledWith(
			{ task: 'woocommerce-payments--additional-payment-methods' },
			''
		);
	} );

	it( 'does not add onClick redirect on WC > Home page', () => {
		getPath.mockReturnValue( '/' );

		const result = AdditionalMethodsPage( {} );

		expect( result ).not.toHaveProperty( 'onClick' );
	} );
} );
