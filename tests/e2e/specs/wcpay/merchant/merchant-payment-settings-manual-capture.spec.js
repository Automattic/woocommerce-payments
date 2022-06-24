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
	} );

	afterAll( async () => {
		await merchantWCP.deactivateUpe();
		await merchant.logout();
	} );

	it( 'should show the confirmation dialog when enabling the manual capture while UPE is activated', async () => {
		await merchantWCP.activateUpe();
		await merchantWCP.openWCPSettings();
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toMatch(
			'Are you sure you want to enable manual capture of payments?'
		);
		await merchantWCP.deactivateUpe();
	} );

	it( 'should not show the confirmation dialog when disabling the manual capture while UPE is activated', async () => {
		await merchantWCP.activateUpe();
		await merchantWCP.openWCPSettings();
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toClick( 'button', {
			text: 'Enable',
		} );

		await merchantWCP.unsetCheckboxByTestId( checkboxCaptureLaterOption );
		await expect( page ).not.toMatchElement( '.wcpay-confirmation-modal' );
		await merchantWCP.deactivateUpe();
	} );

	it( 'should show the UPE methods disabled when manual capture is enabled', async () => {
		await merchantWCP.activateUpe();
		await merchantWCP.openWCPSettings();

		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		const confirmationModal = await expect( page ).toMatchElement(
			confirmationModalClass
		);
		await expect( confirmationModal ).toClick( 'button', {
			text: 'Enable',
		} );

		await page.waitForSelector(
			`[data-testid="${ paymentMethodWarningIconId }"]`
		);

		const paymentMethodWarningIconElement = await page.$(
			`[data-testid="${ paymentMethodWarningIconId }"]`
		);

		await expect( paymentMethodWarningIconElement ).toMatch(
			'cannot be enabled at checkout. Click to expand.'
		);
		await merchantWCP.deactivateUpe();
	} );

	it( 'should not show the confirmation dialog when enabling the manual capture while UPE is disabled', async () => {
		await merchantWCP.openWCPSettings();
		await merchantWCP.setCheckboxByTestId( checkboxCaptureLaterOption );
		await expect( page ).not.toMatchElement( '.wcpay-confirmation-modal' );
	} );
} );
