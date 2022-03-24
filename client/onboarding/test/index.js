/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import OnboardingPage from '..';
import AddBusinessInfo from '../tasks/add-business-info-task';
import SetupComplete from '../tasks/setup-complete-task';

jest.mock( '../tasks/add-business-info-task', () => jest.fn() );
jest.mock( '../tasks/setup-complete-task', () => jest.fn() );

describe( 'OnboardingPage', () => {
	beforeEach( () => {
		AddBusinessInfo.mockReturnValue( <p>Add Business Info</p> );
		SetupComplete.mockReturnValue( <p>Setup Complete</p> );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'renders page', () => {
		const { container: list } = render( <OnboardingPage /> );
		expect( list ).toMatchSnapshot();
	} );
} );
