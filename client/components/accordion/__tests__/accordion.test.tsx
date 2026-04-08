/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { Accordion, AccordionBody, AccordionRow } from '../';

describe( 'Accordion', () => {
	test( 'renders with default props', () => {
		const { container } = render(
			<Accordion>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with high density', () => {
		const { container } = render(
			<Accordion highDensity>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with default expanded', () => {
		const { container } = render(
			<Accordion defaultExpanded>
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with custom className', () => {
		const { container } = render(
			<Accordion className="custom-class">
				<AccordionBody title="Test Title">
					<AccordionRow>Test Content</AccordionRow>
				</AccordionBody>
			</Accordion>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
