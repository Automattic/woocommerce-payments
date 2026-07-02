/**
 * Internal dependencies
 */
// The module under test is required fresh inside each test so its module-level
// root cache is reset between cases.

const mockCreateRoot = jest.fn();

jest.mock( 'react-dom/client', () => ( {
	createRoot: ( ...args ) => mockCreateRoot( ...args ),
} ) );

jest.mock(
	'wcpay/components/woopay/save-user/checkout-page-save-user',
	() => () => null
);

const requireModule = () =>
	require( 'wcpay/checkout/woopay/index' ).renderSaveUserSection;

describe( 'renderSaveUserSection - Blocks checkout', () => {
	beforeEach( () => {
		jest.resetModules();
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
		const renderSaveUserSection = requireModule();

		renderSaveUserSection();
		renderSaveUserSection();

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
		const renderSaveUserSection = requireModule();

		renderSaveUserSection();
		const firstRoot = mockCreateRoot.mock.results[ 0 ].value;

		// Simulate WC core replacing the checkout subtree, taking our
		// container with it.
		document.querySelector( '#remember-me' ).remove();

		renderSaveUserSection();

		expect( firstRoot.unmount ).toHaveBeenCalledTimes( 1 );
		expect( mockCreateRoot ).toHaveBeenCalledTimes( 2 );

		const newContainer = document.querySelector( '#remember-me' );
		expect( mockCreateRoot ).toHaveBeenLastCalledWith( newContainer );
	} );

	it( 'does not mount into a detached container when the payment options block is missing', () => {
		document.body.innerHTML = '<div class="wc-block-checkout"></div>';
		const renderSaveUserSection = requireModule();

		renderSaveUserSection();

		expect( mockCreateRoot ).not.toHaveBeenCalled();
		expect( document.querySelector( '#remember-me' ) ).toBeNull();

		const paymentBlock = document.createElement( 'div' );
		paymentBlock.className = 'wp-block-woocommerce-checkout-payment-block';
		document
			.querySelector( '.wc-block-checkout' )
			.appendChild( paymentBlock );

		renderSaveUserSection();

		expect( mockCreateRoot ).toHaveBeenCalledTimes( 1 );

		const container = document.querySelector( '#remember-me' );
		expect( container ).not.toBeNull();
		expect( mockCreateRoot ).toHaveBeenCalledWith( container );
	} );
} );
