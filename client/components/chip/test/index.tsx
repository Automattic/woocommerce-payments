/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Chip, { chipType } from '../';

describe( 'Chip', () => {
	function renderChip( type: chipType | undefined, message: string ) {
		return render( <Chip type={ type } message={ message } /> );
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

	test( 'renders a primary chip by default', () => {
		const { container: chip } = renderChip( undefined, 'Message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a warning chip', () => {
		const { container: chip } = renderChip( 'warning', 'Alert message' );
		expect( chip ).toMatchSnapshot();
	} );
} );
