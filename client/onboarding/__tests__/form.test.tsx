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

declare const global: {
	wcpaySettings: {
		connect: { country: string };
	};
};

let nextStep = jest.fn();
let data = {};
let errors = {};
let touched = {};
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

	it( 'calls nextStep when the form is submitted by click and there are no errors', async () => {
		render( <OnboardingForm /> );

		const button = screen.getByRole( 'button' );
		await userEvent.click( button );

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

	it( 'calls setTouched and does not call nextStep when there are errors', async () => {
		errors = { email: 'invalid' };

		render( <OnboardingForm /> );

		const button = screen.getByRole( 'button', {
			name: 'Continue',
		} );
		await userEvent.click( button );

		expect( nextStep ).not.toHaveBeenCalled();
		expect( setTouched ).toHaveBeenCalled();
	} );

	describe( 'OnboardingTextField', () => {
		it( 'renders component with provided props ', () => {
			data = { country: 'United States' };
			error.mockReturnValue( 'error message' );

			render( <OnboardingTextField name="country" /> );

			const textField = screen.getByLabelText(
				'Where is your business located?'
			);
			const errorMessage = screen.getByText( 'error message' );

			expect( textField ).toHaveValue( 'United States' );
			expect( errorMessage ).toBeInTheDocument();
		} );

		it( 'calls setData on change', async () => {
			render( <OnboardingTextField name="country" /> );

			const textField = screen.getByLabelText(
				'Where is your business located?'
			);
			textField.focus(); // Workaround for `type` not triggering focus.
			await userEvent.paste( textField, 'United States' );

			expect( setData ).toHaveBeenCalledWith( {
				country: 'United States',
			} );

			expect( validate ).not.toHaveBeenCalled();
		} );

		it( 'calls validate on change if touched', async () => {
			touched = { country: true };
			render( <OnboardingTextField name="country" /> );

			const textField = screen.getByLabelText(
				'Where is your business located?'
			);
			await userEvent.paste( textField, 'John' );

			expect( validate ).toHaveBeenCalledWith( 'John' );
		} );

		it( 'calls validate on change if not focused', async () => {
			render( <OnboardingTextField name="country" /> );

			const textField = screen.getByLabelText(
				'Where is your business located?'
			);
			// Use fireEvent to change value without auto-focusing
			fireEvent.change( textField, { target: { value: 'John' } } );

			expect( validate ).toHaveBeenCalledWith( 'John' );
		} );

		it( 'calls validate on blur', async () => {
			render( <OnboardingTextField name="country" /> );

			const textField = screen.getByLabelText(
				'Where is your business located?'
			);
			await userEvent.paste( textField, 'John' );
			await userEvent.tab();
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

		it( 'OnboardingSelectField calls setData and validate on change', async () => {
			render(
				<OnboardingSelectField
					name="business_type"
					options={ [ { key: 'individual', name: 'individual' } ] }
				/>
			);

			const selectField = screen.getByRole( 'button' );
			await userEvent.click( selectField );
			const option = screen.getByRole( 'option' );
			await userEvent.click( option );

			expect( setData ).toHaveBeenCalledWith( {
				business_type: 'individual',
			} );
			expect( validate ).toHaveBeenCalledWith( 'individual' );
		} );
	} );
} );
