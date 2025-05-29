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
import { StepperPanel } from 'wcpay/components/stepper';
import {
	Accordion,
	AccordionBody,
	AccordionRow,
} from 'wcpay/components/accordion';
import Page from 'wcpay/components/page';

import './style.scss';

interface StepProps {
	heading: string;
	subheading: string;
}

const Step: React.FC< StepProps > = ( { heading, subheading } ) => {
	return (
		<div>
			<h2 className="wcpay-dispute-evidence-new__stepper-title">
				{ heading }
			</h2>
			<p className="wcpay-dispute-evidence-new__stepper-subheading">
				{ subheading }
			</p>
		</div>
	);
};

const panelHeadings = [ 'General evidence', 'Shipping information', 'Review' ];

const steps = [
	{
		heading: 'Let’s gather the basics',
		subheading:
			'To make a stronger case, please provide as much info as possible. We prefilled some fields for you, please double check and upload all the necessary documents.',
	},
	{
		heading: 'Shipping details',
		subheading: 'Please make sure all the shipping information is correct.',
	},
	{
		heading: 'Review the cover letter',
		subheading:
			'Please review the cover letter that will be submitted to the bank based on the information you provided. You can make changes to it or add additional details.',
	},
];

const stepComponents = [
	<Step
		heading={ steps[ 0 ].heading }
		subheading={ steps[ 0 ].subheading }
		key="step-1"
	/>,
	<Step
		heading={ steps[ 1 ].heading }
		subheading={ steps[ 1 ].subheading }
		key="step-2"
	/>,
	<Step
		heading={ steps[ 2 ].heading }
		subheading={ steps[ 2 ].subheading }
		key="step-3"
	/>,
];

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
				<div className="wcpay-dispute-evidence-new__button-row">
					<Button variant="secondary" onClick={ handleCancel }>
						Cancel
					</Button>
					<div className="wcpay-dispute-evidence-new__button-group-right">
						<Button
							variant="tertiary"
							onClick={ handleSaveForLater }
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
				<div className="wcpay-dispute-evidence-new__button-row">
					<Button variant="secondary" onClick={ handleBack }>
						Back
					</Button>
					<div className="wcpay-dispute-evidence-new__button-group-right">
						<Button
							variant="tertiary"
							onClick={ handleSaveForLater }
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
			<div className="wcpay-dispute-evidence-new__button-row">
				<Button variant="secondary" onClick={ handleBack }>
					Back
				</Button>
				<div className="wcpay-dispute-evidence-new__button-group-right">
					<Button variant="tertiary" onClick={ handleSaveForLater }>
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
					<StepperPanel
						steps={ panelHeadings }
						currentStep={ currentStep }
					/>
					<HorizontalRule className="wcpay-dispute-evidence-new__stepper-divider" />
					<div className="wcpay-dispute-evidence-new__stepper-content">
						{ stepComponents[ currentStep ] }
						{ renderButtons() }
					</div>
				</div>
			</div>
		</Page>
	);
};
