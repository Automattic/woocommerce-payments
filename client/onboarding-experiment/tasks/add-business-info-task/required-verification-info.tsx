/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { LoadableBlock } from 'components/loadable';
import { useRequiredVerificationInfo } from 'onboarding-experiment/hooks';
import Requirements from 'onboarding-experiment/requirements';
import { OnboardingProps } from 'onboarding-experiment/types';
import strings from 'onboarding-experiment/strings';

const RequiredVerificationInfo = ( {
	country,
	type,
	structure,
}: OnboardingProps ): JSX.Element => {
	const { requiredInfo, isLoading } = useRequiredVerificationInfo( {
		country,
		type,
		structure,
	} );

	return (
		<Card size="large" className="wcpay-required-info-card">
			<CardBody>
				<p>
					<b>{ strings.onboarding.requirementsDescription }</b>
				</p>
				<LoadableBlock isLoading={ isLoading } numLines={ 2 }>
					<Requirements type={ type } keys={ requiredInfo } />
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 2 } />
				<LoadableBlock isLoading={ isLoading } numLines={ 2 } />
			</CardBody>
		</Card>
	);
};

export default RequiredVerificationInfo;
