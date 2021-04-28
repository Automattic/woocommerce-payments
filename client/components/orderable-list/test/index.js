/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OrderableList from '../';

describe( 'OrderableList', () => {
	test( 'renders list with children as items', () => {
		render(
			<OrderableList>
				<div>foo</div>
				<div>bar</div>
			</OrderableList>
		);

		const list = screen.getByRole( 'list' );

		expect( list ).toBeInTheDocument();
		expect( list ).toContainElement( screen.getByText( 'foo' ) );
		expect( list ).toContainElement( screen.getByText( 'bar' ) );
	} );

	test( 'renders list with custom classes', () => {
		render(
			<OrderableList className="some-class another-class">
				<div>foo</div>
			</OrderableList>
		);
		const listClasses = screen.getByRole( 'list' ).className.split( ' ' );
		expect( listClasses ).toContain( 'some-class' );
		expect( listClasses ).toContain( 'another-class' );
	} );

	test( 'renders items with custom classes', () => {
		render(
			<OrderableList>
				<div className="foo">bar</div>
			</OrderableList>
		);

		const itemClasses = screen
			.getByRole( 'listitem' )
			.className.split( ' ' );
		expect( itemClasses ).toContain( 'orderable-list__item' );
		expect( itemClasses ).toContain( 'foo' );
	} );

	test( 'renders drag handles if there are multiple items', () => {
		render(
			<OrderableList>
				<div>foo</div>
				<div>bar</div>
			</OrderableList>
		);

		const list = screen.getByRole( 'list' );
		expect( list.className ).toContain( 'has-drag-handles' );
	} );

	test( 'does not render drag handles if there is a single item', () => {
		render(
			<OrderableList>
				<div>foo</div>
			</OrderableList>
		);

		const list = screen.getByRole( 'list' );
		expect( list.className ).not.toContain( 'has-drag-handles' );
	} );
} );
