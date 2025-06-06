/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CoverLetter from '../cover-letter';

describe( 'CoverLetter', () => {
	const baseProps = {
		value: 'Cover letter text',
		onChange: jest.fn(),
	};

	it( 'renders textarea and print button', () => {
		render( <CoverLetter { ...baseProps } /> );
		expect( screen.getByLabelText( /COVER LETTER/i ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /View cover letter/i } )
		).toBeInTheDocument();
	} );

	it( 'calls onChange when textarea changes', () => {
		render( <CoverLetter { ...baseProps } /> );
		fireEvent.change( screen.getByLabelText( /COVER LETTER/i ), {
			target: { value: 'New text' },
		} );
		expect( baseProps.onChange ).toHaveBeenCalledWith( 'New text' );
	} );

	it( 'renders print-only pre with value', () => {
		render( <CoverLetter { ...baseProps } /> );
		const all = screen.getAllByText( 'Cover letter text' );
		const pre = all.find( ( el ) => el.tagName === 'PRE' );
		expect( pre ).toBeTruthy();
	} );
} );
