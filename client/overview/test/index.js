/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OverviewPage from '../';
import { getTasks } from '../task-list/tasks';

jest.mock( '../task-list/tasks', () => ( { getTasks: jest.fn() } ) );

describe( 'Overview page', () => {
	it( 'Skips rendering task list when there are no tasks', () => {
		global.wcpaySettings = {};
		getTasks.mockReturnValue( [] );
		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.woocommerce-experimental-list' )
		).toBeNull();
	} );
} );
