/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Chip from '../';
// eslint-disable-next-line no-duplicate-imports
import type { ChipType } from '../';

describe( 'Chip', () => {
	function renderChip(
		type: ChipType,
		message: string,
		tooltip?: React.ReactNode
	) {
		return render(
			<Chip type={ type } message={ message } tooltip={ tooltip } />
		);
	}

	test( 'renders an alert chip', () => {
		const { container: chip } = renderChip( 'alert', 'Alert message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip', () => {
		const { container: chip } = renderChip( 'primary', 'Primary message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a light chip', () => {
		const { container: chip } = renderChip( 'light', 'Light message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a chip with a tooltip', () => {
		const { container: chip } = renderChip(
			'light',
			'Light message',
			'Tooltip'
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a warning chip', () => {
		const { container: chip } = renderChip( 'warning', 'Alert message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders default if type is invalid', () => {
		const { container: chip } = renderChip( 'invalidtype', 'Message' );
		expect( chip ).toMatchSnapshot();
	} );
} );
