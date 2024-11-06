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
import userEvent from '@testing-library/user-event';

declare const global: {
	wcpaySettings: {
		isFRTReviewFeatureActive?: boolean;
	};
};

interface mockContext {
	protectionSettingsUI: {
		[ key: string ]: {
			enabled: boolean;
			block: boolean;
		};
	};
	setProtectionSettingsUI: jest.Mock;
	setIsDirty: jest.Mock;
}

describe( 'Fraud protection rule toggle tests', () => {
	global.wcpaySettings = {
		isFRTReviewFeatureActive: false,
	};

	let mockContext: mockContext = {
		protectionSettingsUI: {
			test_rule: {
				enabled: false,
				block: false,
			},
		},
		setProtectionSettingsUI: jest.fn(),
		setIsDirty: jest.fn(),
	};

	beforeEach( () => {
		mockContext = {
			protectionSettingsUI: {
				test_rule: {
					enabled: false,
					block: false,
				},
			},
			setProtectionSettingsUI: jest.fn(),
			setIsDirty: jest.fn(),
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
				'When enabled, the payment will be blocked.'
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
	test( 'calls the toggle enable function when clicking in the label', () => {
		mockContext.protectionSettingsUI.test_rule.enabled = false;

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
		userEvent.click( activationToggle );

		expect( mockContext.setProtectionSettingsUI ).toHaveBeenCalled();
	} );
} );
