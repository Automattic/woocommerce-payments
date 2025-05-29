/** @format **/

/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';

/**
 * Internal dependencies.
 */
import { Button, HorizontalRule } from 'wcpay/components/wp-components-wrapped';
import { getAdminUrl } from 'wcpay/utils';
import {
	Stepper,
	StepperPanel,
	useStepperContext,
} from 'wcpay/components/stepper';
import {
	Accordion,
	AccordionBody,
	AccordionRow,
} from 'wcpay/components/accordion';
import Page from 'wcpay/components/page';

import '../style.scss';
import './style.scss';

interface StepProps {
	name: string;
}

const StepOne: React.FC< StepProps > = () => {
	const { nextStep } = useStepperContext();
	return (
		<div>
			<h2>General evidence</h2>
			<p>Provide general evidence for your dispute.</p>
		</div>
	);
};

const StepTwo: React.FC< StepProps > = () => {
	const { nextStep, prevStep } = useStepperContext();
	return (
		<div>
			<h2>Shipping information</h2>
			<p>Provide shipping details if applicable.</p>
		</div>
	);
};

const StepThree: React.FC< StepProps > = () => {
	const { prevStep } = useStepperContext();
	return (
		<div>
			<h2>Review</h2>
			<p>Review your information before submitting.</p>
		</div>
	);
};

const steps = [ 'General evidence', 'Shipping information', 'Review' ];

export default () => {
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ isAccordionOpen, setIsAccordionOpen ] = useState(
		currentStep === 0
	);

	useEffect( () => {
		setIsAccordionOpen( currentStep === 0 );
	}, [ currentStep ] );

	// TODO: Replace with real dispute ID from props or router
	const disputeId = '123';

	const handleCancel = () => {
		window.location.href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/disputes/details',
			id: disputeId,
		} );
	};

	const handleSaveForLater = () => {
		// TODO: Implement save for later logic
		alert( 'Save for later (not implemented)' );
	};

	const handleNext = () => setCurrentStep( ( s ) => s + 1 );
	const handleBack = () => setCurrentStep( ( s ) => s - 1 );
	const handleSubmit = () => {
		// TODO: Implement submit logic
		alert( 'Submit (not implemented)' );
	};

	const renderButtons = () => {
		if ( currentStep === 0 ) {
			return (
				<div
					style={ {
						display: 'flex',
						justifyContent: 'space-between',
						marginTop: 32,
					} }
				>
					<Button variant="secondary" onClick={ handleCancel }>
						Cancel
					</Button>
					<div>
						<Button
							variant="tertiary"
							onClick={ handleSaveForLater }
							style={ { marginRight: 8 } }
						>
							Save for later
						</Button>
						<Button variant="primary" onClick={ handleNext }>
							Next
						</Button>
					</div>
				</div>
			);
		}
		if ( currentStep === 1 ) {
			return (
				<div
					style={ {
						display: 'flex',
						justifyContent: 'space-between',
						marginTop: 32,
					} }
				>
					<Button variant="secondary" onClick={ handleBack }>
						Back
					</Button>
					<div>
						<Button
							variant="tertiary"
							onClick={ handleSaveForLater }
							style={ { marginRight: 8 } }
						>
							Save for later
						</Button>
						<Button variant="primary" onClick={ handleNext }>
							Next
						</Button>
					</div>
				</div>
			);
		}
		// Step 2 (index 2): Review
		return (
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					marginTop: 32,
				} }
			>
				<Button variant="secondary" onClick={ handleBack }>
					Back
				</Button>
				<div>
					<Button
						variant="tertiary"
						onClick={ handleSaveForLater }
						style={ { marginRight: 8 } }
					>
						Save for later
					</Button>
					<Button variant="primary" onClick={ handleSubmit }>
						Submit
					</Button>
				</div>
			</div>
		);
	};

	return (
		<Page maxWidth={ 1032 } className="wcpay-dispute-evidence">
			<div className="wcpay-dispute-evidence-new">
				{ /* Section 1: Accordion */ }
				<Accordion highDensity>
					<AccordionBody
						title="Challenge dispute"
						opened={ isAccordionOpen }
						onToggle={ setIsAccordionOpen }
					>
						<AccordionRow>
							<div className="evidence-summary__body">
								{ /* Placeholder: Replace with summary and notice content in next step */ }
								<p>Dispute summary and details go here.</p>
							</div>
						</AccordionRow>
					</AccordionBody>
				</Accordion>

				{ /* Section 2: Stepper */ }
				<div className="wcpay-dispute-evidence-new__stepper-section">
					<StepperPanel steps={ steps } currentStep={ currentStep } />
					<HorizontalRule className="wcpay-dispute-evidence-new__stepper-divider" />
					<div className="wcpay-dispute-evidence-new__stepper-content">
						<Stepper
							initialStep={ steps[ currentStep ] }
							onStepChange={ ( step ) =>
								setCurrentStep( steps.indexOf( step ) )
							}
						>
							<StepOne name="General evidence" />
							<StepTwo name="Shipping information" />
							<StepThree name="Review" />
						</Stepper>
						{ renderButtons() }
					</div>
				</div>
			</div>
		</Page>
	);
};
