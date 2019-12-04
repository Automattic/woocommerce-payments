/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import HorizontalList from '../';

describe( 'HorizontalList', () => {
	let horizontalList;
	beforeEach( () => {
		const items = [
			{ title: 'Item 1', content: 'Item 1 content' },
			{ title: 'Item 2', content: 'Item 2 content' },
			{ title: 'Item 3', content: 'Item 3 content' },
			{ title: 'Item 4', content: 'Item 4 content' },
		];
		horizontalList = renderHorizontalList( items );
	} );

	test( 'correctly renders a List element with horizontal class modifier', () => {
		expect( horizontalList ).toMatchSnapshot();
	} );

	function renderHorizontalList( items ) {
		return shallow( <HorizontalList items={ items } /> );
	}
} );

