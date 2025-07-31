/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';

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
		it( 'copies the text to the clipboard and shows copied state', async () => {
			render(
				<CopyButton
					textToCopy="test_bank_reference_id"
					label="Copy bank reference ID to clipboard"
				/>
			);

			const button = screen.queryByRole( 'button', {
				name: /Copy bank reference ID to clipboard/i,
			} );

			if ( ! button ) {
				throw new Error( 'Button not found' );
			}

			//Mock the clipboard API
			Object.assign( navigator, {
				clipboard: {
					writeText: jest.fn().mockResolvedValueOnce( undefined ),
				},
			} );

			await act( async () => {
				fireEvent.click( button );
			} );

			expect( navigator.clipboard.writeText ).toHaveBeenCalledWith(
				'test_bank_reference_id'
			);
			expect( button ).toHaveClass( 'state--copied' );

			act( () => {
				fireEvent.animationEnd( button );
			} );

			expect( button ).not.toHaveClass( 'state--copied' );
		} );
	} );
} );
