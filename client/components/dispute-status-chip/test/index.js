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
		const dispute = { status: 'mock_status', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders needs_response status', () => {
		const dispute = { status: 'needs_response', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders under_review status', () => {
		const dispute = { status: 'under_review', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders charge_refunded status', () => {
		const dispute = { status: 'charge_refunded', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders won status', () => {
		const dispute = { status: 'won', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders lost status', () => {
		const dispute = { status: 'lost', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders warning_needs_response status', () => {
		const dispute = { status: 'warning_needs_response', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders warning_under_review status', () => {
		const dispute = { status: 'warning_under_review', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	test( 'renders warning_closed status', () => {
		const dispute = { status: 'warning_closed', id: 'dp_mock' };
		expect( renderDisputeStatus( dispute ) ).toMatchSnapshot();
	} );

	function renderDisputeStatus( dispute ) {
		return shallow( <DisputeStatusChip { ...dispute } /> );
	}
} );

