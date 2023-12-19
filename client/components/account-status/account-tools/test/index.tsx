/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { AccountTools } from '..';

const accountLink = '/onboarding';
const openModal = jest.fn();

describe( 'AccountTools', () => {
	it( 'should render', () => {
		const { container } = render(
			<AccountTools accountLink={ accountLink } openModal={ openModal } />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
