/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import MultiCurrencySetup from '../tasks/multi-currency-setup';
import { getPath, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import createMultiCurrencySetupTask from '../task';

jest.mock( '../tasks/multi-currency-setup', () => jest.fn() );
jest.mock( '@woocommerce/navigation', () => ( {
	getPath: jest.fn(),
	updateQueryString: jest.fn(),
} ) );

describe( 'createMultiCurrencySetupTask()', () => {
	beforeEach( () => {
		MultiCurrencySetup.mockReturnValue( <p>Multi-Currency setup task</p> );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'returns task', () => {
		const result = createMultiCurrencySetupTask( {} );

		expect( result.key ).toEqual(
			'woocommerce-payments--multi-currency-setup'
		);
	} );

	it.each( [ [ 'yes' ], [ 'no' ], [ '' ], [ false ] ] )(
		'marks task completed if isSetupCompleted equals "yes" (testing: %s)',
		( isSetupCompleted ) => {
			const result = createMultiCurrencySetupTask( {
				isSetupCompleted,
			} );

			expect( result.completed ).toEqual( 'yes' === isSetupCompleted );
		}
	);

	it( 'renders "Sell worldwide in multiple currencies" task', () => {
		const result = createMultiCurrencySetupTask( {
			isSetupCompleted: false,
		} );

		render( result.container );

		expect( result.title ).toEqual(
			'Sell worldwide in multiple currencies'
		);
		expect(
			screen.queryByText( 'Multi-Currency setup task' )
		).toBeInTheDocument();
	} );

	it( 'adds onClick redirect on WCPay > Overview page', () => {
		getPath.mockReturnValue( '/payments/overview' );

		const result = createMultiCurrencySetupTask( {} );

		expect( result ).toHaveProperty( 'onClick' );
		result.onClick();
		expect( updateQueryString ).toHaveBeenCalledWith(
			{ task: 'woocommerce-payments--multi-currency-setup' },
			''
		);
	} );

	it( 'does not add onClick redirect on WC > Home page', () => {
		getPath.mockReturnValue( '/' );

		const result = createMultiCurrencySetupTask( {} );

		expect( result ).not.toHaveProperty( 'onClick' );
	} );
} );
