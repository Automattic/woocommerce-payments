/**
 * External dependencies
 */
import { Page } from '@playwright/test';

// The dev-tools settings page (served by the woocommerce-payments-dev-tools
// plugin, pulled from its own trunk during E2E setup) is intermittently truncated
// mid-render by a PHP fatal, which drops its "Save Changes" submit button. A bare
// click on the missing button would wait out the full 120s test timeout, so we
// verify the page rendered fully, reload-and-retry when it didn't, and fail fast
// with a clear message otherwise. A save is a full POST -> redirect -> re-render
// round trip through that same fragile page, so the truncation can just as easily
// hit the post-save render as the initial one — hence the retry wraps the whole
// navigate/toggle/save/verify sequence.
const devToolsRenderTimeoutMs = 15 * 1000;
const devToolsMaxLoadAttempts = 3;

const goToDevToolsSettings = async ( page: Page ) => {
	await page.goto( '/wp-admin/admin.php?page=wcpaydev', {
		waitUntil: 'load',
	} );

	// The submit button renders after every settings section, so its presence
	// is proof the page was not truncated before it.
	return page
		.getByRole( 'button', { name: 'Save Changes' } )
		.waitFor( { state: 'visible', timeout: devToolsRenderTimeoutMs } )
		.then( () => true )
		.catch( () => false );
};

const saveDevToolsSettings = async ( page: Page ) => {
	const clicked = await page
		.getByRole( 'button', { name: 'Save Changes' } )
		.click( { timeout: devToolsRenderTimeoutMs } )
		.then( () => true )
		.catch( () => false );

	if ( ! clicked ) {
		return false;
	}

	await page.waitForLoadState( 'load' );

	// The "Settings saved" notice alone doesn't prove the post-save render
	// survived: the submit button sits after every settings section, so its
	// presence means the page the caller re-reads state from isn't truncated.
	const [ savedNoticeVisible, renderedFully ] = await Promise.all( [
		page
			.getByText( /Settings saved/ )
			.waitFor( { state: 'visible', timeout: devToolsRenderTimeoutMs } )
			.then( () => true )
			.catch( () => false ),
		page
			.getByRole( 'button', { name: 'Save Changes' } )
			.waitFor( { state: 'visible', timeout: devToolsRenderTimeoutMs } )
			.then( () => true )
			.catch( () => false ),
	] );

	return savedNoticeVisible && renderedFully;
};

const getIsCardTestingProtectionEnabled = ( page: Page ) =>
	page.getByLabel( /Card testing mitigations enabled/ ).isChecked();

const setCardTestingProtection = ( page: Page, enabled: boolean ) =>
	page
		.locator( 'label[for="wcpaydev_force_card_testing_protection_on"]' )
		.setChecked( enabled );

const getIsActAsDisconnectedFromWCPayEnabled = ( page: Page ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.isChecked();

const setActAsDisconnectedFromWCPay = ( page: Page, enabled: boolean ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.setChecked( enabled );

// Any leg of the toggle-and-save sequence can be taken down by the page's
// intermittent PHP fatal, so a failure anywhere restarts from navigation instead
// of retrying just the failing step against a possibly-truncated page. The final
// re-read of the setting guards against a save that rendered the "Settings saved"
// notice but silently dropped the checkbox update.
const setDevToolsSetting = async (
	page: Page,
	settingName: string,
	enabled: boolean,
	getIsEnabled: ( page: Page ) => Promise< boolean >,
	setEnabled: ( page: Page, enabled: boolean ) => Promise< void >
) => {
	for ( let attempt = 1; attempt <= devToolsMaxLoadAttempts; attempt++ ) {
		const renderedFully = await goToDevToolsSettings( page );

		if ( ! renderedFully ) {
			continue;
		}

		if ( ( await getIsEnabled( page ) ) === enabled ) {
			return;
		}

		await setEnabled( page, enabled );

		const saved = await saveDevToolsSettings( page );

		if ( saved && ( await getIsEnabled( page ) ) === enabled ) {
			return;
		}
	}

	throw new Error(
		`WCPay Dev Tools failed to set "${ settingName }" to ${ enabled } after ${ devToolsMaxLoadAttempts } attempts; ` +
			'the settings page was likely truncated by a PHP fatal during render or save. See the "PHP fatals" group in the E2E run log.'
	);
};

export const enableCardTestingProtection = async ( page: Page ) => {
	await setDevToolsSetting(
		page,
		'Card testing mitigations enabled',
		true,
		getIsCardTestingProtectionEnabled,
		setCardTestingProtection
	);
};

export const disableCardTestingProtection = async ( page: Page ) => {
	await setDevToolsSetting(
		page,
		'Card testing mitigations enabled',
		false,
		getIsCardTestingProtectionEnabled,
		setCardTestingProtection
	);
};

export const enableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await setDevToolsSetting(
		page,
		'act as disconnected from the Transact Platform Server',
		true,
		getIsActAsDisconnectedFromWCPayEnabled,
		setActAsDisconnectedFromWCPay
	);
};

export const disableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await setDevToolsSetting(
		page,
		'act as disconnected from the Transact Platform Server',
		false,
		getIsActAsDisconnectedFromWCPayEnabled,
		setActAsDisconnectedFromWCPay
	);
};
