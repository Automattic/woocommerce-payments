/**
 * External dependencies
 */
import { screen, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import '..';

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
		document.body.innerHTML = '';
	} );

	test( 'render the button component in placeholder', async () => {
		document.body.appendChild( expressButtonContainer );

		// trigger window load.
		await act( async () => {
			window.dispatchEvent( new Event( 'load' ) );

			// giving React some time to render
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );

		expect(
			await screen.findByText( 'WooPay Express Button' )
		).toBeInTheDocument();
	} );

	test( 'should not render the express button component if placeholder is absent', async () => {
		// trigger window load.
		await act( async () => {
			window.dispatchEvent( new Event( 'load' ) );

			// giving React some time to render
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );

		expect(
			screen.queryByText( 'WooPay Express Button' )
		).not.toBeInTheDocument();
	} );
} );
