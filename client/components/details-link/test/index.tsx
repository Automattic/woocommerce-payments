/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import DetailsLink from '..';

describe( 'Details link', () => {
	test( 'renders transaction details with charge ID', () => {
		const { container: link } = render(
			<DetailsLink id="ch_mock" parentSegment="transactions" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders dispute details with ID', () => {
		const { container: link } = render(
			<DetailsLink id="dp_mock" parentSegment="disputes" />
		);
		expect( link ).toMatchSnapshot();
	} );
} );
