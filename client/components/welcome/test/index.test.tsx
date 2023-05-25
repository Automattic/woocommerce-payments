/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Welcome from '..';
import { getGreeting } from '../utils';
import { useCurrentWpUser } from '../hooks';

jest.mock( '../utils', () => ( {
	getGreeting: jest.fn(),
} ) );

jest.mock( '../hooks', () => ( {
	useCurrentWpUser: jest.fn(),
} ) );

const mockGetGreeting = getGreeting as jest.MockedFunction<
	typeof getGreeting
>;
const mockUseCurrentWpUser = useCurrentWpUser as jest.MockedFunction<
	typeof useCurrentWpUser
>;

const mockUser = {
	id: 123,
	first_name: 'Tester',
	username: 'admin',
	name: 'admin',
	nickname: 'Tester-nickname',
	last_name: 'Tester-lastname',
	email: 'tester@test.com',
	locale: 'en',
};

describe( 'Welcome', () => {
	test( 'renders the correct greeting in the header', () => {
		const expectedGreeting = 'Good afternoon, Tester 👋';
		mockGetGreeting.mockReturnValue( expectedGreeting );
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <Welcome /> );
		getByText( expectedGreeting );
	} );
} );
