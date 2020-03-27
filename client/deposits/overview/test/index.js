/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsOverview from '../';

describe( 'Deposits Overview', () => {
	test( 'renders correctly', () => {
		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );
} );
