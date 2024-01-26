/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { Icon, store } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
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
			<div className={ 'wcpay-component-onboarding-card' }>
				<div className="wcpay-component-onboarding-card__label">
					<Icon icon={ store } />
					{ modeStrings.label }
				</div>
				<div className="wcpay-component-onboarding-card__body">
					<div className="onboarding-mode__note">
						{ modeStrings.note }
					</div>
					<p>{ modeStrings.tos }</p>
				</div>
				<div className="wcpay-component-onboarding-card__footer">
					<Button
						className="wcpay-component-onboarding-card__button"
						variant="primary"
						data-testid="live-mode-button"
						onClick={ () => {
							handleContinue( 'live' );
						} }
					>
						{ modeStrings.continue.live }
					</Button>
				</div>
			</div>

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
