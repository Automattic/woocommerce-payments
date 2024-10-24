/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Search from '../';

describe( 'Search', () => {
	test( 'renders a search input with placeholder', () => {
		const { container } = render(
			<Search placeholder="Placeholder text" />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
