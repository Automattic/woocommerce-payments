/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import FraudProtectionRuleCard from '../rule-card';
/**
 * Internal dependencies
 */
describe( 'Fraud protection rule card tests', () => {
	test( 'renders correctly', () => {
		const container = render(
			<FraudProtectionRuleCard
				title="test title"
				description="test description"
				id="test-id"
			>
				test content
			</FraudProtectionRuleCard>
		);
		expect( container ).toMatchSnapshot();
		expect( container.queryByText( 'test title' ) ).toBeInTheDocument();
		expect(
			container.queryByText( 'test description' )
		).toBeInTheDocument();
		expect( container.queryByText( 'test content' ) ).toBeInTheDocument();
	} );
} );
