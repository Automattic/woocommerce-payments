/**
 * Internal dependencies
 */
import { getCrumbs, getFields, getSubPage } from '../pages';
import { route } from '../route';

const mainPage = {
	name: 'woopayments',
	label: 'WooPayments settings',
	fields: [],
};
const woopay = {
	name: 'woopayments/woopay',
	label: 'WooPay',
	fields: [ { id: 'woopay_on_cart', type: 'boolean' } ],
};

beforeEach( () => {
	( global as any ).wcpaySettingsScreenConfig = {
		kind: 'woo_settings_payments',
		baseURL: '/wc/v3/payments/settings-dataform',
		recordId: 'woopayments',
		entities: [ mainPage, woopay ],
		fraudRulesUrl: '',
	};
} );

describe( 'getSubPage', () => {
	it( 'finds a sub-page by its path below the main page', () => {
		expect( getSubPage( 'woopay' ) ).toBe( woopay );
	} );

	it.each( [ undefined, '', 'nope', 'woopayments' ] )(
		'finds no sub-page for %p',
		( path ) => {
			expect( getSubPage( path ) ).toBeUndefined();
		}
	);
} );

describe( 'route', () => {
	it( 'opens a registered sub-page', () => {
		expect( () =>
			route.beforeLoad( { params: { subpage: 'woopay' } } )
		).not.toThrow();
	} );

	it( 'shows "not found" for an unknown sub-page', () => {
		expect( () =>
			route.beforeLoad( { params: { subpage: 'nope' } } )
		).toThrow();
	} );
} );

it( 'adds the parts PHP cannot describe to the sub-page fields', () => {
	const getValue = jest.fn();
	expect( getFields( woopay, { woopay_on_cart: { getValue } } ) ).toEqual( [
		{ id: 'woopay_on_cart', type: 'boolean', getValue },
	] );
} );

it( 'links the breadcrumbs to the classic Payments settings and the main page', () => {
	expect(
		getCrumbs( woopay, 'https://example.com/wp-admin/admin.php?page=x' )
	).toEqual( [
		{
			label: 'Payments',
			to: 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout',
		},
		{
			label: 'WooPayments settings',
			to: '/settings/payments/woopayments',
		},
		{ label: 'WooPay' },
	] );
} );
