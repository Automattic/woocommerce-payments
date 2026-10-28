/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { AccordionRow } from '../';

describe( 'AccordionRow', () => {
	test( 'renders its content in a row container', () => {
		render( <AccordionRow>Test Content</AccordionRow> );

		expect( screen.getByText( 'Test Content' ) ).toHaveClass(
			'wcpay-accordion__row'
		);
	} );

	test( 'renders with custom className', () => {
		render(
			<AccordionRow className="custom-class">Test Content</AccordionRow>
		);

		expect( screen.getByText( 'Test Content' ) ).toHaveClass(
			'wcpay-accordion__row',
			'custom-class'
		);
	} );

	test( 'renders with complex content', () => {
		render(
			<AccordionRow>
				<div>
					<h3>Title</h3>
					<p>Description</p>
				</div>
			</AccordionRow>
		);

		expect(
			screen
				.getByRole( 'heading', { name: 'Title' } )
				.closest( '.wcpay-accordion__row' )
		).toContainElement( screen.getByText( 'Description' ) );
	} );
} );
