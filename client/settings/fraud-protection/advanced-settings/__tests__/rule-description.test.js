/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import FraudProtectionRuleDescription from '../rule-description';
/**
 * Internal dependencies
 */
describe( 'Fraud protection rule description tests', () => {
	test( 'renders correctly', () => {
		const container = render(
			<FraudProtectionRuleDescription>
				test content
			</FraudProtectionRuleDescription>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText( 'How does this filter protect me?' )
		).toBeInTheDocument();
		expect( container.queryByText( 'test content' ) ).toBeInTheDocument();
	} );
} );
