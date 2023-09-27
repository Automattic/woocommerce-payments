/**
 * External dependencies
 */
import React from 'react';
import { Icon, closeSmall } from '@wordpress/icons';
import ChevronLeft from 'gridicons/dist/chevron-left';

/**
 * Internal dependencies
 */
import { useStepperContext } from 'components/stepper';
import RestoredStateBanner from './restored-state-banner';
import { OnboardingSteps } from './types';
import { useTrackAbandoned } from './tracking';
import strings from './strings';
import Logo from 'assets/images/woopayments.svg';
import './style.scss';

interface Props {
	name: OnboardingSteps;
}

const Step: React.FC< Props > = ( { name, children } ) => {
	const { trackAbandoned } = useTrackAbandoned();
	const { progress, prevStep, exit } = useStepperContext();
	const width = `${ progress * 100 }%`;

	const handleExit = () => {
		trackAbandoned( 'exit' );
		exit();
	};

	return (
		<>
			<div className="stepper__progress" style={ { width } } />
			<div className="stepper__nav">
				<button
					type="button"
					className="stepper__nav-button"
					onClick={ prevStep }
				>
					<ChevronLeft />
					{ strings.back }
				</button>
				<img
					src={ Logo }
					alt="WooPayments"
					className="stepper__nav-logo"
				/>
				<button
					type="button"
					className="stepper__nav-button"
					onClick={ handleExit }
				>
					<Icon icon={ closeSmall } />
				</button>
			</div>
			<div className="stepper__wrapper">
				<RestoredStateBanner />
				<h1 className="stepper__heading">
					{ strings.steps[ name ].heading }
				</h1>
				<h2 className="stepper__subheading">
					{ strings.steps[ name ].subheading }
				</h2>
				<div className="stepper__content">{ children }</div>
			</div>
		</>
	);
};

export default Step;
