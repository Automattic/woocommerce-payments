/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Chip from '../';

describe( 'Chip', () => {
	test( 'renders an alert chip', () => {
		const chip = renderChip( 'alert', 'Alert message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip', () => {
		const chip = renderChip( 'primary', 'Primary message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a light chip', () => {
		const chip = renderChip( 'light', 'Light message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip by default', () => {
		const chip = renderChip( undefined, 'Message' );
		expect( chip ).toMatchSnapshot();
	} );

	test( 'renders default if type is invalid', () => {
		const chip = renderChip( 'invalidtype', 'Message' );
		expect( chip ).toMatchSnapshot();
	} );

	function renderChip( type, message ) {
		return shallow( <Chip type={ type } message={ message } /> );
	}
} );

