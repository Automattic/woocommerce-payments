/** @format */

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { LoadableBlock } from 'components/loadable';
import { useRequiredVerificationInfo } from 'data/onboarding';
import Requirements from 'onboarding/requirements';

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
					<b>
						{ __(
							"To verify your details, we'll require:",
							'woocommerce-payments'
						) }
					</b>
				</p>
				<LoadableBlock isLoading={ isLoading }>
					<Requirements type={ type } keys={ requiredFields } />
				</LoadableBlock>
			</CardBody>
		</Card>
	);
};

export default RequiredVerificationInfo;
