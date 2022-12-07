/**
 * External dependencies
 */
import { screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import '../index';

jest.mock( '../woopay-express-checkout-button', () => ( {
	__esModule: true,
	WoopayExpressCheckoutButton: () => {
		return <div>WooPay Express Button</div>;
	},
} ) );

describe( 'renderPlatformCheckoutExpressButton', () => {
	test( 'render the button component in placeholder', () => {
		// placeholder to attach react component.
		const expressButtonContainer = document.createElement( 'div' );
		expressButtonContainer.setAttribute(
			'id',
			'wcpay-platform-checkout-button'
		);
		document.body.appendChild( expressButtonContainer );

		// trigger window load.
		window.dispatchEvent( new Event( 'load' ) );

		expect(
			screen.queryByText( 'WooPay Express Button' )
		).toBeInTheDocument();
	} );

	test( 'should not render the express button component if placeholder is absent', () => {
		// trigger window load.
		window.dispatchEvent( new Event( 'load' ) );

		expect(
			screen.queryByText( 'WooPay Express Button' )
		).not.toBeInTheDocument();
	} );
} );
