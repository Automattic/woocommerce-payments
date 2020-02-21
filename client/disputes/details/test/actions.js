/** @format */

/**
 * External dependencies
 */
import { mount } from 'enzyme';

/**
 * Internal dependencies
 */
import Actions from '../actions';

describe( 'Dispute details actions', () => {
	test( 'renders correctly for dispute needing response, confirmation requested on submit', () => {
		window.confirm = jest.fn();
		const doAccept = jest.fn();

		const actions = mount(
			<Actions
				id="dp_mock"
				needsResponse={ true }
				onAccept={ doAccept }
			/>
		);
		expect( actions ).toMatchSnapshot();

		const acceptButton = actions.find( 'button.is-default' );
		acceptButton.simulate( 'click' );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( doAccept ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'onAccept called after confirmation only', () => {
		const doAccept = jest.fn();

		const actions = mount(
			<Actions
				id="dp_mock"
				needsResponse={ true }
				onAccept={ doAccept }
			/>
		);
		const acceptButton = actions.find( 'button.is-default' );

		window.confirm = jest.fn()
			.mockReturnValueOnce( false )
			.mockReturnValueOnce( true );

		acceptButton.simulate( 'click' );
		expect( doAccept ).toHaveBeenCalledTimes( 0 );
		acceptButton.simulate( 'click' );
		expect( doAccept ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'renders correctly for closed dispute', () => {
		const actions = mount(
			<Actions id="dp_mock" needsResponse={ false } isSubmitted={ false } />
		);
		expect( actions ).toMatchSnapshot();
	} );

	test( 'renders correctly for dispute with evidence submitted', () => {
		const actions = mount(
			<Actions id="dp_mock" needsResponse={ false } isSubmitted={ true } />
		);
		expect( actions ).toMatchSnapshot();
	} );
} );
