/** @format */

/**
 * External dependencies
 */
import React from 'react';

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Chip from '../';

describe( 'Chip', () => {
	test( 'renders an alert chip', () => {
		const { container: chip } = render(
			<Chip type="alert" message="Alert message" />
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip', () => {
		const { container: chip } = render(
			<Chip type="primary" message="Primary message" />
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a light chip', () => {
		const { container: chip } = render(
			<Chip type="light" message="Light message" />
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a chip with a tooltip', () => {
		const { container: chip } = render(
			<Chip type="light" message="Light message" tooltip="Tooltip" />
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip by default', () => {
		const { container: chip } = render(
			<Chip type={ undefined } message="Message" />
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a warning chip', () => {
		const { container: chip } = render(
			<Chip type="warning" message="Alert message" />
		);
		expect( chip ).toMatchSnapshot();
	} );
} );
