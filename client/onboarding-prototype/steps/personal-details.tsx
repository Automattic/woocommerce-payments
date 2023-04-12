/**
 * External dependencies
 */
import React from 'react';
import { Flex, FlexBlock } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingTextField } from '../form';

const PersonalDetails: React.FC = () => {
	return (
		<>
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
					// TODO: Use BannerNotice component when it's available.
					strings.steps.personal.notice
				}
			</div>
			<OnboardingTextField name="phone" />
		</>
	);
};

export default PersonalDetails;
