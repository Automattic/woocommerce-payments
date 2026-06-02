/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@wordpress/element';

import { loadRequirementErrorMessages } from './update-business-details-task-description';

export const renderUpdateBusinessDetailsModal = async ( {
	requirementErrors,
	status,
	accountLink,
	currentDeadline,
} ) => {
	const [ { default: UpdateBusinessDetailsModal }, errorMessages ] =
		await Promise.all( [
			import( 'wcpay/overview/modal/update-business-details' ),
			loadRequirementErrorMessages( requirementErrors ),
		] );
	let container = document.querySelector(
		'#wcpay-update-business-details-container'
	);

	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = 'wcpay-update-business-details-container';
		document.body.appendChild( container );
	}

	render(
		React.createElement( UpdateBusinessDetailsModal, {
			key: Date.now(),
			errorMessages,
			accountStatus: status,
			accountLink,
			currentDeadline,
		} ),
		container
	);
};
