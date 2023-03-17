/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import {
	OnboardingForm,
	OnboardingTextField,
	OnboardingSelectField,
} from '../form';

let nextStep = jest.fn();
let data = {};
let errors = {};
let setData = jest.fn();
let setTouched = jest.fn();
let validate = jest.fn();
let error = jest.fn();

jest.mock( '../context', () => ( {
	useOnboardingContext: jest.fn( () => ( {
		data,
		errors,
		setData,
		setTouched,
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
		setData = jest.fn();
		setTouched = jest.fn();
		validate = jest.fn();
		error = jest.fn();
	} );

	it( 'calls nextStep when the form is submitted and there are no errors', () => {
		render(
			<OnboardingForm>
				<label>
					field
					<input />
				</label>
			</OnboardingForm>
		);

		const button = screen.getByRole( 'button', { name: 'Continue' } );
		userEvent.click( button );

		const field = screen.getByRole( 'textbox', { name: 'field' } );
		userEvent.type( field, '{enter}' );

		expect( nextStep ).toHaveBeenCalledTimes( 2 );
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
	} );
} );
