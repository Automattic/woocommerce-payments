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
} );
