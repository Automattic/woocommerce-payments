/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { Accordion, AccordionBody, AccordionRow } from '../';

describe( 'Accordion', () => {
	test( 'renders its bodies collapsed by default', () => {
		const { container } = render(
			<Accordion>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);

		expect( container.firstChild ).toHaveClass( 'wcpay-accordion' );
		expect( container.firstChild ).not.toHaveClass( 'is-high-density' );
		expect(
			screen.getByRole( 'button', { name: 'Test Title' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Test Content' ) ).not.toBeInTheDocument();
	} );

	test( 'renders with high density', () => {
		const { container } = render(
			<Accordion highDensity>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);

		expect( container.firstChild ).toHaveClass( 'is-high-density' );
	} );

	test( 'renders with default expanded', () => {
		render(
			<Accordion defaultExpanded>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);

		expect( screen.getByText( 'Test Content' ) ).toBeInTheDocument();
	} );

	test( 'renders with custom className', () => {
		const { container } = render(
			<Accordion className="custom-class">
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);

		expect( container.firstChild ).toHaveClass(
			'wcpay-accordion',
			'custom-class'
		);
	} );
} );
