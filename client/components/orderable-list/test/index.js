/** @format */

/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import OrderableList from '../';

describe( 'OrderableList', () => {
	test( 'renders list with items', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		render( <OrderableList items={ items } /> );

		const list = screen.getByRole( 'list' );

		expect( list ).toBeInTheDocument();
		expect( list ).toContainElement( screen.getByText( 'Some label' ) );
		expect( list ).toContainElement( screen.getByText( 'Another label' ) );
	} );

	test( 'renders list with custom classes', () => {
		render(
			<OrderableList items={ [] } className="some-class another-class" />
		);
		const listClasses = screen.getByRole( 'list' ).className.split( ' ' );
		expect( listClasses ).toContain( 'some-class' );
		expect( listClasses ).toContain( 'another-class' );
	} );

	test( 'renders drag handles if there are multiple items', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		render( <OrderableList items={ items } /> );

		const list = screen.getByRole( 'list' );
		expect( list.className ).toContain( 'show-drag-handles' );
	} );

	test( 'does not render drag handles if there is a single item', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
		];

		render( <OrderableList items={ items } /> );

		const list = screen.getByRole( 'list' );
		expect( list.className ).not.toContain( 'show-drag-handles' );
	} );

	test( 'renders item descriptions', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		render( <OrderableList items={ items } /> );

		const list = screen.getByRole( 'list' );

		expect( list ).toContainElement(
			screen.getByText( 'Some description' )
		);
		expect( list ).toContainElement(
			screen.getByText( 'Another description' )
		);
	} );

	test( 'renders action buttons', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		render( <OrderableList items={ items } /> );

		const listItems = screen.getAllByRole( 'listitem' );

		listItems.forEach( ( item ) => {
			const manageButton = within( item ).getByRole( 'button', {
				name: 'Manage',
			} );
			const deleteButton = within( item ).getByRole( 'button', {
				name: 'Delete',
			} );

			expect( item ).toContainElement( manageButton );
			expect( item ).toContainElement( deleteButton );
		} );
	} );

	test( 'clicking Manage button calls onManageClick() with appropriate ID', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		const onManageClick = jest.fn();
		render(
			<OrderableList items={ items } onManageClick={ onManageClick } />
		);

		items.forEach( ( item ) => {
			const listItemDOM = screen.getByText( item.label ).closest( 'li' );
			const manageButton = within( listItemDOM ).getByRole( 'button', {
				name: 'Manage',
			} );
			user.click( manageButton );
			expect( onManageClick ).toHaveBeenCalledWith( item.id );
		} );
	} );

	test( 'clicking Delete button calls onDeleteClick() with appropriate ID', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		const onDeleteClick = jest.fn();
		render(
			<OrderableList items={ items } onDeleteClick={ onDeleteClick } />
		);

		items.forEach( ( item ) => {
			const listItemDOM = screen.getByText( item.label ).closest( 'li' );
			const deleteButton = within( listItemDOM ).getByRole( 'button', {
				name: 'Delete',
			} );
			user.click( deleteButton );
			expect( onDeleteClick ).toHaveBeenCalledWith( item.id );
		} );
	} );

	test( 'clicking payment method name calls onManageClick() with appropriate ID', () => {
		const items = [
			{ id: 'foo', label: 'Some label', description: 'Some description' },
			{
				id: 'bar',
				label: 'Another label',
				description: 'Another description',
			},
		];

		const onManageClick = jest.fn();
		render(
			<OrderableList items={ items } onManageClick={ onManageClick } />
		);

		items.forEach( ( item ) => {
			const paymentMethodLabel = screen.getByRole( 'button', {
				name: item.label,
			} );
			user.click( paymentMethodLabel );
			expect( onManageClick ).toHaveBeenCalledWith( item.id );
		} );
	} );
} );
