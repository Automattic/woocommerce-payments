/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodsList from '../';

describe( 'PaymentMethodsList', () => {
	test( 'renders list with children as items', () => {
		render(
			<PaymentMethodsList>
				<li>foo</li>
				<li>bar</li>
			</PaymentMethodsList>
		);

		const list = screen.queryByRole( 'list' );

		expect( list ).toBeInTheDocument();
		expect( list ).toContainElement( screen.getByText( 'foo' ) );
		expect( list ).toContainElement( screen.getByText( 'bar' ) );
	} );

	test( 'renders list with custom classes', () => {
		render(
			<PaymentMethodsList className="some-class another-class">
				<li>foo</li>
			</PaymentMethodsList>
		);
		const listClasses = screen.getByRole( 'list' ).className.split( ' ' );
		expect( listClasses ).toContain( 'some-class' );
		expect( listClasses ).toContain( 'another-class' );
	} );

	test( 'renders items with custom classes', () => {
		render(
			<PaymentMethodsList>
				<li className="foo">bar</li>
			</PaymentMethodsList>
		);

		const itemClasses = screen
			.getByRole( 'listitem' )
			.className.split( ' ' );
		expect( itemClasses ).toContain( 'foo' );
	} );
} );
