/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import DisputeStatusChip from '../';

describe( 'DisputeStatusChip', () => {
	test( 'renders default appearance if status is unrecognized', () => {
		expect( renderDisputeStatus( 'mock_status' ) ).toMatchSnapshot();
	} );

	test( 'renders needs_response status', () => {
		expect( renderDisputeStatus( 'needs_response' ) ).toMatchSnapshot();
	} );

	test( 'renders under_review status', () => {
		expect( renderDisputeStatus( 'under_review' ) ).toMatchSnapshot();
	} );

	test( 'renders charge_refunded status', () => {
		expect( renderDisputeStatus( 'charge_refunded' ) ).toMatchSnapshot();
	} );

	test( 'renders won status', () => {
		expect( renderDisputeStatus( 'won' ) ).toMatchSnapshot();
	} );

	test( 'renders lost status', () => {
		expect( renderDisputeStatus( 'lost' ) ).toMatchSnapshot();
	} );

	test( 'renders warning_needs_response status', () => {
		expect( renderDisputeStatus( 'warning_needs_response' ) ).toMatchSnapshot();
	} );

	test( 'renders warning_under_review status', () => {
		expect( renderDisputeStatus( 'warning_under_review' ) ).toMatchSnapshot();
	} );

	test( 'renders warning_closed status', () => {
		expect( renderDisputeStatus( 'warning_closed' ) ).toMatchSnapshot();
	} );

	function renderDisputeStatus( status ) {
		return shallow( <DisputeStatusChip status={ status } /> );
	}
} );

