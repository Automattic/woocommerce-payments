/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ReadersList from '..';

describe( 'ReadersList', () => {
	it( 'Readers list renders', () => {
		render( <ReadersList /> );

		expect(
			screen.queryByText( 'Connected card readers' )
		).toBeInTheDocument();
	} );
} );
