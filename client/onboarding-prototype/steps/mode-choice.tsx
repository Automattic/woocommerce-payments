/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { Icon, store, tool, payment, globe, shortcode } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import ScheduledIcon from 'gridicons/dist/scheduled';

/**
 * Internal dependencies
 */
import RadioCard from 'components/radio-card';
import TipBox from 'components/tip-box';
import { useStepperContext } from 'components/stepper';
import HowToReg from 'components/icons/how-to-reg';
import strings from '../strings';

const ModeChoice: React.FC = () => {
	const liveStrings = strings.steps.mode.live;
	const testStrings = strings.steps.mode.test;

	const [ selected, setSelected ] = useState< 'live' | 'test' >( 'live' );
	const { nextStep } = useStepperContext();

	const handleContinue = () => {
		if ( selected === 'live' ) return nextStep();

		const { connectUrl } = wcpaySettings;
		const url = addQueryArgs( connectUrl, {
			test_mode: true,
		} );
		window.location.href = url;
	};

	return (
		<>
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
							<>
								<div className="onboarding-mode__sentences live">
									<Icon icon={ payment } />
									{ liveStrings.paymentMethods }
									<Icon icon={ globe } />
									{ liveStrings.internationalMarkets }
									<ScheduledIcon />
									{ liveStrings.managePayments }
								</div>
								<TipBox color="purple">
									{ liveStrings.tip }
								</TipBox>
								{ liveStrings.tos }
							</>
						),
					},
					{
						label: testStrings.label,
						value: 'test',
						icon: <Icon icon={ tool } />,
						content: (
							<>
								<div className="onboarding-mode__sentences test">
									<Icon icon={ shortcode } />
									{ testStrings.setup }
									<HowToReg />
									<span>{ testStrings.testData }</span>
									<Icon icon={ payment } />
									{ testStrings.payments }
								</div>
								<TipBox color="blue">
									{ testStrings.tip }
								</TipBox>
							</>
						),
					},
				] }
			/>
			<Button isPrimary onClick={ handleContinue }>
				{ strings.continue }
			</Button>
		</>
	);
};

export default ModeChoice;
