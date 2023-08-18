/** @format **/

/**
 * External dependencies
 */
import React from 'react';
/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { Card, CardBody } from '@wordpress/components';
import './style.scss';

interface DisputeDetailsProps {
	dispute: Dispute;
}

const DisputeDetails: React.FC< DisputeDetailsProps > = ( { dispute } ) => {
	return (
		<div className="transaction-details-dispute-details-wrapper">
			<Card>
				<CardBody>
					<div></div>
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeDetails;
