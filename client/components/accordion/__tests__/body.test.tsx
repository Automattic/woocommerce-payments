/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { more } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { AccordionBody, AccordionRow } from '../';

describe( 'AccordionBody', () => {
	test( 'renders open by default', () => {
		const { container } = render(
			<AccordionBody title="Test Title">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);

		expect( container.firstChild ).toHaveClass(
			'wcpay-accordion__body',
			'is-opened'
		);
		expect(
			screen.getByRole( 'button', { name: 'Test Title' } )
		).toHaveClass( 'is-md' );
		expect( screen.getByText( 'Test Content' ) ).toBeInTheDocument();
	} );

	test( 'renders with icon', () => {
		const { container } = render(
			<AccordionBody title="Test Title" icon={ more }>
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);

		expect(
			container.querySelector( '.wcpay-accordion__icon' )
		).toBeInTheDocument();
	} );

	test( 'renders with subtitle', () => {
		render(
			<AccordionBody title="Test Title" subtitle="Test Subtitle">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);

		expect(
			screen.getByRole( 'button', { name: /Test Title/ } )
		).toHaveTextContent( 'Test Subtitle' );
	} );

	test( 'renders with large title', () => {
		render(
			<AccordionBody title="Test Title" lg>
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);

		expect(
			screen.getByRole( 'button', { name: 'Test Title' } )
		).toHaveClass( 'is-lg' );
	} );

	test( 'renders with custom className', () => {
		const { container } = render(
			<AccordionBody title="Test Title" className="custom-class">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);

		expect( container.firstChild ).toHaveClass(
			'wcpay-accordion__body',
			'custom-class'
		);
	} );

	test( 'passes the open state to function children', async () => {
		render(
			<AccordionBody title="Test Title">
				{ ( { opened } ) => (
					<AccordionRow>
						{ opened ? 'Opened Content' : 'Closed Content' }
					</AccordionRow>
				) }
			</AccordionBody>
		);

		expect( screen.getByText( 'Opened Content' ) ).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Test Title' } )
		);

		expect( screen.getByText( 'Closed Content' ) ).toBeInTheDocument();
	} );
} );
