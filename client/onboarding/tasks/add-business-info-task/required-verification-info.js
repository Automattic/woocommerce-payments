/** @format */

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { LoadableBlock } from 'components/loadable';
import { useRequiredVerificationInfo } from 'data/onboarding';
import Requirements from 'onboarding/requirements';
import strings from 'onboarding/strings';

const RequiredVerificationInfo = ( { country, type, structure } ) => {
	const {
		requiredFields,
		isLoading,
		getRequiredVerificationInfo,
	} = useRequiredVerificationInfo( {
		country,
		type,
		structure,
	} );

	useEffect( () => {
		getRequiredVerificationInfo( { country, type, structure } );
	}, [ country, type, structure, getRequiredVerificationInfo ] );

	return (
		<Card size="large" className="wcpay-required-info-card">
			<CardBody>
				<p>
					<b>{ strings.onboarding.requirementsDescription }</b>
				</p>
				<LoadableBlock isLoading={ isLoading } numLines={ 2 }>
					<Requirements type={ type } keys={ requiredFields } />
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 2 } />
				<LoadableBlock isLoading={ isLoading } numLines={ 2 } />
			</CardBody>
		</Card>
	);
};

export default RequiredVerificationInfo;
