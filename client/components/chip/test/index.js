/** @format */
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

	test( 'renders default if type is invalid', () => {
		const { container: chip } = renderChip( 'invalidtype', 'Message' );
		expect( chip ).toMatchSnapshot();
	} );

	function renderChip( type, message ) {
		return render( <Chip chipType={ type } message={ message } /> );
	}
} );
