/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import SetupCompleteTask from '../';

describe( 'SetupCompleteTask', () => {
	it( 'renders page', () => {
		global.wcpaySettings = {
			connectUrl: '/connect',
		};

		const { container: task } = render(
			<SetupCompleteTask args={ { country: 'US', type: 'individual' } } />
		);
		expect( task ).toMatchSnapshot();
	} );
} );
