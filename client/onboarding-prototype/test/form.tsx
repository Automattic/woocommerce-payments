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
	OnboardingPhoneNumberField,
} from '../form';

declare const global: {
	wcpaySettings: {
		connect: { country: string };
	};
};

let nextStep = jest.fn();
let data = {};
let errors = {};
let temp = {};

let setData = jest.fn();
let setTouched = jest.fn();
let setTemp = jest.fn();
let validate = jest.fn();
let error = jest.fn();

jest.mock( '../context', () => ( {
	useOnboardingContext: jest.fn( () => ( {
		data,
		errors,
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

describe( 'Progressive Onboarding Prototype Form', () => {
	beforeEach( () => {
		nextStep = jest.fn();
		data = {};
		errors = {};
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
		render( <OnboardingForm /> );

		const button = screen.getByRole( 'button' );
		userEvent.click( button );

		expect( nextStep ).toHaveBeenCalled();
	} );

	it( 'calls nextStep when the form is submitted by enter and there are no errors', () => {
		render(
			<OnboardingForm>
				<input />
			</OnboardingForm>
		);

		const field = screen.getByRole( 'textbox' );
		fireEvent.submit( field );

		expect( nextStep ).toHaveBeenCalled();
	} );

	it( 'calls setTouched and does not call nextStep when there are errors', () => {
		errors = { email: 'invalid' };

		render( <OnboardingForm /> );

		const button = screen.getByRole( 'button', {
			name: 'Continue',
		} );
		userEvent.click( button );

		expect( nextStep ).not.toHaveBeenCalled();
		expect( setTouched ).toHaveBeenCalled();
	} );

	describe( 'OnboardingTextField', () => {
		it( 'renders component with provided props ', () => {
			data = { 'individual.first_name': 'John' };
			error.mockReturnValue( 'error message' );

			render( <OnboardingTextField name="individual.first_name" /> );

			const textField = screen.getByLabelText( 'First name' );
			const errorMessage = screen.getByText( 'error message' );

			expect( textField ).toHaveValue( 'John' );
			expect( errorMessage ).toBeInTheDocument();
		} );

		it( 'calls setData and validate on change', () => {
			render( <OnboardingTextField name="individual.first_name" /> );

			const textField = screen.getByLabelText( 'First name' );
			userEvent.type( textField, 'John' );

			expect( setData ).toHaveBeenCalledWith( {
				'individual.first_name': 'John',
			} );
			expect( validate ).toHaveBeenCalledWith( 'John' );
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

		describe( 'OnboardingPhoneNumberField', () => {
			it( 'renders component with provided props ', () => {
				data = { phone: '+123' };
				error.mockReturnValue( 'error message' );

				render( <OnboardingPhoneNumberField name="phone" /> );

				const textField = screen.getByLabelText(
					'What’s your mobile phone number?'
				);
				const errorMessage = screen.getByText( 'error message' );

				expect( textField ).toHaveValue( '23' );
				expect( errorMessage ).toBeInTheDocument();
			} );

			it( 'calls setTemp, setData and validate on change', () => {
				render( <OnboardingPhoneNumberField name="phone" /> );

				const textField = screen.getByLabelText(
					'What’s your mobile phone number?'
				);
				userEvent.type( textField, '23' );

				expect( setTemp ).toHaveBeenCalledWith( {
					phoneCountryCode: 'US',
				} );

				expect( setData ).toHaveBeenCalledWith( {
					phone: '+123',
				} );
				expect( validate ).toHaveBeenCalledWith( '+123' );
			} );
		} );
	} );
} );
