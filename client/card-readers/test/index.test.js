/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ConnectedReaders from '..';

describe( 'CardReadersSettings', () => {
	it( 'Card Readers tabs renders', () => {
		render( <ConnectedReaders /> );

		expect( screen.queryByText( 'Connected readers' ) ).toBeInTheDocument();

		expect( screen.queryByText( 'Receipt details' ) ).toBeInTheDocument();
	} );
} );
