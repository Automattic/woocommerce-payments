/**
 * External dependencies
 */
import config from 'config';

const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../utils/flows';
import {
	confirmCardAuthentication,
	setupProductCheckout,
} from '../../utils/payments';

const cards = [
	[ 'basic', config.get( 'cards.basic' ) ],
	[ '3DS2', config.get( 'cards.3ds2' ) ],
];

const WCPAY_DEV_TOOLS = `${ config.get(
	'url'
) }wp-admin/admin.php?page=wcpaydev`;

async function activateUpe() {
	await page.goto( WCPAY_DEV_TOOLS, {
		waitUntil: 'networkidle0',
	} );

	if ( ! ( await page.$( '#_wcpay_feature_upe:checked' ) ) ) {
		await expect( page ).toClick( 'label', {
			text: 'Enable UPE checkout',
		} );
	}

	const isAdditionalPaymentsActive = await page.$(
		'#_wcpay_feature_upe_additional_payment_methods:checked'
	);

	if ( ! isAdditionalPaymentsActive ) {
		await expect( page ).toClick( 'label', {
			text: 'Add UPE additional payment methods',
		} );
	}

	await expect( page ).toClick( 'input[type="submit"]' );
	await page.waitForNavigation( {
		waitUntil: 'networkidle0',
	} );
}

async function deactivateUpe() {
	await page.goto( WCPAY_DEV_TOOLS, {
		waitUntil: 'networkidle0',
	} );

	if ( await page.$( '#_wcpay_feature_upe:checked' ) ) {
		await expect( page ).toClick( 'label', {
			text: 'Enable UPE checkout',
		} );
	}

	const isAdditionalPaymentsActive = await page.$(
		'#_wcpay_feature_upe_additional_payment_methods:checked'
	);

	if ( isAdditionalPaymentsActive ) {
		await expect( page ).toClick( 'label', {
			text: 'Add UPE additional payment methods',
		} );
	}

	await expect( page ).toClick( 'input[type="submit"]' );
	await page.waitForNavigation( {
		waitUntil: 'networkidle0',
	} );
}

describe( 'Saved cards ', () => {
	describe.each( cards )(
		'when using a %s card added through my account',
		( cardType, card ) => {
			beforeAll( async () => {
				await merchant.login();
				await activateUpe();
				await merchant.logout();

				await shopper.login();
			} );

			afterAll( async () => {
				await shopperWCP.logout();
				await merchant.login();
				await deactivateUpe();
				await merchant.logout();
			} );

			it( 'should save the card', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.addNewPaymentMethod( cardType, card );
				await expect( page ).toMatch(
					'Payment method successfully added'
				);
			} );

			it( 'should process a payment with the saved card', async () => {
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await shopperWCP.selectSavedPaymentMethod(
					`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
				);

				if ( 'basic' === cardType ) {
					await shopper.placeOrder();
				} else {
					await expect( page ).toClick( '#place_order' );
					await confirmCardAuthentication( page, cardType );
					await page.waitForNavigation( {
						waitUntil: 'networkidle0',
					} );
				}

				await expect( page ).toMatch( 'Order received' );
				await expect( page ).toMatchElement(
					'.woocommerce-order-overview__payment-method',
					'Visa credit card'
				);
			} );

			it( 'should delete the card', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.deleteSavedPaymentMethod( card.label );
				await expect( page ).toMatch( 'Payment method deleted' );
			} );
		}
	);
} );
