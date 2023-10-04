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
		await merchantWCP.enableProgressiveOnboarding();
		await merchantWCP.enableActAsDisconnectedFromWCPay();
		// todo clear/mock wcpaySettings.onboardingFlowState = null, so no previous session is restored
	} );

	afterAll( async () => {
		await merchantWCP.disableProgressiveOnboarding();
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
		await expect( page ).toFill(
			'.personal-details__firstname input.components-text-control__input',
			'Test'
		);
		await expect( page ).toFill(
			'.personal-details__lastname input.components-text-control__input',
			'Test'
		);
		await expect( page ).toFill(
			'.personal-details__email input.components-text-control__input',
			'test@gmail.com'
		);
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
		await expect( page ).toClick(
			'.business-details__type button.components-custom-select-control__button'
		);
		await page.waitForSelector(
			'.business-details__type li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'.business-details__type li.components-custom-select-control__item'
		);
		// pick Software type of goods
		await expect( page ).toClick(
			'.business-details__mcc button.wcpay-component-grouped-select-control__button'
		);
		await page.waitForSelector(
			'.business-details__mcc li.wcpay-component-grouped-select-control__item:not(.is-group)'
		);
		await expect( page ).toClick(
			'.business-details__mcc li.wcpay-component-grouped-select-control__item:not(.is-group)'
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
		await expect( page ).toClick(
			'.store-details__annual_revenue button.components-custom-select-control__button'
		);
		await page.waitForSelector(
			'.store-details__annual_revenue li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'.store-details__annual_revenue li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'.store-details__go_live_timeframe button.components-custom-select-control__button'
		);
		await page.waitForSelector(
			'.store-details__go_live_timeframe li.components-custom-select-control__item'
		);
		await expect( page ).toClick(
			'.store-details__go_live_timeframe li.components-custom-select-control__item'
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
