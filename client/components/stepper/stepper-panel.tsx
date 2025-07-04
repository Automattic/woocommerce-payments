/**
 * External dependencies
 */
import React from 'react';
import { check } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { Icon } from 'wcpay/components/wp-components-wrapped';
import './style.scss';
import clsx from 'clsx';

interface StepperIndicatorProps {
	steps: string[];
	currentStep: number;
	onStepClick?: ( stepIndex: number ) => void;
}

export const StepperPanel: React.FC< StepperIndicatorProps > = ( {
	steps,
	currentStep,
	onStepClick,
} ) => (
	<div className="stepper-panel">
		{ steps.map( ( label, idx ) => {
			const isComplete = idx < currentStep;
			const isActive = idx === currentStep;
			const isClickable = typeof onStepClick === 'function';

			const StepLabel = isClickable ? 'button' : 'div';

			const handleClick = () => {
				if ( isClickable && onStepClick ) {
					onStepClick( idx );
				}
			};

			return (
				<div
					key={ label }
					className={ clsx( 'stepper-step', {
						active: isActive,
						complete: isComplete,
						clickable: isClickable,
					} ) }
				>
					<StepLabel
						className="stepper-circle"
						onClick={ handleClick }
						disabled={ ! isClickable }
					>
						{ isComplete ? (
							<Icon icon={ check } size={ 36 } />
						) : (
							idx + 1
						) }
					</StepLabel>
					<StepLabel
						className="stepper-label"
						onClick={ handleClick }
						disabled={ ! isClickable }
					>
						{ label }
					</StepLabel>
					{ idx < steps.length - 1 && (
						<div className="stepper-line" />
					) }
				</div>
			);
		} ) }
	</div>
);
