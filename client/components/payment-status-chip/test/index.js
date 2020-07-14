/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentStatusChip from '../';

describe( 'PaymentStatusChip', () => {
	test( 'renders default appearance if status is unrecognized', () => {
		const { container } = render( <PaymentStatusChip status="mock_status" /> );
		expect( container ).toMatchSnapshot();
	} );

	const statuses = [
		'refunded_partial',
		'refunded_full',
		'paid',
		'authorized',
		'failed',
		'blocked',
		'disputed_needs_response',
		'disputed_under_review',
		'disputed_won',
		'disputed_lost',
		'disputed_warning_needs_response',
		'disputed_warning_under_review',
		'disputed_warning_closed',
	];

	test.each( statuses )( 'renders %s status', ( status ) => {
		const { container } = render( <PaymentStatusChip status={ status } /> );
		expect( container ).toMatchSnapshot();
	} );
} );
