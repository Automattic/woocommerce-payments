/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionHelpText from '../index';

describe( 'FraudProtectionHelpText', () => {
	it( 'renders standard level help text when level prop equals standard', () => {
		const { container: standardHelpText } = render(
			<FraudProtectionHelpText level="standard" />
		);

		expect( standardHelpText ).toMatchSnapshot();
	} );

	it( 'renders high level help text when level prop equals high', () => {
		const { container: highHelpText } = render(
			<FraudProtectionHelpText level="high" />
		);

		expect( highHelpText ).toMatchSnapshot();
	} );
} );
