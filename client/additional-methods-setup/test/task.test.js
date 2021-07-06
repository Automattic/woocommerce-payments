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
import createAdditionalMethodsSetupTask from '../task';

jest.mock( '../methods-selector', () => jest.fn() );
jest.mock( '../upe-preview-methods-selector', () => jest.fn() );

describe( 'createAdditionalMethodsSetupTask()', () => {
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
		const result = createAdditionalMethodsSetupTask( {} );

		expect( result.key ).toEqual(
			'woocommerce-payments--additional-payment-methods'
		);
	} );

	describe( 'if UPE settings preview is disabled', () => {
		it( 'renders "Set up additional payment methods" task', () => {
			const result = createAdditionalMethodsSetupTask( {
				isUpeSettingsPreviewEnabled: false,
			} );

			render( result.container );

			expect( result.title ).toEqual(
				'Set up additional payment methods'
			);
			expect(
				screen.queryByText( 'Non-UPE method selector' )
			).toBeInTheDocument();
		} );

		it.each( [ [ 'yes' ], [ 'no' ], [ '' ], [ false ] ] )(
			'marks task completed if isSetupCompleted equals "yes" (testing: %s)',
			( isSetupCompleted ) => {
				const result = createAdditionalMethodsSetupTask( {
					isSetupCompleted,
				} );

				expect( result.completed ).toEqual(
					'yes' === isSetupCompleted
				);
			}
		);
	} );

	describe( 'if UPE settings preview is enabled', () => {
		it( 'renders "Boost your sales by accepting new payment methods" task', () => {
			const result = createAdditionalMethodsSetupTask( {
				isUpeSettingsPreviewEnabled: true,
			} );

			render( result.container );

			expect( result.title ).toEqual(
				'Boost your sales by accepting new payment methods'
			);
			expect(
				screen.queryByText( 'UPE preview method selector' )
			).toBeInTheDocument();
		} );

		it.each( [
			[ 'yes', '1' ],
			[ 'yes', '0' ],
			[ 'no', '1' ],
			[ 'no', '0' ],
		] )(
			'marks task completed if isSetupCompleted equals "yes" or isUpeEnabled equals "1" (testing: %s, %s)',
			( isSetupCompleted, isUpeEnabled ) => {
				const result = createAdditionalMethodsSetupTask( {
					isUpeSettingsPreviewEnabled: true,
					isSetupCompleted,
					isUpeEnabled,
				} );

				expect( result.completed ).toEqual(
					'yes' === isSetupCompleted || '1' === isUpeEnabled
				);
			}
		);
	} );
} );
