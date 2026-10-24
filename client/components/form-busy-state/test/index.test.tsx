/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FormBusyState from '..';

describe( 'FormBusyState', () => {
	it( 'renders its children', () => {
		render(
			<FormBusyState isBusy={ false }>
				<button>Save</button>
			</FormBusyState>
		);

		expect(
			screen.getByRole( 'button', { name: 'Save' } )
		).toBeInTheDocument();
	} );

	it( 'reflects the busy state via aria-busy without disabling children', () => {
		const { container, rerender } = render(
			<FormBusyState isBusy={ false }>
				<input type="text" />
			</FormBusyState>
		);

		const wrapper = container.querySelector( '.wcpay-form-busy-state' );

		expect( wrapper ).toHaveAttribute( 'aria-busy', 'false' );
		expect( wrapper ).not.toHaveClass( 'is-busy' );

		rerender(
			<FormBusyState isBusy={ true }>
				<input type="text" />
			</FormBusyState>
		);

		expect( wrapper ).toHaveAttribute( 'aria-busy', 'true' );
		expect( wrapper ).toHaveClass( 'is-busy' );

		// the #4740 point: never `disabled`/`inert`, which drops inputs out of the tab order
		expect( screen.getByRole( 'textbox' ) ).not.toBeDisabled();
		expect( wrapper ).not.toHaveAttribute( 'inert' );
	} );

	it( 'announces the in-flight state through a polite live region', () => {
		const { rerender } = render(
			<FormBusyState isBusy={ false }>
				<input type="text" />
			</FormBusyState>
		);

		const status = screen.getByRole( 'status' );

		expect( status ).toHaveAttribute( 'aria-live', 'polite' );
		expect( status ).toHaveTextContent( '' );

		rerender(
			<FormBusyState isBusy={ true }>
				<input type="text" />
			</FormBusyState>
		);

		expect( status ).toHaveTextContent( 'Saving…' );

		// goes quiet on completion; the "saved" notice carries the confirmation
		rerender(
			<FormBusyState isBusy={ false }>
				<input type="text" />
			</FormBusyState>
		);

		expect( status ).toHaveTextContent( '' );
	} );
} );
