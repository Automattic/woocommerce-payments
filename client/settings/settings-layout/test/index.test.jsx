/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsLayout from '..';

describe( 'SettingsLayout', () => {
	it( 'renders its children', () => {
		render(
			<SettingsLayout>
				<div>Children</div>
			</SettingsLayout>
		);

		expect( screen.queryByText( 'Children' ) ).toBeInTheDocument();
	} );
} );
