jest.spyOn( window, 'alert' ).mockImplementation( () => {} );

describe( 'validateGiftCardFields', () => {
	beforeEach( () => {
		jest.resetModules();
		window.alert.mockClear();
	} );

	const setupDomWithGiftCardForm = ( formFields = {} ) => {
		const form = document.createElement( 'form' );
		form.classList.add( 'cart' );

		const addToCartButton = document.createElement( 'button' );
		addToCartButton.classList.add( 'single_add_to_cart_button' );
		addToCartButton.value = '123';
		form.appendChild( addToCartButton );

		const qtyWrapper = document.createElement( 'div' );
		qtyWrapper.classList.add( 'quantity' );
		const qtyInput = document.createElement( 'input' );
		qtyInput.classList.add( 'qty' );
		qtyInput.value = '1';
		qtyWrapper.appendChild( qtyInput );
		form.appendChild( qtyWrapper );

		Object.entries( formFields ).forEach( ( [ name, value ] ) => {
			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild( input );
		} );

		document.body.appendChild( form );
		return form;
	};

	const getProductDataFromHook = () => {
		const handler = require( '../use-express-checkout-product-handler' )
			.default;
		const { getProductData } = handler( {} );
		return getProductData;
	};

	afterEach( () => {
		const form = document.querySelector( 'form.cart' );
		if ( form ) {
			document.body.removeChild( form );
		}
	} );

	it( 'returns data when no gift card fields are present', () => {
		setupDomWithGiftCardForm();
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).not.toBe( false );
		expect( result.product_id ).toBe( '123' );
	} );

	it( 'returns false when required gift card field is empty', () => {
		setupDomWithGiftCardForm( { wc_gc_giftcard_to: '' } );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( window.alert ).toHaveBeenCalledWith(
			'Please fill out all required fields'
		);
	} );

	it( 'returns data when single recipient email is valid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to: 'recipient@example.com',
			wc_gc_giftcard_from: 'Sender',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).not.toBe( false );
	} );

	it( 'returns false when single recipient email is invalid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to: 'notanemail',
			wc_gc_giftcard_from: 'Sender',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( window.alert ).toHaveBeenCalledWith(
			'Please type only valid emails'
		);
	} );

	it( 'returns data when multiple recipient emails are all valid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,b@example.com',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).not.toBe( false );
	} );

	it( 'returns false when one of multiple recipient emails is invalid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,notanemail',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( window.alert ).toHaveBeenCalledWith(
			'Please type only valid emails'
		);
	} );

	it( 'handles whitespace around commas in multiple recipients', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: ' a@example.com , b@example.com ',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).not.toBe( false );
	} );

	it( 'returns false for trailing comma in multiple recipients', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( window.alert ).toHaveBeenCalledWith(
			'Please type only valid emails'
		);
	} );

	it( 'returns false for empty segment in multiple recipients', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,,b@example.com',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( window.alert ).toHaveBeenCalledWith(
			'Please type only valid emails'
		);
	} );
} );
