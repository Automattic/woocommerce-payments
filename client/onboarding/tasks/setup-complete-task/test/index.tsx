/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SetupCompleteTask from '../';

declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

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
