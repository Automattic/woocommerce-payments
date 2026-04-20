/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { more } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { AccordionBody, AccordionRow } from '../';

describe( 'AccordionBody', () => {
	test( 'renders with default props', () => {
		const { container } = render(
			<AccordionBody title="Test Title">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with icon', () => {
		const { container } = render(
			<AccordionBody title="Test Title" icon={ more }>
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with subtitle', () => {
		const { container } = render(
			<AccordionBody title="Test Title" subtitle="Test Subtitle">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with large title', () => {
		const { container } = render(
			<AccordionBody title="Test Title" lg>
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with custom className', () => {
		const { container } = render(
			<AccordionBody title="Test Title" className="custom-class">
				<AccordionRow>Test Content</AccordionRow>
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with function children', () => {
		const { container } = render(
			<AccordionBody title="Test Title">
				{ ( { opened } ) => (
					<AccordionRow>
						{ opened ? 'Opened Content' : 'Closed Content' }
					</AccordionRow>
				) }
			</AccordionBody>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
