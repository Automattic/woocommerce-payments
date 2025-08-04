/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React, { act } from 'react';

/**
 * Internal dependencies
 */
import ConnectedReaders from '..';

describe( 'CardReadersSettings', () => {
	it( 'Card Readers tabs renders', async () => {
		await act( async () => {
			render( <ConnectedReaders /> );
		} );

		expect( screen.queryByText( 'Connected readers' ) ).toBeInTheDocument();
	} );
} );
