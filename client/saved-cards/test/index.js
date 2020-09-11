/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { useResizeObserver } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { SavedCards } from '../';

jest.mock( '@wordpress/compose', () => ( { useResizeObserver: jest.fn() } ) );
const mockUseResizeObserverWidth = ( width ) => {
	useResizeObserver.mockReturnValue( [ null, { width } ] );
};

const mockCards = [
	{
		tokenId: 1,
		paymentMethodId: 'pm_mock_1',
		isDefault: true,
		brand: 'visa',
		last4: '4242',
		expiryMonth: '04',
		expiryYear: '2024',
	},
	{
		tokenId: 2,
		paymentMethodId: 'pm_mock_2',
		isDefault: false,
		brand: 'mastercard',
		last4: '4444',
		expiryMonth: '04',
		expiryYear: '2044',
	},
];

describe( 'Saved Cards', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		mockUseResizeObserverWidth( 800 );
		const { container } = render( <SavedCards cards={ mockCards } /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'adds is-small class for small viewports', () => {
		mockUseResizeObserverWidth( 374 );
		const { container } = render( <SavedCards cards={ mockCards } /> );
		expect( container.firstChild ).toHaveClass( 'is-small' );
	} );

	test( 'adds is-medium class for medium viewports lower-bound', () => {
		mockUseResizeObserverWidth( 375 );
		const { container } = render( <SavedCards cards={ mockCards } /> );
		expect( container.firstChild ).toHaveClass( 'is-medium' );
	} );

	test( 'adds is-medium class for medium viewports upper-bound', () => {
		mockUseResizeObserverWidth( 651 );
		const { container } = render( <SavedCards cards={ mockCards } /> );
		expect( container.firstChild ).toHaveClass( 'is-medium' );
	} );

	test( 'adds is-large class for large viewports', () => {
		mockUseResizeObserverWidth( 652 );
		const { container } = render( <SavedCards cards={ mockCards } /> );
		expect( container.firstChild ).toHaveClass( 'is-large' );
	} );
} );
