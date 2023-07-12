/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeStatusChip from '../';

describe( 'DisputeStatusChip', () => {
	function renderDisputeStatus( status: string, dueBy = undefined ) {
		const { container } = render(
			<DisputeStatusChip status={ status } dueBy={ dueBy } />
		);
		return container;
	}

	test( 'renders default appearance if status is unrecognized', () => {
		expect( renderDisputeStatus( 'mock_status' ) ).toMatchSnapshot();
	} );

	const statuses = [
		'needs_response',
		'under_review',
		'charge_refunded',
		'won',
		'lost',
		'warning_needs_response',
		'warning_under_review',
		'warning_closed',
	];

	test.each( statuses )( 'renders %s status', ( status ) => {
		expect( renderDisputeStatus( status ) ).toMatchSnapshot();
	} );
} );
