/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

jest.mock( '@wordpress/components', () => ( {
	Popover: ( {
		children,
		onClose,
	}: {
		children: React.ReactNode;
		onClose: () => void;
	} ) => (
		<div role="dialog" aria-label="Custom date filter">
			<button onClick={ onClose }>Close popover</button>
			{ children }
		</div>
	),
} ) );

/**
 * Internal dependencies
 */
import { CustomDateFilterPopover } from '../custom-date-filter-popover';

describe( 'CustomDateFilterPopover', () => {
	it( 'returns focus to the fallback target when the anchor is removed', async () => {
		const anchor = document.createElement( 'button' );
		const fallback = document.createElement( 'div' );
		fallback.tabIndex = -1;
		document.body.append( anchor, fallback );

		render(
			<CustomDateFilterPopover
				anchor={ anchor }
				fallbackFocus={ fallback }
				initialValue={ undefined }
				onChange={ jest.fn() }
				onClose={ jest.fn() }
			/>
		);

		anchor.remove();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Close popover' } )
		);

		await waitFor( () => expect( fallback ).toHaveFocus() );
	} );
} );
