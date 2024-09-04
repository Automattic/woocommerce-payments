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
import { OnboardingSteps } from './types';
import { useTrackAbandoned } from './tracking';
import strings from './strings';
import WooLogo from 'assets/images/woo-logo.svg';
import './style.scss';

interface Props {
	name: OnboardingSteps;
	showHeading?: boolean;
}

const Step: React.FC< Props > = ( { name, children, showHeading = true } ) => {
	const { trackAbandoned } = useTrackAbandoned();
	const { prevStep, exit } = useStepperContext();
	const handleExit = () => {
		const urlParams = new URLSearchParams( window.location.search );
		const source =
			urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

		trackAbandoned( 'exit', source );
		exit();
	};

	return (
		<>
			<div className="stepper__nav">
				<button
					type="button"
					className={ `stepper__nav-button ${
						name === 'embedded' ? 'hide' : ''
					}` }
					onClick={ prevStep }
				>
					<ChevronLeft />
					{ strings.back }
				</button>
				<img src={ WooLogo } alt="Woo" className="stepper__nav-logo" />
				<button
					type="button"
					className="stepper__nav-button"
					onClick={ handleExit }
				>
					{ strings.cancel }
				</button>
			</div>
			<div className="stepper__wrapper">
				{ showHeading && (
					<>
						<h1 className="stepper__heading">
							{ strings.steps[ name ].heading }
						</h1>
						<h2 className="stepper__subheading">
							{ strings.steps[ name ].subheading }
						</h2>
					</>
				) }
				<div className="stepper__content">{ children }</div>
			</div>
		</>
	);
};

export default Step;
