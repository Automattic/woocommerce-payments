/**
 * External Dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal Dependencies
 */
import GroupedSelectControl, { ListItem } from '..';

describe( 'Grouped Select Control', () => {
	const onChange = jest.fn();

	const options: ListItem[] = [
		{ type: 'group', key: 'g1', name: 'Group 1' },
		{ type: 'option', key: 'o1', name: 'Option 1', group: 'g1' },
		{ type: 'option', key: 'o2', name: 'Option 2', group: 'g1' },
		{ type: 'group', key: 'g2', name: 'Group 2' },
		{ type: 'option', key: 'o3', name: 'Option 3', group: 'g2' },
		{ type: 'option', key: 'o4', name: 'Option 4', group: 'g2' },
		{ type: 'group', key: 'g3', name: 'Group 3' },
		{
			type: 'option',
			key: 'o5',
			name: 'Option 5',
			group: 'g3',
			context: 'z',
		},
	];

	const renderControl = ( props?: any ) =>
		render(
			<GroupedSelectControl
				{ ...props }
				label="Group select"
				options={ options }
				onChange={ onChange }
			/>
		);

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders with minimum required props', () => {
		renderControl();

		const select = screen.getByRole( 'button', { name: 'Group select' } );

		expect( select ).toBeInTheDocument();
	} );

	it( 'renders with placeholder', () => {
		renderControl( { placeholder: 'Select an option' } );

		const select = screen.getByRole( 'button' );

		expect( select ).toHaveTextContent( 'Select an option' );
	} );

	it( 'renders selected option instead of placeholder', () => {
		renderControl( {
			value: options[ 1 ],
			placeholder: 'Select an option',
		} );

		const select = screen.getByRole( 'button' );

		expect( select ).toHaveTextContent( 'Option 1' );
	} );

	it( 'shows only the first group options', () => {
		renderControl();

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		expect( screen.getByText( 'Option 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Option 3' ) ).not.toBeInTheDocument();
	} );

	it( 'toggles group visibility on click', () => {
		renderControl();

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		const group1 = screen.getByRole( 'option', { name: 'Group 1' } );
		const group2 = screen.getByRole( 'option', { name: 'Group 2' } );

		expect( screen.getByText( 'Option 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Option 3' ) ).not.toBeInTheDocument();

		userEvent.click( group1 );
		expect( screen.queryByText( 'Option 1' ) ).not.toBeInTheDocument();

		userEvent.click( group2 );
		expect( screen.getByText( 'Option 3' ) ).toBeInTheDocument();
	} );

	it( 'calls onChange callback when an option is selected', () => {
		renderControl();

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		const option = screen.getByRole( 'option', { name: 'Option 1' } );
		userEvent.click( option );

		expect( onChange ).toHaveBeenCalledWith( options[ 0 ] );
	} );

	it( 'filters options by name', () => {
		renderControl( { searchable: true } );

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		const input = screen.getByRole( 'textbox' );

		userEvent.type( input, '1' );
		expect( screen.getByText( 'Option 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Option 2' ) ).not.toBeInTheDocument();
	} );

	it( 'filters options by context', () => {
		renderControl( { searchable: true } );

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		const input = screen.getByRole( 'textbox' );

		userEvent.type( input, 'z' );
		expect( screen.queryByText( 'Option 1' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'Option 5' ) ).toBeInTheDocument();
	} );

	it( 'restores visibility state after clearing search', () => {
		renderControl( { searchable: true } );

		const select = screen.getByRole( 'button' );
		userEvent.click( select );

		const group1 = screen.getByRole( 'option', { name: 'Group 1' } );
		const group2 = screen.getByRole( 'option', { name: 'Group 2' } );
		userEvent.click( group1 );
		userEvent.click( group2 );
		expect( screen.queryByText( 'Option 1' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Option 3' ) ).toBeInTheDocument();

		const input = screen.getByRole( 'textbox' );
		userEvent.type( input, 'z' );
		expect( screen.queryByText( 'Option 5' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Option 3' ) ).not.toBeInTheDocument();

		userEvent.clear( input );

		expect( screen.queryByText( 'Option 5' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Option 3' ) ).toBeInTheDocument();
	} );
} );
