/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import { CompanyDataTask } from '../company-data-task';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const noop = () => undefined;

describe( 'CompanyDataTask', () => {
	it( 'resets the inputs synchronously when placeholder props change', () => {
		const { rerender } = render(
			<CompanyDataTask
				onCompleted={ noop }
				vatNumber={ null }
				placeholderCompanyName="A"
				placeholderCompanyAddress="X"
			/>
		);

		expect( screen.getByDisplayValue( 'A' ) ).toBeInTheDocument();
		expect( screen.getByDisplayValue( 'X' ) ).toBeInTheDocument();

		// A single synchronous rerender; the new placeholder values must be
		// reflected in the inputs on the very next getByDisplayValue read, with
		// no await and no second rerender. This pins the render-time
		// placeholder-reset ordering invariant that prevents an intermediate
		// committed state from being visible to the user.
		rerender(
			<CompanyDataTask
				onCompleted={ noop }
				vatNumber={ null }
				placeholderCompanyName="B"
				placeholderCompanyAddress="Y"
			/>
		);

		expect( screen.getByDisplayValue( 'B' ) ).toBeInTheDocument();
		expect( screen.getByDisplayValue( 'Y' ) ).toBeInTheDocument();
	} );
} );
