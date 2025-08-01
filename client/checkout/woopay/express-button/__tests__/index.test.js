/**
 * External dependencies
 */
import { screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import '../index';
import { getConfig } from 'utils/checkout';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( '../woopay-express-checkout-button', () => ( {
	__esModule: true,
	WoopayExpressCheckoutButton: () => {
		return <div>WooPay Express Button</div>;
	},
} ) );

describe( 'renderWooPayExpressButton', () => {
	// placeholder to attach react component.
	const expressButtonContainer = document.createElement( 'div' );
	expressButtonContainer.setAttribute( 'id', 'wcpay-woopay-button' );

	beforeEach( () => {
		getConfig.mockReturnValue( 'foo' );
	} );

	test( 'render the button component in placeholder', () => {
		document.body.appendChild( expressButtonContainer );

		// trigger window load.
		window.dispatchEvent( new Event( 'load' ) );

		expect(
			screen.queryByText( 'WooPay Express Button' )
		).toBeInTheDocument();
	} );

	test( 'should not render the express button component if placeholder is absent', () => {
		document.body.removeChild( expressButtonContainer );

		// trigger window load.
		window.dispatchEvent( new Event( 'load' ) );

		expect(
			screen.queryByText( 'WooPay Express Button' )
		).not.toBeInTheDocument();
	} );
} );
