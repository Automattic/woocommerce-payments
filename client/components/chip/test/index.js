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
    test( 'renders a warning chip', () => {
        const chip = renderChip( 'warning', 'Warning message' );
        expect( chip ).toMatchSnapshot();
    } );

    test( 'renders a primary chip', () => {
        const chip = renderChip( 'primary', 'Primary message' );
        expect( chip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip by default', () => {
        const chip = renderChip( undefined, 'Message' );
        expect( chip ).toMatchSnapshot();
    } );

    function renderChip( type, message ) {
        return shallow( <Chip type={ type } message={ message } /> );
    }
} );

