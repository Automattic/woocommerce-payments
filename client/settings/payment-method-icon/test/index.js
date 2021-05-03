/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '..';

describe( 'PaymentMethodIcon', () => {
	test( 'renders GiroPay payment method icon', () => {
		const { getByAltText } = render( <PaymentMethodIcon name="giropay" /> );

		const method = getByAltText( 'GiroPay' );
		expect( method ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon', () => {
		const { getByAltText } = render( <PaymentMethodIcon name="sepa" /> );

		const method = getByAltText( 'Direct Debit Payments' );
		expect( method ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon', () => {
		const { getByAltText } = render( <PaymentMethodIcon name="sofort" /> );

		const method = getByAltText( 'Sofort' );
		expect( method ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		const { getByText, getByAltText } = render(
			<PaymentMethodIcon name="giropay" showName />
		);

		const method = getByAltText( 'GiroPay' );
		expect( method ).toBeInTheDocument();

		const label = getByText( 'GiroPay' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon and label', () => {
		const { getByText, getByAltText } = render(
			<PaymentMethodIcon name="sepa" showName />
		);

		const method = getByAltText( 'Direct Debit Payments' );
		expect( method ).toBeInTheDocument();

		const label = getByText( 'Direct Debit Payments' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		const { getByText, getByAltText } = render(
			<PaymentMethodIcon name="sofort" showName />
		);

		const method = getByAltText( 'Sofort' );
		expect( method ).toBeInTheDocument();

		const label = getByText( 'Sofort' );
		expect( label ).toBeInTheDocument();
	} );
	test( 'renders nothing when using an invalid icon name', () => {
		const { container } = render( <PaymentMethodIcon name="wrong" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
