/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import RuleCardInfoNoticesContext from '../rule-card-notice-context';
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
	} );

	test( 'renders warning box correctly', () => {
		const container = render(
			<FraudProtectionRuleCardNotice type="warning">
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders error box correctly', () => {
		const container = render(
			<FraudProtectionRuleCardNotice type="error">
				This is the test content
			</FraudProtectionRuleCardNotice>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( "doesn't render box when type is not a valid type", () => {
		const testValue = 'This is the test content';
		const { queryByText } = render(
			<FraudProtectionRuleCardNotice type="invalid">
				{ testValue }
			</FraudProtectionRuleCardNotice>
		);

		expect(
			queryByText( testValue, {
				ignore: '.a11y-speak-region',
			} )
		).not.toBeInTheDocument();
	} );
	test( 'renders a warning as info on the payment settings screen', () => {
		const { container } = render(
			<RuleCardInfoNoticesContext.Provider value={ true }>
				<FraudProtectionRuleCardNotice type="warning">
					This is the test content
				</FraudProtectionRuleCardNotice>
			</RuleCardInfoNoticesContext.Provider>
		);
		expect( container.querySelector( '.is-info' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.is-warning' )
		).not.toBeInTheDocument();
	} );
} );
