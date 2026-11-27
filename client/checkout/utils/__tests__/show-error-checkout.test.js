import $ from 'jquery';
import showErrorCheckout from '../show-error-checkout';

describe( 'showErrorCheckout', () => {
	const originalJQuery = global.jQuery;

	beforeEach( () => {
		global.jQuery = $;
		$.scroll_to_notices = jest.fn();
		document.body.innerHTML = '<form class="checkout"></form>';
	} );

	afterEach( () => {
		global.jQuery = originalJQuery;
		delete $.scroll_to_notices;
		document.body.innerHTML = '';
	} );

	test( 'preserves WooCommerce notice markup', () => {
		showErrorCheckout(
			'<ul class="woocommerce-error" role="alert"><li><a href="https://example.com/help">Payment failed</a></li></ul>',
			false,
			false
		);

		const notice = document.querySelector( '.woocommerce-error' );

		expect( notice.tagName ).toBe( 'UL' );
		expect( notice.getAttribute( 'role' ) ).toBe( 'alert' );
		expect( notice.querySelector( 'a' ).href ).toBe(
			'https://example.com/help'
		);
	} );

	test( 'removes executable content from error messages', () => {
		showErrorCheckout(
			'<img src="x" onerror="window.attack()"><a href="javascript:window.attack()">Help</a><script>window.attack()</script>',
			false,
			false
		);

		const notice = document.querySelector( '.woocommerce-error' );

		expect( notice.querySelector( 'img' ).hasAttribute( 'onerror' ) ).toBe(
			false
		);
		expect( notice.querySelector( 'a' ).hasAttribute( 'href' ) ).toBe(
			false
		);
		expect( notice.querySelector( 'script' ) ).toBeNull();
	} );
} );
