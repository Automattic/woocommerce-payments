/**
 * Internal dependencies
 */
import { showErrorMessage } from '../utils';

jest.mock( '../utils', () => ( {
	showErrorMessage: jest.fn(),
} ) );

describe( 'validateGiftCardFields', () => {
	const TEST_CONTEXT = 'product';

	beforeEach( () => {
		jest.resetModules();
		showErrorMessage.mockClear();
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
		const handler =
			require( '../use-express-checkout-product-handler' ).default;
		const { getProductData } = handler( {}, TEST_CONTEXT );
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

	it( 'returns false and shows inline notice when required gift card field is empty', () => {
		setupDomWithGiftCardForm( { wc_gc_giftcard_to: '' } );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( showErrorMessage ).toHaveBeenCalledWith(
			TEST_CONTEXT,
			'Please fill out all required fields.'
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

	it( 'returns false and shows inline notice when single recipient email is invalid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to: 'notanemail',
			wc_gc_giftcard_from: 'Sender',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( showErrorMessage ).toHaveBeenCalledWith(
			TEST_CONTEXT,
			'Please enter a valid email address.'
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

	it( 'returns false and shows inline notice when one of multiple recipient emails is invalid', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,notanemail',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( showErrorMessage ).toHaveBeenCalledWith(
			TEST_CONTEXT,
			'Please enter valid email addresses.'
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

	it( 'returns false and shows inline notice for trailing comma in multiple recipients', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( showErrorMessage ).toHaveBeenCalledWith(
			TEST_CONTEXT,
			'Please enter valid email addresses.'
		);
	} );

	it( 'returns false and shows inline notice for empty segment in multiple recipients', () => {
		setupDomWithGiftCardForm( {
			wc_gc_giftcard_to_multiple: 'a@example.com,,b@example.com',
		} );
		const getProductData = getProductDataFromHook();
		const result = getProductData();
		expect( result ).toBe( false );
		expect( showErrorMessage ).toHaveBeenCalledWith(
			TEST_CONTEXT,
			'Please enter valid email addresses.'
		);
	} );
} );

describe( 'getProductData — IAPI block (Add to Cart + Options)', () => {
	beforeEach( () => {
		jest.resetModules();
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	const setupIAPIBlock = ( { parentId = '42', variationId = '0' } = {} ) => {
		const form = document.createElement( 'form' );
		form.classList.add( 'wp-block-add-to-cart-with-options' );

		const addToCart = document.createElement( 'input' );
		addToCart.type = 'hidden';
		addToCart.name = 'add-to-cart';
		addToCart.value = parentId;
		form.appendChild( addToCart );

		const productIdInput = document.createElement( 'input' );
		productIdInput.type = 'hidden';
		productIdInput.name = 'product_id';
		productIdInput.value = parentId;
		form.appendChild( productIdInput );

		const variationInput = document.createElement( 'input' );
		variationInput.type = 'hidden';
		variationInput.name = 'variation_id';
		variationInput.value = variationId;
		form.appendChild( variationInput );

		const qtyWrapper = document.createElement( 'div' );
		qtyWrapper.classList.add( 'quantity' );
		const qtyInput = document.createElement( 'input' );
		qtyInput.classList.add( 'qty' );
		qtyInput.value = '1';
		qtyWrapper.appendChild( qtyInput );
		form.appendChild( qtyWrapper );

		const submit = document.createElement( 'button' );
		submit.type = 'submit';
		form.appendChild( submit );

		document.body.appendChild( form );
	};

	const getProductDataFromHook = () => {
		const handler =
			require( '../use-express-checkout-product-handler' ).default;
		const { getProductData } = handler( {} );
		return getProductData;
	};

	it( 'uses the resolved variation ID as product_id and omits attributes', () => {
		setupIAPIBlock( { parentId: '42', variationId: '99' } );
		const result = getProductDataFromHook()();
		expect( result.product_id ).toBe( 99 );
		expect( result.attributes ).toBeUndefined();
	} );

	it( 'falls back to FormData when no variation is resolved', () => {
		setupIAPIBlock( { parentId: '42', variationId: '0' } );
		const result = getProductDataFromHook()();
		// FormData branch spreads form fields; parent product_id is preserved.
		expect( result.product_id ).toBe( '42' );
	} );
} );
