/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import MethodSelector from '../methods-selector';
import UpePreviewMethodSelector from '../upe-preview-methods-selector';

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

	describe( 'if UPE settings preview is disabled', () => {
		it( 'renders "Set up additional payment methods" page', () => {
			global.wcpaySettings = {
				additionalMethodsSetup: {
					isUpeSettingsPreviewEnabled: false,
					isUpeEnabled: false,
				},
			};

			render( <AdditionalMethodsPage /> );

			expect(
				screen.queryByText( 'Non-UPE method selector' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'if UPE settings preview is enabled', () => {
		it( 'renders "Boost your sales by accepting new payment methods" page', () => {
			global.wcpaySettings = {
				additionalMethodsSetup: {
					isUpeSettingsPreviewEnabled: true,
					isUpeEnabled: false,
				},
			};

			render( <AdditionalMethodsPage /> );

			expect(
				screen.queryByText( 'UPE preview method selector' )
			).toBeInTheDocument();
		} );
	} );
} );
