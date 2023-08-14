/** @format **/

/**
 * External dependencies
 */
import React from 'react';
/**
 * Internal dependencies
 */
import { Dispute } from '../../types/disputes';
import { Card, CardBody } from '@wordpress/components';
import './style.scss';

interface DisputeDetailsProps {
	dispute: Dispute;
}

const DisputeDetails: React.FC< DisputeDetailsProps > = ( { dispute } ) => {
	return (
		<div>
			<Card className="transaction-details-dispute-details-wrapper">
				<CardBody>
					<div></div>
				</CardBody>
			</Card>
			<br />
		</div>
	);
};

export default DisputeDetails;
