/**
 * External dependencies
 */
import React from 'react';
import { Flex, FlexBlock } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingTextField, OnboardingPhoneNumberField } from '../form';
import InlineNotice from 'components/inline-notice';

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
			<OnboardingPhoneNumberField name="phone" />
			<InlineNotice
				status="info"
				className="personal-details-notice"
				icon
				isDismissible={ false }
			>
				{ strings.steps.personal.notice }
			</InlineNotice>
		</>
	);
};

export default PersonalDetails;
