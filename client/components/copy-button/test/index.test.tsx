/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { CopyButton } from '..';

describe( 'CopyButton', () => {
	it( 'renders the button correctly', () => {
		const { container: copyButtonContainer } = render(
			<CopyButton
				textToCopy="test_bank_reference_id"
				label="Copy bank reference ID to clipboard"
			/>
		);

		expect( copyButtonContainer ).toMatchSnapshot();
	} );

	describe( 'when the button is clicked', () => {
		beforeAll( () => {
			jest.useFakeTimers();
		} );

		afterAll( () => {
			jest.useRealTimers();
		} );

		it( 'copies the text to the clipboard and shows copied state', () => {
			render(
				<CopyButton
					textToCopy="test_bank_reference_id"
					label="Copy bank reference ID to clipboard"
				/>
			);

			const button = screen.queryByRole( 'button', {
				name: /Copy bank reference ID to clipboard/i,
			} );

			//Mock the clipboard API
			Object.assign( navigator, {
				clipboard: {
					writeText: jest.fn(),
				},
			} );

			act( () => {
				button?.click();
			} );

			expect( navigator.clipboard.writeText ).toHaveBeenCalledWith(
				'test_bank_reference_id'
			);
			expect( button ).toHaveClass( 'state--copied' );

			act( () => {
				jest.advanceTimersByTime( 2000 );
			} );

			expect( button ).not.toHaveClass( 'state--copied' );
		} );
	} );
} );
