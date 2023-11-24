/**
 * External dependencies
 */
const { merchant, evalAndClick } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, uiLoaded } from '../../../utils';

describe( 'Admin merchant progressive onboarding', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.enableActAsDisconnectedFromWCPay();
	} );

	afterAll( async () => {
		await merchantWCP.disableActAsDisconnectedFromWCPay();
		await merchant.logout();
	} );

	it( 'should pass merchant flow without any errors', async () => {
		// Open connect account page and click Finish Setup
		await merchantWCP.openConnectPage();
		await Promise.all( [
			evalAndClick(
				'div.connect-account-page button.components-button.is-primary'
			),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );

		// Merchant vs builder flow step
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Let’s get your store ready to accept payments',
		} );
		await expect( page ).toClick(
			'div.stepper__content button.components-button.is-primary',
			{
				text: 'Continue',
			}
		);

		// User details step
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'First, you’ll need to create an account',
		} );
		await expect( page ).toFill( '[name="individual.first_name"]', 'Test' );
		await expect( page ).toFill( '[name="individual.last_name"]', 'Test' );
		await expect( page ).toFill( '[name="email"]', 'test@gmail.com' );
		await page.waitForSelector(
			'div.wcpay-component-phone-number-control input[type="text"]'
		);
		await expect( page ).toFill(
			'div.wcpay-component-phone-number-control input[type="text"]',
			'0000000000'
		);
		await expect( page ).toClick(
			'div.stepper__content button.components-button.is-primary',
			{
				text: 'Continue',
			}
		);

		// Tell us about your business step
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Tell us about your business',
		} );
		// pick Individual business entity
		await expect( page ).toClick( '[name="business_type"]' );
		await page.waitForSelector(
			'[name="business_type"] ~ ul li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'[name="business_type"] ~ ul li.components-custom-select-control__item'
		);
		// pick Software type of goods
		await expect( page ).toClick( '[name="mcc"]' );
		await page.waitForSelector(
			'[name="mcc"] ~ ul li.wcpay-component-grouped-select-control__item:not(.is-group)'
		);
		await expect( page ).toClick(
			'[name="mcc"] ~ ul li.wcpay-component-grouped-select-control__item:not(.is-group)'
		);
		await expect( page ).toClick(
			'div.stepper__content button.components-button.is-primary',
			{
				text: 'Continue',
			}
		);

		// Store details step: pick annual revenue and go live timeframe
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Please share a few more details',
		} );
		await expect( page ).toClick( '[name="annual_revenue"]' );
		await page.waitForSelector(
			'[name="annual_revenue"] ~ ul li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'[name="annual_revenue"] ~ ul li.components-custom-select-control__item'
		);
		await expect( page ).toClick( '[name="go_live_timeframe"]' );
		await page.waitForSelector(
			'[name="go_live_timeframe"] ~ ul li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'[name="go_live_timeframe"] ~ ul li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'div.stepper__content button.components-button.is-primary',
			{
				text: 'Continue',
			}
		);

		// Loading screen
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Let’s get you set up for payments',
		} );

		// Merchant is redirected away to payments/connect again (because of force fisconnected option)
		// todo at some point test real Stripe KYC
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
	} );
} );
