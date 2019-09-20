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
			{ title: 'Item 1', description: 'Item 1 description' },
			{ title: 'Item 2', description: 'Item 2 description' },
			{ title: 'Item 3', description: 'Item 3 description' },
			{ title: 'Item 4', description: 'Item 4 description' },
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

