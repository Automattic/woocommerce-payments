/**
 * External dependencies
 */
import React from 'react';
import { Flex, FlexBlock } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingForm, OnboardingTextField } from '../form';

const PersonalDetails: React.FC = () => {
	return (
		<OnboardingForm>
			<Flex align="top">
				<FlexBlock>
					<OnboardingTextField name="individual.first_name" />
				</FlexBlock>
				<FlexBlock>
					<OnboardingTextField name="individual.last_name" />
				</FlexBlock>
			</Flex>
			<OnboardingTextField name="email" />
			<div>
				{
					// TODO  [GH-4744]: Create a notice component
					strings.steps.personal.notice
				}
			</div>
			<OnboardingTextField name="phone" />
		</OnboardingForm>
	);
};

export default PersonalDetails;
