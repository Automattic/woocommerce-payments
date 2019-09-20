/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import CardSummary from '../';

describe( 'CardSummary', () => {
    test( 'renders a valid card brand and last 4 digits', () => {
        const cardSummary = renderCard( { brand: 'visa', last4: '4242' } );
        expect( cardSummary ).toMatchSnapshot();
    } );

    test( 'renders a dash if no card was provided', () => {
        const cardSummary = renderCard( null );
        expect( cardSummary ).toMatchSnapshot();
    } );

    function renderCard( card ) {
        return shallow( <CardSummary card={ card } /> );
    }
} );

