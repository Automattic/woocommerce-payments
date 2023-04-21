/**
 * External dependencies
 */
import { useEffect } from 'react';
import { mapValues } from 'lodash';

/**
 * Internal dependencies
 */

import { useStepperContext } from 'components/stepper';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';
import wcpayTracks from 'tracks';

const trackedSteps: Set< string > = new Set();
let startTime: number;
let stepStartTime: number;

const elapsed = ( time: number ) => Math.round( ( Date.now() - time ) / 1000 );
const stepElapsed = () => {
	const result = elapsed( stepStartTime );
	stepStartTime = Date.now();
	return result;
};

export const trackStarted = (): void => {
	startTime = stepStartTime = Date.now();

	wcpayTracks.recordEvent( wcpayTracks.events.ONBOARDING_FLOW_STARTED, null );
};

export const trackModeSelected = ( mode: string ): void => {
	wcpayTracks.recordEvent( wcpayTracks.events.ONBOARDING_FLOW_MODE_SELECTED, {
		mode,
		elapsed: stepElapsed(),
	} );
};

export const trackStepCompleted = ( step: string ): void => {
	// We only track a completed step once.
	if ( trackedSteps.has( step ) ) return;

	wcpayTracks.recordEvent(
		wcpayTracks.events.ONBOARDING_FLOW_STEP_COMPLETED,
		{
			step,
			elapsed: stepElapsed(),
		}
	);
	trackedSteps.add( step );
};

export const trackRedirected = ( isEligible: boolean ): void => {
	wcpayTracks.recordEvent( wcpayTracks.events.ONBOARDING_FLOW_REDIRECTED, {
		is_po_eligible: isEligible,
		elapsed: elapsed( startTime ),
	} );
};

export const useTrackAbandoned = (): {
	trackAbandoned: ( method: string ) => void;
} => {
	const { errors, touched } = useOnboardingContext();
	const { currentStep: step } = useStepperContext();

	const trackEvent = ( method = 'hidden' ) => {
		const errored = Object.keys( errors ).filter(
			( field ) => touched[ field as keyof OnboardingFields ]
		);

		wcpayTracks.recordEvent( wcpayTracks.events.ONBOARDING_FLOW_ABANDONED, {
			step,
			method,
			errored,
			elapsed: elapsed( startTime ),
		} );
	};

	const listener = () => {
		if ( document.visibilityState === 'hidden' ) {
			trackEvent();
		}
	};

	useEffect( () => {
		document.addEventListener( 'visibilitychange', listener );
		return () => {
			document.removeEventListener( 'visibilitychange', listener );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ step, errors, touched ] );

	return {
		trackAbandoned: ( method: string ) => {
			trackEvent( method );
			document.removeEventListener( 'visibilitychange', listener );
		},
	};
};
