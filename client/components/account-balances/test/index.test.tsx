/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';

describe( 'AccountBalances', () => {
	// Adding an initial test to be expanded upon.
	test( 'renders', () => {
		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();
	} );
} );
