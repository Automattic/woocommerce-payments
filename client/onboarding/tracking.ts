/**
 * External dependencies
 */
import { useEffect } from 'react';

/**
 * Internal dependencies
 */

import { useStepperContext } from 'components/stepper';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';
import { recordEvent, events } from 'tracks';

const trackedSteps: Set< string > = new Set();
let startTime: number;
let stepStartTime: number;

const elapsed = ( time: number ) => Math.round( ( Date.now() - time ) / 1000 );
const stepElapsed = () => {
	const result = elapsed( stepStartTime );
	stepStartTime = Date.now();
	return result;
};

export const trackStarted = ( source: string | null ): void => {
	startTime = stepStartTime = Date.now();

	recordEvent( events.ONBOARDING_FLOW_STARTED, {
		source,
	} );
};

export const trackModeSelected = ( mode: string ): void => {
	recordEvent( events.ONBOARDING_FLOW_MODE_SELECTED, {
		mode,
		elapsed: stepElapsed(),
	} );
};

export const trackStepCompleted = ( step: string ): void => {
	// We only track a completed step once.
	if ( trackedSteps.has( step ) ) return;

	recordEvent( events.ONBOARDING_FLOW_STEP_COMPLETED, {
		step,
		elapsed: stepElapsed(),
	} );
	trackedSteps.add( step );
};

export const trackRedirected = ( isEligible: boolean ): void => {
	recordEvent( events.ONBOARDING_FLOW_REDIRECTED, {
		is_po_eligible: isEligible,
		elapsed: elapsed( startTime ),
	} );
};

export const trackAccountReset = (): void =>
	recordEvent( events.ONBOARDING_FLOW_RESET );

export const trackEligibilityModalClosed = (
	action: 'dismiss' | 'setup_deposits' | 'enable_payments_only'
): void =>
	recordEvent( events.ONBOARDING_FLOW_ELIGIBILITY_MODAL_CLOSED, {
		action,
	} );

export const useTrackAbandoned = (): {
	trackAbandoned: ( method: 'hide' | 'exit' ) => void;
	removeTrackListener: () => void;
} => {
	const { errors, touched } = useOnboardingContext();
	const { currentStep: step } = useStepperContext();

	const trackEvent = ( method = 'hide' ) => {
		const event =
			method === 'hide'
				? events.ONBOARDING_FLOW_HIDDEN
				: events.ONBOARDING_FLOW_EXITED;
		const errored = Object.keys( errors ).filter(
			( field ) => touched[ field as keyof OnboardingFields ]
		);

		recordEvent( event, {
			step,
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
		removeTrackListener: () =>
			document.removeEventListener( 'visibilitychange', listener ),
	};
};
