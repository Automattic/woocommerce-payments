/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import UpePreviewMethodSelector from '../upe-preview-methods-selector';

/**
 * Internal dependencies
 */
import AdditionalMethodsPage from '../';

jest.mock( '../upe-preview-methods-selector', () => jest.fn() );

describe( 'AdditionalMethodsPage', () => {
	beforeEach( () => {
		UpePreviewMethodSelector.mockReturnValue(
			<p>UPE preview method selector</p>
		);
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	describe( 'if UPE settings preview is enabled', () => {
		it( 'renders "Boost your sales by accepting new payment methods" page', () => {
			global.wcpaySettings = {
				additionalMethodsSetup: {
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
