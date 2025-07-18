/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { StepperPanel } from '../stepper-panel';

// Mock the Icon component to avoid dependency on WordPress icons
jest.mock( 'wcpay/components/wp-components-wrapped/components/icon', () => ( {
	Icon: ( { size }: { size: number } ) => (
		<span data-testid="mock-icon">icon-{ size }</span>
	),
} ) );

describe( 'StepperPanel', () => {
	it( 'renders all step labels', () => {
		const steps = [ 'Step 1', 'Step 2', 'Step 3' ];
		render( <StepperPanel steps={ steps } currentStep={ 1 } /> );
		steps.forEach( ( label ) => {
			expect( screen.getByText( label ) ).toBeInTheDocument();
		} );
	} );

	it( 'marks previous steps as complete and current as active', () => {
		const steps = [ 'A', 'B', 'C' ];
		render( <StepperPanel steps={ steps } currentStep={ 1 } /> );
		// Instead, check DOM classes
		const [ step1, step2, step3 ] = screen
			.getAllByText( /A|B|C/ )
			.map( ( labelNode ) => labelNode.closest( '.stepper-step' ) );
		// Step 0 should be complete
		expect( step1 ).toHaveClass( 'complete' );
		// Step 1 should be active
		expect( step2 ).toHaveClass( 'active' );
		// Step 2 should be neither
		expect( step3 ).not.toHaveClass( 'active' );
		expect( step3 ).not.toHaveClass( 'complete' );
	} );

	it( 'renders a mock icon for completed steps', () => {
		const steps = [ 'A', 'B' ];
		render( <StepperPanel steps={ steps } currentStep={ 1 } /> );
		// The first step should have the icon
		expect( screen.getByTestId( 'mock-icon' ) ).toBeInTheDocument();
	} );

	it( 'renders step numbers for incomplete steps', () => {
		const steps = [ 'A', 'B' ];
		render( <StepperPanel steps={ steps } currentStep={ 0 } /> );
		// The first step should show 1, the second should show 2
		expect( screen.getByText( '1' ) ).toBeInTheDocument();
		expect( screen.getByText( '2' ) ).toBeInTheDocument();
	} );

	it( 'renders step lines between all but the last step', () => {
		const steps = [ 'A', 'B', 'C' ];
		render( <StepperPanel steps={ steps } currentStep={ 0 } /> );
		// There should be steps.length - 1 lines
		expect( document.querySelectorAll( '.stepper-line' ) ).toHaveLength(
			2
		);
	} );

	describe( 'clickable steps', () => {
		it( 'makes completed and current steps clickable when onStepClick is provided', () => {
			const steps = [ 'A', 'B', 'C' ];
			const onStepClick = jest.fn();
			render(
				<StepperPanel
					steps={ steps }
					currentStep={ 1 }
					onStepClick={ onStepClick }
				/>
			);

			const stepElements = screen
				.getAllByText( /A|B|C/ )
				.map( ( label ) => label.closest( '.stepper-step' ) );

			// First step (completed) should be clickable
			expect( stepElements[ 0 ] ).toHaveClass( 'clickable' );
			// Second step (current) should be clickable
			expect( stepElements[ 1 ] ).toHaveClass( 'clickable' );
			// Third step (future) should not be clickable
			expect( stepElements[ 2 ] ).toHaveClass( 'clickable' );
		} );

		it( 'calls onStepClick with correct index when clicking a step', () => {
			const steps = [ 'A', 'B', 'C' ];
			const onStepClick = jest.fn();
			render(
				<StepperPanel
					steps={ steps }
					currentStep={ 1 }
					onStepClick={ onStepClick }
				/>
			);

			// Click the first step (completed)
			fireEvent.click( screen.getByText( 'A' ) );
			expect( onStepClick ).toHaveBeenCalledWith( 0 );

			// Click the second step (current)
			fireEvent.click( screen.getByText( 'B' ) );
			expect( onStepClick ).toHaveBeenCalledWith( 1 );
		} );

		it( 'does not make steps clickable when onStepClick is not provided', () => {
			const steps = [ 'A', 'B', 'C' ];
			render( <StepperPanel steps={ steps } currentStep={ 1 } /> );

			const stepElements = screen
				.getAllByText( /A|B|C/ )
				.map( ( label ) => label.closest( '.stepper-step' ) );

			// No steps should be clickable
			stepElements.forEach( ( step ) => {
				expect( step ).not.toHaveClass( 'clickable' );
			} );
		} );
	} );
} );
