/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { swapDarkIcons } from '../swap-dark-icons';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getUPEConfig: jest.fn(),
} ) );

const mockGetIconTheme = jest.fn( () => 'night' );

jest.mock( 'wcpay/checkout/utils/icon-theme', () => ( {
	getIconTheme: ( ...args ) => mockGetIconTheme( ...args ),
} ) );

/**
 * Creates a payment method DOM structure matching classic checkout layout.
 *
 * @param {string} paymentMethodType The payment method type.
 * @param {Array}  imgSrcs           Image source URLs.
 * @return {Object} The container element and image elements.
 */
function setupPaymentMethodDOM(
	paymentMethodType,
	imgSrcs = [ '/original.svg' ]
) {
	const container = document.createElement( 'div' );
	container.classList.add( 'wc_payment_method' );

	const label = document.createElement( 'label' );
	const imgs = imgSrcs.map( ( src ) => {
		const img = document.createElement( 'img' );
		img.src = src;
		label.appendChild( img );
		return img;
	} );

	const upeElement = document.createElement( 'div' );
	upeElement.classList.add( 'wcpay-upe-element' );
	upeElement.dataset.paymentMethodType = paymentMethodType;

	container.appendChild( label );
	container.appendChild( upeElement );
	document.body.appendChild( container );

	return { container, imgs };
}

describe( 'swapDarkIcons in classic checkout', () => {
	let containers = [];

	beforeEach( () => {
		containers = [];
		mockGetIconTheme.mockReturnValue( 'night' );
		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'paymentMethodsConfig' ) {
				return {
					giropay: {
						icon: '/giropay.svg',
						darkIcon: '/dark-giropay.svg',
					},
					card: {
						icon: '/card.svg',
						darkIcon: '/dark-card.svg',
					},
				};
			}
			return null;
		} );
	} );

	afterEach( () => {
		containers.forEach( ( c ) => c.remove() );
		jest.clearAllMocks();
	} );

	test( 'swaps non-card icons when background is dark', () => {
		const { container, imgs } = setupPaymentMethodDOM( 'giropay' );
		containers.push( container );

		swapDarkIcons();

		expect( imgs[ 0 ].src ).toContain( '/dark-giropay.svg' );
		expect( mockGetIconTheme ).toHaveBeenCalledWith( 'classic' );
	} );

	test( 'does not swap card brand icons', () => {
		const { container, imgs } = setupPaymentMethodDOM( 'card', [
			'/visa.svg',
			'/mc.svg',
		] );
		containers.push( container );

		swapDarkIcons();

		expect( imgs[ 0 ].src ).toContain( '/visa.svg' );
		expect( imgs[ 1 ].src ).toContain( '/mc.svg' );
	} );

	test( 'reverts to light icons when background is light', () => {
		// First swap to dark icons.
		const { container, imgs } = setupPaymentMethodDOM( 'giropay' );
		containers.push( container );

		swapDarkIcons();
		expect( imgs[ 0 ].src ).toContain( '/dark-giropay.svg' );

		// Now switch to light background and swap again.
		mockGetIconTheme.mockReturnValue( 'stripe' );
		swapDarkIcons();
		expect( imgs[ 0 ].src ).toContain( '/giropay.svg' );
	} );
} );
