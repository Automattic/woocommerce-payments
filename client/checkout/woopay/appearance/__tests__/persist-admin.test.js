let mockGetConfig;
let mockGetAppearance;
let mockGetFontRulesFromPage;
let mockGetAppearanceType;
let mockIsPreviewing;

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: ( ...args ) => mockGetConfig( ...args ),
} ) );
jest.mock( 'wcpay/checkout/upe-styles', () => ( {
	getAppearance: ( ...args ) => mockGetAppearance( ...args ),
	getFontRulesFromPage: ( ...args ) => mockGetFontRulesFromPage( ...args ),
} ) );
jest.mock( 'wcpay/checkout/utils', () => ( {
	getAppearanceType: ( ...args ) => mockGetAppearanceType( ...args ),
} ) );
jest.mock( 'wcpay/checkout/preview', () => ( {
	isPreviewing: ( ...args ) => mockIsPreviewing( ...args ),
} ) );

const MOCK_APPEARANCE = {
	variables: { colorBackground: '#ffffff' },
	rules: { '.Input': { color: '#333' } },
};

const setupDefaults = () => {
	mockGetConfig = jest.fn( ( key ) => {
		const config = {
			adminAppearanceNonce: 'test-nonce-123',
			ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
		};
		return config[ key ];
	} );
	mockIsPreviewing = jest.fn( () => true );
	mockGetAppearanceType = jest.fn( () => 'stripe' );
	mockGetAppearance = jest.fn( () => MOCK_APPEARANCE );
	mockGetFontRulesFromPage = jest.fn( () => [
		{ cssSrc: 'https://fonts.googleapis.com/css?family=Roboto' },
	] );
	global.fetch = jest.fn( () => Promise.resolve() );
};

describe( 'maybePersistAdminWoopayAppearance', () => {
	beforeEach( () => {
		setupDefaults();
		jest.resetModules();
		delete window.wp;
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete window.wp;
	} );

	// Re-imports the module to reset module-level state (attempted,
	// listeningForSaved) between tests.
	const loadModule = () => {
		const mod = require( '../persist-admin' );
		return mod.maybePersistAdminWoopayAppearance;
	};

	describe( 'precondition guards', () => {
		it( 'does not fetch when adminAppearanceNonce is missing', () => {
			mockGetConfig = jest.fn( () => undefined );
			const persist = loadModule();

			persist();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );

		it( 'does not fetch when not in preview context', () => {
			mockIsPreviewing = jest.fn( () => false );
			const persist = loadModule();

			persist();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );

		it( 'does not fetch when getAppearance returns null', () => {
			mockGetAppearance = jest.fn( () => null );
			const persist = loadModule();

			persist();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );

		it( 'does not fetch when ajaxUrl is missing', () => {
			mockGetConfig = jest.fn( ( key ) => {
				if ( key === 'adminAppearanceNonce' ) {
					return 'test-nonce-123';
				}
				return undefined;
			} );
			const persist = loadModule();

			persist();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'happy path', () => {
		it( 'sends a POST with correct URL, method, and credentials', () => {
			const persist = loadModule();

			persist();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
			const [ url, options ] = global.fetch.mock.calls[ 0 ];
			expect( url ).toBe( 'https://example.com/wp-admin/admin-ajax.php' );
			expect( options.method ).toBe( 'POST' );
			expect( options.credentials ).toBe( 'same-origin' );
		} );

		it( 'includes action, nonce, appearance, and font_rules in FormData', () => {
			const persist = loadModule();

			persist();

			const body = global.fetch.mock.calls[ 0 ][ 1 ].body;
			expect( body ).toBeInstanceOf( FormData );
			expect( body.get( 'action' ) ).toBe(
				'wcpay_admin_set_woopay_appearance'
			);
			expect( body.get( '_ajax_nonce' ) ).toBe( 'test-nonce-123' );
			expect( body.get( 'appearance[variables][colorBackground]' ) ).toBe(
				'#ffffff'
			);
			expect( JSON.parse( body.get( 'font_rules' ) ) ).toEqual( [
				{
					cssSrc: 'https://fonts.googleapis.com/css?family=Roboto',
				},
			] );
		} );
	} );

	describe( 'attempted guard', () => {
		it( 'only fetches once even when called multiple times', () => {
			const persist = loadModule();

			persist();
			persist();
			persist();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'Customizer saved event', () => {
		it( 'binds to wp.customize saved event when available', () => {
			const mockBind = jest.fn();
			window.wp = { customize: { bind: mockBind } };
			const persist = loadModule();

			persist();

			expect( mockBind ).toHaveBeenCalledWith(
				'saved',
				expect.any( Function )
			);
		} );

		it( 're-persists appearance when saved event fires', () => {
			let savedCallback;
			const mockBind = jest.fn( ( event, cb ) => {
				if ( event === 'saved' ) {
					savedCallback = cb;
				}
			} );
			window.wp = { customize: { bind: mockBind } };
			const persist = loadModule();

			persist();
			expect( global.fetch ).toHaveBeenCalledTimes( 1 );

			// Simulate a Customizer publish.
			savedCallback();
			expect( global.fetch ).toHaveBeenCalledTimes( 2 );

			// A second publish should also re-persist.
			savedCallback();
			expect( global.fetch ).toHaveBeenCalledTimes( 3 );
		} );

		it( 'does not bind when wp.customize is absent', () => {
			const persist = loadModule();

			expect( () => persist() ).not.toThrow();
			// Fetch still called for the initial persist.
			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
