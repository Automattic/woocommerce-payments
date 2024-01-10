/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { Icon, store, tool } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import OnboardingCard from 'wcpay/components/onboarding-card';
import { useStepperContext } from 'components/stepper';
import { trackModeSelected } from '../tracking';
import strings from '../strings';
import InlineNotice from 'components/inline-notice';

const SandboxModeNotice = () => (
	<InlineNotice icon status="warning" isDismissible={ false }>
		{ strings.steps.mode.sandboxModeNotice }
	</InlineNotice>
);

const ModeChoice: React.FC = () => {
	const { devMode } = wcpaySettings;
	const modeStrings = strings.steps.mode;

	const { nextStep } = useStepperContext();

	const handleContinue = ( mode: 'live' | 'test' ) => {
		trackModeSelected( mode );

		// If live mode is selected, go to the next step of the flow.
		if ( mode === 'live' ) return nextStep();

		// Else, redirect to the test mode Stripe flow.
		const { connectUrl } = wcpaySettings;
		const url = addQueryArgs( connectUrl, {
			test_mode: true,
		} );
		window.location.href = url;
	};

	return (
		<>
			{ devMode && <SandboxModeNotice /> }
			<OnboardingCard
				icon={ <Icon icon={ store } /> }
				heading={ modeStrings.label }
				content={
					<>
						<div className="onboarding-mode__note">
							{ modeStrings.note }
						</div>
						<p>{ modeStrings.tos }</p>
					</>
				}
				actionLabel={ modeStrings.continue.live }
				onClick={ () => {
					handleContinue( 'live' );
				} }
			/>

			<div className="onboarding-mode__sandbox">
				<Button
					variant="tertiary"
					onClick={ () => {
						handleContinue( 'test' );
					} }
				>
					{ modeStrings.continue.test }
				</Button>
			</div>
		</>
	);
};

export default ModeChoice;
