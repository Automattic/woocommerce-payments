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

		const title = screen.getByRole( 'heading', { name: 'Foo' } );
		const description = screen.getByText( 'Bar' );

		expect( title ).toBeInTheDocument();
		expect( description ).toBeInTheDocument();
	} );

	test( 'renders children', () => {
		render(
			<SettingsSection title="Foo" description="Bar">
				<div>Baz</div>
			</SettingsSection>
		);

		expect( screen.getByText( 'Baz' ) ).toBeInTheDocument();
	} );
} );
