/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WooPayPreview from '../woopay-preview';

describe( 'WooPayPreview', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			restUrl: 'http://example.com/wp-json/',
			siteLogoUrl: '',
		};
		global.wcSettings = {
			siteTitle: 'Test Store',
		};
	} );

	it( 'renders without errors when appearance is null', () => {
		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ null }
			/>
		);

		expect(
			container.querySelector( '.preview-layout' )
		).toBeInTheDocument();
	} );

	it( 'applies header background to container for seamless header strip', () => {
		const appearance = {
			variables: { colorBackground: '#f0f0f0' },
			rules: {
				'.Header': { backgroundColor: '#333333' },
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		// Container uses header background (not body background) so the
		// strip above the header blends seamlessly.
		const previewContainer = container.querySelector(
			'.preview-layout__container'
		);
		expect( previewContainer.style.backgroundColor ).toBe(
			'rgb(51, 51, 51)'
		);

		// Body uses the page background color.
		const body = container.querySelector( '.preview-layout__body' );
		expect( body.style.backgroundColor ).toBe( 'rgb(240, 240, 240)' );
	} );

	it( 'keeps default WooPay purple on Place Order button', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Button': {
					backgroundColor: '#ff0000',
					color: '#ffffff',
				},
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		// Button should NOT inherit theme colors — WooPay branding.
		const button = container.querySelector(
			'.preview-layout__checkout-button'
		);
		expect( button.style.backgroundColor ).toBe( '' );
		expect( button.style.color ).toBe( '' );
	} );

	it( 'applies themed header colors', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Header': {
					backgroundColor: '#333333',
					color: '#eeeeee',
				},
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const storeHeader = container.querySelector(
			'.preview-layout__store-header'
		);
		expect( storeHeader.style.backgroundColor ).toBe( 'rgb(51, 51, 51)' );

		const headerText = container.querySelector( '.header-text' );
		expect( headerText.style.color ).toBe( 'rgb(238, 238, 238)' );
	} );

	it( 'does not apply inline styles when appearance is null', () => {
		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ null }
			/>
		);

		const button = container.querySelector(
			'.preview-layout__checkout-button'
		);
		expect( button.style.backgroundColor ).toBe( '' );
		expect( button.style.color ).toBe( '' );
	} );

	it( 'applies themed section header colors', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Label': { color: '#444444' },
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const sectionHeaders = container.querySelectorAll(
			'.preview-layout__section-header'
		);
		sectionHeaders.forEach( ( header ) => {
			expect( header.style.color ).toBe( 'rgb(68, 68, 68)' );
		} );
	} );

	it( 'applies font family from appearance variables', () => {
		const appearance = {
			variables: { fontFamily: 'Georgia, serif' },
			rules: {},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const root = container.querySelector( '.preview-layout' );
		expect( root.style.fontFamily ).toBe( 'Georgia, serif' );
	} );

	it( 'applies themed footer colors', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Footer': {
					backgroundColor: '#222222',
					color: '#cccccc',
				},
				'.Footer-link': { color: '#aaaaaa' },
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const footer = container.querySelector( '.preview-layout__footer' );
		expect( footer.style.backgroundColor ).toBe( 'rgb(34, 34, 34)' );
		expect( footer.style.color ).toBe( 'rgb(204, 204, 204)' );
	} );

	it( 'applies card border color derived from background', () => {
		const appearance = {
			variables: { colorBackground: '#ffffff' },
			rules: {},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const rightColumn = container.querySelector(
			'.preview-layout__right-column'
		);
		// darkenColor('#ffffff', 6) = #f0f0f0
		expect( rightColumn.style.borderColor ).toBe( '#f0f0f0' );
	} );

	it( 'applies text color to field values', () => {
		const appearance = {
			variables: { colorText: '#112233' },
			rules: {},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const fieldValue = container.querySelector(
			'.preview-layout__field-value'
		);
		expect( fieldValue.style.color ).toBe( 'rgb(17, 34, 51)' );
	} );

	it( 'sets link color CSS custom property on custom message', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Link': { color: '#ff6600' },
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage="<a>Terms</a>"
				appearance={ appearance }
			/>
		);

		const textBox = container.querySelector( '.preview-layout__text-box' );
		expect( textBox.style.getPropertyValue( '--preview-link-color' ) ).toBe(
			'#ff6600'
		);
	} );

	it( 'handles appearance with empty variables and rules', () => {
		const appearance = {
			variables: {},
			rules: {},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		// Should render without errors and not apply any themed styles.
		const root = container.querySelector( '.preview-layout' );
		expect( root ).toBeInTheDocument();
		expect( root.style.fontFamily ).toBe( '' );
	} );

	it( 'handles partial appearance with only some rules', () => {
		const appearance = {
			variables: {},
			rules: {
				'.Header': { backgroundColor: '#112233' },
				// No .Label, .Footer, .Link, etc.
			},
		};

		const { container } = render(
			<WooPayPreview
				storeName="Test Store"
				storeLogo=""
				customMessage=""
				appearance={ appearance }
			/>
		);

		const storeHeader = container.querySelector(
			'.preview-layout__store-header'
		);
		expect( storeHeader.style.backgroundColor ).toBe( 'rgb(17, 34, 51)' );

		// Section headers should have no inline color.
		const sectionHeaders = container.querySelectorAll(
			'.preview-layout__section-header'
		);
		sectionHeaders.forEach( ( header ) => {
			expect( header.style.color ).toBe( '' );
		} );
	} );
} );
