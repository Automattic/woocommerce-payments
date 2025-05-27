/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */
import { Stepper, StepperIndicator, useStepperContext } from '../';

const Step: React.FC< { name: string; label: string } > = ( { label } ) => {
	const { nextStep, prevStep } = useStepperContext();
	return (
		<div>
			<span data-testid="step-label">{ label }</span>
			<button onClick={ prevStep } data-testid="prev">
				Prev
			</button>
			<button onClick={ nextStep } data-testid="next">
				Next
			</button>
		</div>
	);
};

describe( 'Stepper', () => {
	const steps = [
		<Step key="1" name="step1" label="Step 1" />,
		<Step key="2" name="step2" label="Step 2" />,
		<Step key="3" name="step3" label="Step 3" />,
	];

	it( 'renders the first step by default', () => {
		render( <Stepper>{ steps }</Stepper> );
		expect( screen.getByTestId( 'step-label' ) ).toHaveTextContent(
			'Step 1'
		);
	} );

	it( 'navigates to the next and previous steps', () => {
		render( <Stepper>{ steps }</Stepper> );
		// Go to next step
		fireEvent.click( screen.getByTestId( 'next' ) );
		expect( screen.getByTestId( 'step-label' ) ).toHaveTextContent(
			'Step 2'
		);
		// Go to next step
		fireEvent.click( screen.getByTestId( 'next' ) );
		expect( screen.getByTestId( 'step-label' ) ).toHaveTextContent(
			'Step 3'
		);
		// Go back
		fireEvent.click( screen.getByTestId( 'prev' ) );
		expect( screen.getByTestId( 'step-label' ) ).toHaveTextContent(
			'Step 2'
		);
	} );

	it( 'calls onStepChange, onComplete, and onExit', () => {
		const onStepChange = jest.fn();
		const onComplete = jest.fn();
		const onExit = jest.fn();
		render(
			<Stepper
				onStepChange={ onStepChange }
				onComplete={ onComplete }
				onExit={ onExit }
			>
				{ steps }
			</Stepper>
		);
		// Next to step 2
		fireEvent.click( screen.getByTestId( 'next' ) );
		expect( onStepChange ).toHaveBeenCalledWith( 'step2' );
		// Next to step 3
		fireEvent.click( screen.getByTestId( 'next' ) );
		expect( onStepChange ).toHaveBeenCalledWith( 'step3' );
		// Next to complete
		fireEvent.click( screen.getByTestId( 'next' ) );
		expect( onComplete ).toHaveBeenCalled();
		// Back to step 2
		fireEvent.click( screen.getByTestId( 'prev' ) );
		expect( onStepChange ).toHaveBeenCalledWith( 'step2' );
		// Back to step 1
		fireEvent.click( screen.getByTestId( 'prev' ) );
		expect( onStepChange ).toHaveBeenCalledWith( 'step1' );
		// Back to exit
		fireEvent.click( screen.getByTestId( 'prev' ) );
		expect( onExit ).toHaveBeenCalled();
	} );
} );

describe( 'StepperIndicator', () => {
	it( 'shows correct active and complete states', () => {
		const stepLabels = [ 'One', 'Two', 'Three' ];
		const { container, rerender } = render(
			<StepperIndicator steps={ stepLabels } currentStep={ 1 } />
		);
		// First step should be complete, second active, third inactive
		const steps = container.querySelectorAll( '.stepper-step' );
		expect( steps[ 0 ] ).toHaveClass( 'complete' );
		expect( steps[ 1 ] ).toHaveClass( 'active' );
		expect( steps[ 2 ] ).not.toHaveClass( 'active' );
		// Move to last step
		rerender( <StepperIndicator steps={ stepLabels } currentStep={ 2 } /> );
		expect( steps[ 1 ] ).toHaveClass( 'complete' );
		expect( steps[ 2 ] ).toHaveClass( 'active' );
	} );
} );
