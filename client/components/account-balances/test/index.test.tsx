/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';
import { getGreeting } from '../utils';

jest.mock( '../utils', () => ( {
	getGreeting: jest.fn(),
} ) );

const mockGetGreeting = getGreeting as jest.MockedFunction<
	typeof getGreeting
>;

describe( 'AccountBalances', () => {
	// Adding an initial test to be expanded upon.
	mockGetGreeting.mockReturnValue( 'Good afternoon!' );

	test( 'renders', () => {
		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();
	} );
} );
