/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import { HorizontalListItem, HorizontalList } from '..';

describe( 'HorizontalList', () => {
	function renderHorizontalList( items: HorizontalListItem[] ) {
		return render( <HorizontalList items={ items } /> );
	}
	let horizontalList: HTMLElement;
	beforeEach( () => {
		const items = [
			{ title: 'Item 1', content: 'Item 1 content' },
			{ title: 'Item 2', content: 'Item 2 content' },
			{ title: 'Item 3', content: 'Item 3 content' },
			{ title: 'Item 4', content: 'Item 4 content' },
		];
		horizontalList = renderHorizontalList( items ).container;
	} );

	test( 'correctly renders a List element with horizontal class modifier', () => {
		expect( horizontalList ).toMatchSnapshot();
		expect( console ).toHaveWarnedWith(
			// eslint-disable-next-line max-len
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);
	} );
} );
