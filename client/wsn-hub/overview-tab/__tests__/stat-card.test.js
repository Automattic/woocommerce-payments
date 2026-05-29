/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import StatCard from '../stat-card';

describe( 'StatCard', () => {
	it( 'renders the label and the value', () => {
		render( <StatCard label="Network Orders" value="41" /> );

		expect( screen.getByText( 'Network Orders' ) ).toBeInTheDocument();
		expect( screen.getByText( '41' ) ).toBeInTheDocument();
	} );

	it( 'renders an em dash when value is null', () => {
		render( <StatCard label="Network Orders" value={ null } /> );

		expect( screen.getByText( '—' ) ).toBeInTheDocument();
	} );

	it( 'renders an em dash when value is undefined', () => {
		render( <StatCard label="Network Orders" /> );

		expect( screen.getByText( '—' ) ).toBeInTheDocument();
	} );

	it( 'inlines the reference denominator when provided', () => {
		render(
			<StatCard label="Network Orders" value="41" reference="156" />
		);

		// The "/ 156" piece is a sibling span that visually reads as a
		// denominator subordinate to the main value.
		expect( screen.getByText( /\/ 156/ ) ).toBeInTheDocument();
	} );

	it( 'does NOT render a reference span when reference is null', () => {
		render(
			<StatCard label="Network Orders" value="41" reference={ null } />
		);

		// The slash character should not appear when reference is null —
		// guard against accidentally rendering "41 / null" or similar.
		expect( screen.queryByText( /\// ) ).not.toBeInTheDocument();
	} );
} );
