/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
/**
 * Internal dependencies
 */
describe( 'Fraud protection rule card notice tests', () => {
	test( 'renders info box correctly', () => {
		const container = render(
			<FraudProtectionRuleCardNotice type="info">
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByTestId( 'rule-card-notice-info-icon-svg' )
		).toBeInTheDocument();
	} );
	test( 'renders warning box correctly', () => {
		const container = render(
			<FraudProtectionRuleCardNotice type="warning">
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByTestId( 'rule-card-notice-warning-icon-svg' )
		).toBeInTheDocument();
	} );
	test( 'renders error box correctly', () => {
		const container = render(
			<FraudProtectionRuleCardNotice type="error">
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByTestId( 'rule-card-notice-error-icon-svg' )
		).toBeInTheDocument();
	} );
	test( "doesn't render box when type is missing", () => {
		const container = render(
			<FraudProtectionRuleCardNotice>
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container.baseElement.innerHTML ).toEqual( '<div></div>' );
	} );
} );
