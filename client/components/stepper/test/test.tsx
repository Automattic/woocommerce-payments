/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { Stepper, useStepperContext } from '../';

const TestStep: React.FC< { name?: string } > = ( { name } ) => {
	const { currentStep, nextStep, prevStep, progress } = useStepperContext();

	return (
		<>
			<h1>{ name ?? currentStep }</h1>
			<input value={ progress } readOnly />
			<button onClick={ prevStep }>Prev</button>
			<button onClick={ nextStep }>Next</button>
		</>
	);
};

describe( 'Stepper', () => {
	it( 'renders the first step by default', () => {
		render(
			<Stepper>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);
		expect( screen.queryByText( 'Step 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Step 2' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the next step when the "Next" button is clicked', () => {
		render(
			<Stepper>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);
		expect( screen.queryByText( 'Step 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Step 2' ) ).not.toBeInTheDocument();
		user.click( screen.getByText( 'Next' ) );
		expect( screen.queryByText( 'Step 1' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Step 2' ) ).toBeInTheDocument();
	} );

	it( 'calls onComplete when there are no more steps forward', () => {
		const onComplete = jest.fn();
		render(
			<Stepper onComplete={ onComplete }>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);
		user.click( screen.getByText( 'Next' ) );
		user.click( screen.getByText( 'Next' ) );
		expect( onComplete ).toHaveBeenCalled();
	} );

	it( 'shows the prev step when the "Prev" button is clicked', () => {
		render(
			<Stepper>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);

		user.click( screen.getByText( 'Next' ) );
		expect( screen.queryByText( 'Step 1' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Step 2' ) ).toBeInTheDocument();

		user.click( screen.getByText( 'Prev' ) );
		expect( screen.queryByText( 'Step 1' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Step 2' ) ).not.toBeInTheDocument();
	} );

	it( 'calls onExit when there are no more steps back', () => {
		const onExit = jest.fn();

		render(
			<Stepper onExit={ onExit }>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);

		user.click( screen.getByText( 'Prev' ) );
		expect( onExit ).toHaveBeenCalled();
	} );

	it( 'calls onStepChange for both next and prev', () => {
		const onStepChange = jest.fn();

		render(
			<Stepper onStepChange={ onStepChange }>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);
		user.click( screen.getByText( 'Next' ) );
		user.click( screen.getByText( 'Prev' ) );
		expect( onStepChange ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'tracks progress', () => {
		render(
			<Stepper>
				<TestStep name="Step 1" />
				<TestStep name="Step 2" />
			</Stepper>
		);

		const progress = screen.getByRole( 'textbox' );
		expect( progress ).toHaveValue( '0.5' );
		user.click( screen.getByText( 'Next' ) );
		expect( progress ).toHaveValue( '1' );
	} );

	it( 'falls back to indexes if no name step is provided', () => {
		render(
			<Stepper>
				<TestStep />
				<TestStep />
			</Stepper>
		);
		expect( screen.queryByText( '0' ) ).toBeInTheDocument();
	} );
} );
