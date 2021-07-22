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
import createAdditionalMethodsSetupTask from '../task';

jest.mock( '../methods-selector', () => jest.fn() );
jest.mock( '../upe-preview-methods-selector', () => jest.fn() );
jest.mock( '@woocommerce/navigation', () => ( {
	getPath: jest.fn(),
	updateQueryString: jest.fn(),
} ) );

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
			[ 'yes', true ],
			[ 'yes', false ],
			[ 'no', true ],
			[ 'no', false ],
		] )(
			'marks task completed if isSetupCompleted equals "yes" or isUpeEnabled is true (testing: %s, %s)',
			( isSetupCompleted, isUpeEnabled ) => {
				const result = createAdditionalMethodsSetupTask( {
					isUpeSettingsPreviewEnabled: true,
					isSetupCompleted,
					isUpeEnabled,
				} );

				expect( result.completed ).toEqual(
					'yes' === isSetupCompleted
				);
			}
		);
	} );

	it( 'adds onClick redirect on WCPay > Overview page', () => {
		getPath.mockReturnValue( '/payments/overview' );

		const result = createAdditionalMethodsSetupTask( {} );

		expect( result ).toHaveProperty( 'onClick' );
		result.onClick();
		expect( updateQueryString ).toHaveBeenCalledWith(
			{ task: 'woocommerce-payments--additional-payment-methods' },
			''
		);
	} );

	it( 'does not add onClick redirect on WC > Home page', () => {
		getPath.mockReturnValue( '/' );

		const result = createAdditionalMethodsSetupTask( {} );

		expect( result ).not.toHaveProperty( 'onClick' );
	} );
} );
