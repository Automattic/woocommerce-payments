/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import {
	OnboardingForm,
	OnboardingTextField,
	OnboardingSelectField,
} from '../form';

// Extend the global type to include wcpaySettings
declare const global: {
	wcpaySettings: {
		connect: { country: string };
	};
};

// Mock functions and state
let nextStep = jest.fn();
let data: Record< string, any > = {};
let errors: Record< string, string > = {};
let touched: Record< string, boolean > = {};
let temp: Record< string, any > = {};

let setData = jest.fn();
let setTouched = jest.fn();
let setTemp = jest.fn();
let validate = jest.fn();
let error = jest.fn();

// Mock context hooks
jest.mock( '../context', () => ( {
	useOnboardingContext: jest.fn( () => ( {
		data,
		errors,
		touched,
		temp,
		setData,
		setTouched,
		setTemp,
	} ) ),
} ) );

jest.mock( 'components/stepper', () => ( {
	useStepperContext: jest.fn( () => ( {
		nextStep,
	} ) ),
} ) );

jest.mock( '../validation', () => ( {
	useValidation: jest.fn( () => ( {
		validate,
		error,
	} ) ),
} ) );

describe( 'Onboarding Form', () => {
	beforeEach( () => {
		// Reset all mocks and state before each test
		nextStep = jest.fn();
		data = {};
		errors = {};
		touched = {};
		temp = {};
		setData = jest.fn();
		setTouched = jest.fn();
		setTemp = jest.fn();
		validate = jest.fn();
		error = jest.fn();

		global.wcpaySettings = {
			connect: { country: 'US' },
		};
	} );

	it( 'calls nextStep when the form is submitted by click and there are no errors', () => {
		render(
			<OnboardingForm>
				<div data-testid="form-content" />
			</OnboardingForm>
		);

		const button = screen.getByRole( 'button' );
		userEvent.click( button );

		expect( nextStep ).toHaveBeenCalled();
	} );

	it( 'calls nextStep when the form is submitted by enter and there are no errors', () => {
		render(
			<OnboardingForm>
				<input data-testid="test-input" />
			</OnboardingForm>
		);

		const field = screen.getByTestId( 'test-input' );
		fireEvent.submit( field );

		expect( nextStep ).toHaveBeenCalled();
	} );

	it( 'calls setTouched and does not call nextStep when there are errors', () => {
		errors = { email: 'invalid' };

		render(
			<OnboardingForm>
				<div data-testid="form-content" />
			</OnboardingForm>
		);

		const button = screen.getByRole( 'button', {
			name: 'Continue',
		} );
		userEvent.click( button );

		expect( nextStep ).not.toHaveBeenCalled();
		expect( setTouched ).toHaveBeenCalled();
	} );

	describe( 'OnboardingTextField', () => {
		it( 'renders component with provided props ', () => {
			data = { annual_revenue: 'Less than $250k' };
			error.mockReturnValue( 'error message' );

			render( <OnboardingTextField name="annual_revenue" /> );

			const textField = screen.getByLabelText(
				'What is your estimated annual Ecommerce revenue (USD)?'
			);
			const errorMessage = screen.getByText( 'error message' );

			expect( textField ).toHaveValue( 'Less than $250k' );
			expect( errorMessage ).toBeInTheDocument();
		} );

		it( 'calls setData on change', () => {
			render( <OnboardingTextField name="annual_revenue" /> );

			const textField = screen.getByLabelText(
				'What is your estimated annual Ecommerce revenue (USD)?'
			);
			textField.focus(); // Workaround for `type` not triggering focus.
			userEvent.type( textField, 'Less than $250k' );

			expect( setData ).toHaveBeenCalledWith( {
				annual_revenue: 'Less than $250k',
			} );

			expect( validate ).not.toHaveBeenCalled();
		} );

		it( 'calls validate on change if touched', () => {
			touched = { annual_revenue: true };
			render( <OnboardingTextField name="annual_revenue" /> );

			const textField = screen.getByLabelText(
				'What is your estimated annual Ecommerce revenue (USD)?'
			);
			userEvent.type( textField, 'John' );

			expect( validate ).toHaveBeenCalledWith( 'John' );
		} );

		it( 'calls validate on change if not focused', () => {
			render( <OnboardingTextField name="annual_revenue" /> );

			const textField = screen.getByLabelText(
				'What is your estimated annual Ecommerce revenue (USD)?'
			);
			userEvent.type( textField, 'John' );

			expect( validate ).toHaveBeenCalledWith( 'John' );
		} );

		it( 'calls validate on blur', () => {
			render( <OnboardingTextField name="annual_revenue" /> );

			const textField = screen.getByLabelText(
				'What is your estimated annual Ecommerce revenue (USD)?'
			);
			userEvent.type( textField, 'John' );
			userEvent.tab();
			fireEvent.focusOut( textField ); // Workaround for onFocus event not firing with jsdom <16.3.0

			expect( validate ).toHaveBeenCalledWith();
		} );
	} );

	describe( 'OnboardingSelectField', () => {
		it( 'renders OnboardingTextField component with provided props ', () => {
			data = { business_type: 'individual' };
			error.mockReturnValue( 'error message' );

			render(
				<OnboardingSelectField
					name="business_type"
					options={ [ { key: 'individual', name: 'individual' } ] }
				/>
			);

			const selectField = screen.getByRole( 'button' );
			const errorMessage = screen.getByText( 'error message' );
			expect( selectField ).toHaveTextContent( 'individual' );
			expect( errorMessage ).toBeInTheDocument();
		} );

		it( 'OnboardingSelectField calls setData and validate on change', () => {
			render(
				<OnboardingSelectField
					name="business_type"
					options={ [ { key: 'individual', name: 'individual' } ] }
				/>
			);

			const selectField = screen.getByRole( 'button' );
			userEvent.click( selectField );
			const option = screen.getByRole( 'option' );
			userEvent.click( option );

			expect( setData ).toHaveBeenCalledWith( {
				business_type: 'individual',
			} );
			expect( validate ).toHaveBeenCalledWith( 'individual' );
		} );
	} );
} );
