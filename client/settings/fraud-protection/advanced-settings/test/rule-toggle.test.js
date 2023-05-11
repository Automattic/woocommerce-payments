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
				'When enabled, the payment method will not be charged until you review and approve the transaction.'
			)
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
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect(
			container.queryByText(
				'The payment method will not be charged until you review and approve the transaction.'
			)
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
				>
					test content
				</FraudProtectionRuleToggle>
			</FraudPreventionSettingsContext.Provider>
		);
		const blockRadio = container.getByLabelText( 'Block Payment' );
		const reviewRadio = container.getByLabelText(
			'Authorize and hold for review'
		);

		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeFalsy();

		userEvent.click( blockRadio );
		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeTruthy();

		userEvent.click( reviewRadio );
		expect( mockContext.protectionSettingsUI.test_rule.block ).toBeFalsy();
	} );
} );
