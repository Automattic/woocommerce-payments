/**
 * External dependencies
 */
import { screen, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import '..';
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

	test( 'render the button component in placeholder', async () => {
		document.body.appendChild( expressButtonContainer );

		// trigger window load.
		act( () => {
			window.dispatchEvent( new Event( 'load' ) );
		} );

		expect(
			await screen.findByText( 'WooPay Express Button' )
		).toBeInTheDocument();
	} );

	test( 'should not render the express button component if placeholder is absent', async () => {
		document.body.removeChild( expressButtonContainer );

		// trigger window load.
		act( () => {
			window.dispatchEvent( new Event( 'load' ) );
		} );

		expect(
			await screen.findByText( 'WooPay Express Button' )
		).not.toBeInTheDocument();
	} );
} );
