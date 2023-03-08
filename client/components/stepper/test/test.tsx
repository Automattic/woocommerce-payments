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
	const { currentStep, nextStep } = useStepperContext();

	return (
		<>
			<h1>{ name ?? currentStep }</h1>
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

	it( 'calls onComplete when there are no more steps', () => {
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
