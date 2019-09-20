/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import OrderLink from '../';

describe( 'OrderLink', () => {
    test( 'renders a link to a valid order', () => {
        const orderLink = renderOrder( { url: 'https://automattic.com/', number: '45891' } );
        expect( orderLink ).toMatchSnapshot();
    } );

    test( 'renders a dash if no order was provided', () => {
        const orderLink = renderOrder( null );
        expect( orderLink ).toMatchSnapshot();
    } );

    function renderOrder( order ) {
        return shallow( <OrderLink order={ order } /> );
    }
} );

