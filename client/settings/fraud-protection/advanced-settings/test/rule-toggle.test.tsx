/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudPreventionSettingsContext from '../context';

interface mockContext {
	protectionSettingsUI: {
		[ key: string ]: {
			enabled: boolean;
			block: boolean;
		};
	};
	protectionSettingsChanged: boolean;
	setProtectionSettingsUI: jest.Mock;
	setProtectionSettingsChanged: jest.Mock;
}

describe( 'Fraud protection rule toggle tests', () => {
	let mockContext: mockContext = {
		protectionSettingsUI: {
			test_rule: {
				enabled: false,
				block: false,
			},
		},
		protectionSettingsChanged: false,
		setProtectionSettingsUI: jest.fn(),
		setProtectionSettingsChanged: jest.fn(),
	};

	beforeEach( () => {
		mockContext = {
			protectionSettingsUI: {
				test_rule: {
					enabled: false,
					block: false,
				},
			},
			protectionSettingsChanged: false,
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
			container.queryByText(
				'When enabled, the payment method will be blocked.'
			)
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
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
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText( 'The payment will be blocked.' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
		expect( container.getByLabelText( 'Test rule toggle' ) ).toBeChecked();
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
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText( 'The payment will be blocked.' )
		).toBeInTheDocument();
		expect(
			container.queryByText( 'Test rule toggle' )
		).toBeInTheDocument();
		expect( container.getByLabelText( 'Test rule toggle' ) ).toBeChecked();
		expect( container.queryByText( 'test content' ) ).toBeInTheDocument();
	} );
	test( 'sets the value correctly when enabled', () => {
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<FraudProtectionRuleToggle
					setting={ 'test_rule' }
					label={ 'Test rule toggle' }
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
} );
