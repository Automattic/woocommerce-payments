/**
 * External dependencies
 */
import React from 'react';

/**
 * WordPress dependencies
 */
import { check } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { Icon } from 'wcpay/components/wp-components-wrapped';
import './style.scss';

interface StepperIndicatorProps {
	steps: string[];
	currentStep: number;
}

export const StepperIndicator: React.FC< StepperIndicatorProps > = ( {
	steps,
	currentStep,
} ) => (
	<div className="stepper-indicator">
		{ steps.map( ( label, idx ) => {
			const isComplete = idx < currentStep;
			const isActive = idx === currentStep;
			return (
				<div
					key={ label }
					className={ `stepper-step${ isActive ? ' active' : '' }${
						isComplete ? ' complete' : ''
					}` }
				>
					<div className="stepper-circle">
						{ isComplete ? (
							<Icon icon={ check } size={ 36 } />
						) : (
							idx + 1
						) }
					</div>
					<div className="stepper-label">{ label }</div>
					{ idx < steps.length - 1 && (
						<div className="stepper-line" />
					) }
				</div>
			);
		} ) }
	</div>
);
