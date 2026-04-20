/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TipBox from '../';

describe( 'TipBox Component', () => {
	it( 'renders a purple tip', () => {
		const { container } = render(
			<TipBox color="purple">Lorem ipsum</TipBox>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
