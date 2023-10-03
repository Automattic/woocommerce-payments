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
	} );

	afterAll( async () => {
		await merchantWCP.disableProgressiveOnboarding();
		await merchantWCP.disableActAsDisconnectedFromWCPay();
		await merchant.logout();
	} );

	it( 'should pass merchant flow without any errors', async () => {
		await merchantWCP.openConnectPage();

		// todo clear the cache, so no previous session is restored
		await Promise.all( [
			evalAndClick(
				'div.connect-account-page button.components-button.is-primary'
			),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );
		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Let’s get your store ready to accept payments',
		} );

		await expect( page ).toClick(
			'div.stepper__content button.components-button.is-primary',
			{
				text: 'Continue',
			}
		);

		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'First, you’ll need to create an account',
		} );

		await expect( page ).toFill( 'input#inspector-text-control-0', 'Test' );
		await expect( page ).toFill( 'input#inspector-text-control-1', 'Test' );
		await expect( page ).toFill(
			'input#inspector-text-control-2',
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

		await expect( page ).toMatchElement( 'h1.stepper__heading', {
			text: 'Tell us about your business',
		} );
	} );
} );
