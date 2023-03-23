/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudPreventionSettingsContext from '../context';

describe( 'Fraud protection rule toggle tests', () => {
	let mockContext = null;

	beforeEach( () => {
		mockContext = {
			protectionSettingsUI: {
				test_rule: {
					enabled: false,
					block: false,
				},
			},
			setProtectionSettingsUI: jest.fn(),
			setProtectionSettingsChanged: jest.fn(),
		};
	} );

	test( 'renders correctly when disabled', () => {
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
					helpText={ 'This is the help text of this toggle.' }
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.getByLabelText( 'Test rule toggle' )
		).not.toBeChecked();
		expect(
			container.queryByText( 'This is the help text of this toggle.' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Block Payment' )
		).not.toBeInTheDocument();
		expect(
			container.queryByText( 'test content' )
		).not.toBeInTheDocument();
	} );
	test( 'renders correctly when enabled', () => {
		mockContext.protectionSettingsUI.test_rule.enabled = true;
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
					helpText={ 'This is the help text of this toggle.' }
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText( 'This is the help text of this toggle.' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
		expect( container.getByLabelText( 'Test rule toggle' ) ).toBeChecked();
		expect( container.queryByText( 'Block Payment' ) ).toBeInTheDocument();
		expect( container.getByLabelText( 'Block Payment' ) ).not.toBeChecked();
		expect( container.queryByText( 'test content' ) ).toBeInTheDocument();
	} );
	test( 'renders correctly when enabled and blocked', () => {
		mockContext.protectionSettingsUI.test_rule.enabled = true;
		mockContext.protectionSettingsUI.test_rule.block = true;
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
					helpText={ 'This is the help text of this toggle.' }
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText( 'This is the help text of this toggle.' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
		expect( container.getByLabelText( 'Test rule toggle' ) ).toBeChecked();
		expect( container.queryByText( 'Block Payment' ) ).toBeInTheDocument();
		expect( container.getByLabelText( 'Block Payment' ) ).toBeChecked();
		expect( container.queryByText( 'test content' ) ).toBeInTheDocument();
	} );
	test( 'sets the value correctly when enabled', () => {
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
					helpText={ 'This is the help text of this toggle.' }
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		const activationToggle = container.getByLabelText( 'Test rule toggle' );
		expect(
			mockContext.protectionSettingsUI.test_rule.enabled
		).toBeFalsy();
		activationToggle.click();
		expect(
			mockContext.protectionSettingsUI.test_rule.enabled
		).toBeTruthy();
		activationToggle.click();
		expect(
			mockContext.protectionSettingsUI.test_rule.enabled
		).toBeFalsy();
	} );
	test( 'sets the value correctly when block is selected', () => {
		mockContext.protectionSettingsUI.test_rule.enabled = true;
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
					helpText={ 'This is the help text of this toggle.' }
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		const activationToggle = container.getByLabelText( 'Block Payment' );
		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeFalsy();
		activationToggle.click();
		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeTruthy();
		activationToggle.click();
		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeFalsy();
	} );
} );
