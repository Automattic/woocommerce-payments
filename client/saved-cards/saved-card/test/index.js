/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { SavedCard } from '../';

const mockCard = {
	tokenId: 1,
	paymentMethodId: 'pm_mock_1',
	isDefault: true,
	brand: 'visa',
	last4: '4242',
	expiryMonth: '04',
	expiryYear: '2024',
};

describe( 'Saved Card', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders default card correctly', () => {
		const { container } = render( <SavedCard { ...mockCard } /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders non-default card correctly', () => {
		const { container } = render(
			<SavedCard { ...mockCard } { ...{ isDefault: false } } />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
