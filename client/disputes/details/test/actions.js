/** @format */

/**
 * External dependencies
 */
import { mount } from 'enzyme';

/**
 * Internal dependencies
 */
import Actions from '../actions';

describe( 'Dispute details screen', () => {
	test( 'renders correctly for dispute needing review', () => {
		const doAccept = jest.fn();

		const actions = mount(
			<Actions id="dp_mock" onAccept={ doAccept } />
		);
		expect( actions ).toMatchSnapshot();

		const acceptButton = actions.find( 'button.is-default' );
		acceptButton.simulate( 'click' );
		expect( doAccept ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'renders correctly for dispute not needing review', () => {
		const actions = mount(
			<Actions id="dp_mock" />
		);
		expect( actions ).toMatchSnapshot();
	} );
} );
