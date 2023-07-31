/**
 * External dependencies
 */
import React from 'react';
import { Flex, FlexBlock } from '@wordpress/components';
import { info } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingTextField, OnboardingPhoneNumberField } from '../form';
import BannerNotice from 'components/banner-notice';

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
			<BannerNotice status="info" icon={ info } isDismissible={ false }>
				{ strings.steps.personal.notice }
			</BannerNotice>
		</>
	);
};

export default PersonalDetails;
