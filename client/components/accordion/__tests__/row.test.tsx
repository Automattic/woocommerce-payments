/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { AccordionRow } from '../';

describe( 'AccordionRow', () => {
	test( 'renders with default props', () => {
		const { container } = render(
			<AccordionRow>Test Content</AccordionRow>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with custom className', () => {
		const { container } = render(
			<AccordionRow className="custom-class">Test Content</AccordionRow>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with complex content', () => {
		const { container } = render(
			<AccordionRow>
				<div>
					<h3>Title</h3>
					<p>Description</p>
				</div>
			</AccordionRow>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
