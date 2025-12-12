const ORDER_ATTRIBUTION_ELEMENT_ID =
	'wcpay-express-checkout__order-attribution-inputs';

describe( 'Order Attribution compatibility', () => {
	const mockSetOrderTracking = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	afterEach( () => {
		delete global.wc_order_attribution;
		document.getElementById( ORDER_ATTRIBUTION_ELEMENT_ID )?.remove();
	} );

	it( 'should create the element if it is not present in the DOM', () => {
		expect(
			document.getElementById( ORDER_ATTRIBUTION_ELEMENT_ID )
		).toBeNull();

		jest.isolateModules( () => {
			require( '../wc-order-attribution' );
		} );

		const element = document.getElementById( ORDER_ATTRIBUTION_ELEMENT_ID );
		expect( element ).not.toBeNull();
		expect( element.tagName.toLowerCase() ).toBe(
			'wc-order-attribution-inputs'
		);
	} );

	it( 'should not create a duplicate element if one already exists', () => {
		const existingElement = document.createElement(
			'wc-order-attribution-inputs'
		);
		existingElement.id = ORDER_ATTRIBUTION_ELEMENT_ID;
		document.body.appendChild( existingElement );

		jest.isolateModules( () => {
			require( '../wc-order-attribution' );
		} );

		const elements = document.querySelectorAll(
			`#${ ORDER_ATTRIBUTION_ELEMENT_ID }`
		);
		expect( elements.length ).toBe( 1 );
	} );

	it( 'should call setOrderTracking when wc_order_attribution is available', () => {
		global.wc_order_attribution = {
			params: {
				allowTracking: true,
			},
			setOrderTracking: mockSetOrderTracking,
		};

		jest.isolateModules( () => {
			require( '../wc-order-attribution' );
		} );

		expect( mockSetOrderTracking ).toHaveBeenCalledWith( true );
	} );

	it( 'should not throw when wc_order_attribution is not available', () => {
		delete global.wc_order_attribution;

		expect( () => {
			jest.isolateModules( () => {
				require( '../wc-order-attribution' );
			} );
		} ).not.toThrow();
	} );
} );
