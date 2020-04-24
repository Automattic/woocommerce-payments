/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeStatusChip from '../';

describe( 'DisputeStatusChip', () => {
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

	function renderDisputeStatus( status ) {
		const { container } = render( <DisputeStatusChip status={ status } /> );
		return container;
	}
} );

