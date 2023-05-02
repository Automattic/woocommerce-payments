/**
 * External dependencies
 */
import React from 'react';
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
