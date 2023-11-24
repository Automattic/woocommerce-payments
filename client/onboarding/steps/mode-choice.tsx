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
import RadioCard from 'components/radio-card';
import { useStepperContext } from 'components/stepper';
import { trackModeSelected } from '../tracking';
import strings from '../strings';
import InlineNotice from 'components/inline-notice';

const DevModeNotice = () => (
	<InlineNotice icon status="warning" isDismissible={ false }>
		{ strings.steps.mode.devModeNotice }
	</InlineNotice>
);

const ModeChoice: React.FC = () => {
	const { devMode } = wcpaySettings;
	const liveStrings = strings.steps.mode.live;
	const testStrings = strings.steps.mode.test;

	const [ selected, setSelected ] = useState< 'live' | 'test' >( 'live' );
	const { nextStep } = useStepperContext();

	const handleContinue = () => {
		trackModeSelected( selected );

		if ( selected === 'live' ) return nextStep();

		const { connectUrl } = wcpaySettings;
		const url = addQueryArgs( connectUrl, {
			test_mode: true,
		} );
		window.location.href = url;
	};

	return (
		<>
			{ devMode && <DevModeNotice /> }
			<RadioCard
				name="onboarding-mode"
				selected={ selected }
				onChange={ setSelected as ( value: string ) => void }
				options={ [
					{
						label: liveStrings.label,
						value: 'live',
						icon: <Icon icon={ store } />,
						content: (
							<div className="onboarding-mode__note">
								{ liveStrings.note }
							</div>
						),
					},
					{
						label: testStrings.label,
						value: 'test',
						icon: <Icon icon={ tool } />,
						content: (
							<div className="onboarding-mode__note">
								{ testStrings.note }
							</div>
						),
					},
				] }
			/>
			<Button
				isPrimary
				onClick={ handleContinue }
				className="stepper__cta"
			>
				{ strings.continue }
			</Button>
		</>
	);
};

export default ModeChoice;
