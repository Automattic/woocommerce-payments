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
import type { ChipType } from '../';

describe( 'Chip', () => {
	function renderChip(
		message: string,
		type?: ChipType,
		tooltip?: React.ReactNode
	) {
		return render(
			<Chip type={ type } message={ message } tooltip={ tooltip } />
		);
	}

	test( 'renders an alert chip', () => {
		const { container: chip } = renderChip( 'Alert message', 'alert' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip', () => {
		const { container: chip } = renderChip( 'Primary message', 'primary' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a light chip', () => {
		const { container: chip } = renderChip( 'Light message', 'light' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a chip with a tooltip', () => {
		const { container: chip } = renderChip(
			'Light message',
			'light',
			'Tooltip'
		);
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a warning chip', () => {
		const { container: chip } = renderChip( 'Alert message', 'warning' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip by default', () => {
		const { container: chip } = renderChip( 'Message', undefined );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders default if type is invalid', () => {
		const { container: chip } = renderChip(
			'Message',
			'invalidtype' as ChipType
		);
		expect( chip ).toMatchSnapshot();
	} );
} );
