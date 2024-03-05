/**
 * External dependencies
 */
import { merchantWCP } from '../../../utils';

const { merchant } = require( '@woocommerce/e2e-utils' );

const checkboxCaptureLaterOption = 'capture-later-checkbox';
const paymentMethodWarningIconId = 'loadable-checkbox-icon-warning';
const confirmationModalClass = '.wcpay-confirmation-modal';

describe( 'As a merchant, I should be prompted a confirmation modal when I try to activate the manual capture', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await merchantWCP.skipFraudProtectionTour();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	afterEach( async () => {
		await merchantWCP.unsetCheckboxByTestId( checkboxCaptureLaterOption );
	} );

	it( 'should show the confirmation dialog when enabling the manual capture', async () => {
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toMatch(
			'Payments must be captured within 7 days or the authorization will expire and money will be returned to the shopper'
		);
	} );

	it( 'should not show the confirmation dialog when disabling the manual capture', async () => {
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toClick( 'button', {
			text: 'Enable',
		} );

		await merchantWCP.unsetCheckboxByTestId( checkboxCaptureLaterOption );
		await expect( page ).not.toMatchElement( '.wcpay-confirmation-modal' );
	} );

	it( 'should show the non-card methods disabled when manual capture is enabled', async () => {
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toClick( 'button', {
			text: 'Enable',
		} );

		const paymentMethodWarningIconElement = await page.$(
			`[data-testid="${ paymentMethodWarningIconId }"]`
		);
		await expect( paymentMethodWarningIconElement ).toMatch(
			'cannot be enabled at checkout. Click to expand.'
		);
	} );
} );
