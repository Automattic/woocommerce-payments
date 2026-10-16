/**
 * Internal dependencies
 */
// The entry module wires renderSaveUserSection to `window.load` and jQuery's
// `ajaxComplete`. Tests drive those same callbacks rather than the (unexported)
// function, so they exercise the real public surface.

const mockCreateRoot = jest.fn();

jest.mock( 'react-dom/client', () => ( {
	createRoot: ( ...args ) => mockCreateRoot( ...args ),
} ) );

jest.mock(
	'wcpay/components/woopay/save-user/checkout-page-save-user',
	() => () => null
);

// Load the entry module in isolation (fresh module-level root cache each time)
// and hand back the callbacks it registers, so a test can trigger the initial
// render (`load`) and the re-renders that follow checkout updates
// (`ajaxComplete`).
const loadCheckoutEntry = () => {
	let ajaxCompleteHandler;

	global.jQuery = jest.fn( ( arg ) => {
		if ( typeof arg === 'function' ) {
			arg( global.jQuery );
		}

		return {
			ajaxComplete: ( handler ) => {
				ajaxCompleteHandler = handler;
			},
		};
	} );

	const addEventListenerSpy = jest.spyOn( window, 'addEventListener' );

	jest.isolateModules( () => {
		require( 'wcpay/checkout/woopay/index' );
	} );

	const loadCall = addEventListenerSpy.mock.calls.find(
		( [ event ] ) => event === 'load'
	);
	addEventListenerSpy.mockRestore();

	return {
		triggerLoad: loadCall[ 1 ],
		triggerAjaxComplete: () => ajaxCompleteHandler(),
	};
};

describe( 'renderSaveUserSection - Blocks checkout', () => {
	beforeEach( () => {
		mockCreateRoot.mockReset();
		mockCreateRoot.mockImplementation( () => ( {
			render: jest.fn(),
			unmount: jest.fn(),
		} ) );
		document.body.innerHTML = '';
	} );

	it( 'creates the React root once and reuses it across re-renders', () => {
		document.body.innerHTML = `
			<div class="wc-block-checkout">
				<div class="wp-block-woocommerce-checkout-payment-block"></div>
			</div>
		`;
		const { triggerLoad, triggerAjaxComplete } = loadCheckoutEntry();

		triggerLoad();
		triggerAjaxComplete();

		expect( mockCreateRoot ).toHaveBeenCalledTimes( 1 );

		const root = mockCreateRoot.mock.results[ 0 ].value;

		expect( root.render ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'unmounts the previous root and mounts a fresh one when the container is recreated', () => {
		document.body.innerHTML = `
			<div class="wc-block-checkout">
				<div class="wp-block-woocommerce-checkout-payment-block"></div>
			</div>
		`;
		const { triggerLoad, triggerAjaxComplete } = loadCheckoutEntry();

		triggerLoad();
		const firstRoot = mockCreateRoot.mock.results[ 0 ].value;

		// Simulate WC core replacing the checkout subtree, taking our
		// container with it.
		document.querySelector( '#remember-me' ).remove();

		triggerAjaxComplete();

		expect( firstRoot.unmount ).toHaveBeenCalledTimes( 1 );
		expect( mockCreateRoot ).toHaveBeenCalledTimes( 2 );

		const newContainer = document.querySelector( '#remember-me' );

		expect( mockCreateRoot ).toHaveBeenLastCalledWith( newContainer );
	} );

	it( 'does not mount into a detached container when the payment options block is missing', () => {
		document.body.innerHTML = '<div class="wc-block-checkout"></div>';
		const { triggerLoad, triggerAjaxComplete } = loadCheckoutEntry();

		triggerLoad();

		expect( mockCreateRoot ).not.toHaveBeenCalled();
		expect( document.querySelector( '#remember-me' ) ).toBeNull();

		const paymentBlock = document.createElement( 'div' );
		paymentBlock.className = 'wp-block-woocommerce-checkout-payment-block';
		document
			.querySelector( '.wc-block-checkout' )
			.appendChild( paymentBlock );

		triggerAjaxComplete();

		expect( mockCreateRoot ).toHaveBeenCalledTimes( 1 );

		const container = document.querySelector( '#remember-me' );

		expect( container ).not.toBeNull();
		expect( mockCreateRoot ).toHaveBeenCalledWith( container );
	} );
} );
