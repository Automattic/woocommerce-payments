/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import NewPill from '..';

describe( 'NewPill', () => {
	it( 'renders', () => {
		const { container: newPillComponent } = render( <NewPill /> );

		expect( newPillComponent ).toMatchSnapshot();
	} );
} );
