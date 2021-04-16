/** @format */
/**
 * External dependencies
 */
import { fireEvent, render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

/**
 * Internal dependencies
 */
import PaymentMethodsSelector from '../';
import { addSelectedPaymentMethods } from 'data';

jest.mock( 'data', () => ( { addSelectedPaymentMethods: jest.fn() } ) );

describe( 'PaymentMethodsSelector', () => {
	test( 'renders a modal window', () => {
		const handleClose = jest.fn();

		const { getByText } = render(
			<PaymentMethodsSelector onClose={ handleClose } />
		);

		expect( getByText( 'Add payment methods' ) ).toBeTruthy();
		expect( getByText( 'Add selected' ) ).toBeTruthy();
		expect( getByText( 'Cancel' ) ).toBeTruthy();

		fireEvent.click( getByText( 'Cancel' ) );

		expect( handleClose ).toHaveBeenCalledTimes( 1 );

		fireEvent.click( getByText( 'Add selected' ) );
		expect( addSelectedPaymentMethods ).toHaveBeenCalledTimes( 1 );
	} );
} );
