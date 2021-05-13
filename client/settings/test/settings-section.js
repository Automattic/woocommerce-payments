/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';

describe( 'SettingsSection', () => {
	test( 'renders the Description', () => {
		render(
			<SettingsSection
				Description={ () => (
					<>
						<h2>Foo</h2>
						<p>Bar</p>
					</>
				) }
			/>
		);

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
