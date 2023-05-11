/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProtectionLevelModalNotice from '../index';

describe( 'ProtectionLevelModalNotice', () => {
	it( 'renders the standard notice when the level prop is standard', () => {
		const { container: standardNotice } = render(
			<ProtectionLevelModalNotice level="standard" />
		);

		expect( standardNotice ).toMatchSnapshot();
	} );

	it( 'renders the high notice when the level prop is high', () => {
		const { container: highNotice } = render(
			<ProtectionLevelModalNotice level="high" />
		);

		expect( highNotice ).toMatchSnapshot();
	} );
} );
