/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BannerBody from '..';

describe( 'BannerBody', () => {
	it( 'renders', () => {
		const { container: bannerBodyComponent } = render( <BannerBody /> );

		expect( bannerBodyComponent ).toMatchSnapshot();
	} );
} );
