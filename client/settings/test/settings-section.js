/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';

describe( 'SettingsSection', () => {
	test( 'renders title and description', () => {
		render( <SettingsSection title="Foo" description="Bar" /> );

		const title = screen.queryByRole( 'heading', { name: 'Foo' } );
		const description = screen.queryByText( 'Bar' );

		expect( title ).toBeInTheDocument();
		expect( description ).toBeInTheDocument();
	} );

	test( 'renders children', () => {
		render(
			<SettingsSection title="Foo" description="Bar">
				<div>Baz</div>
			</SettingsSection>
		);

		expect( screen.queryByText( 'Baz' ) ).toBeInTheDocument();
	} );
} );
