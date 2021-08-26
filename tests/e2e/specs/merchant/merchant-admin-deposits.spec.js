/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );
const { dateI18n } = require( '@wordpress/date' );
const moment = require( 'moment' );
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
import {
	merchantWCP,
	takeScreenshot,
	countLines,
	getTextAtLine,
} from '../../utils';

const DOWNLOADS_PATH = './tests/e2e/downloads';

describe( 'Admin deposits', () => {
	beforeAll( async () => {
		await page._client.send( 'Page.setDownloadBehavior', {
			behavior: 'allow',
			downloadPath: DOWNLOADS_PATH,
		} );
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openDeposits();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Deposit history',
		} );
		await takeScreenshot( 'merchant-admin-deposits' );
	} );

	it( 'exported data should match the visible rows in Deposit History', async () => {
		await merchantWCP.openDeposits();
		await page.click( '.woocommerce-table__download-button' );
		await page.waitFor( 5000 );

		const fileNames = fs.readdirSync( DOWNLOADS_PATH );
		const csvFilePath = `${ DOWNLOADS_PATH }/${ fileNames[ 0 ] }`;

		const fileBuffer = fs.readFileSync( csvFilePath );
		const fileData = fileBuffer.toString( 'utf8' );
		fs.unlinkSync( csvFilePath );

		const tableRowsSelector = await page.$$(
			'.woocommerce-table__table tr'
		);
		const numDepositsOnPage = tableRowsSelector.length - 1; // subtracting the column headers row
		const numDepositsInFile = countLines( fileData ) - 1; // subtracting the column headers row

		expect( numDepositsInFile ).toEqual( numDepositsOnPage );

		const firstDepositDateSelector = await page.$(
			'.woocommerce-table__table tr:nth-child(2) .woocommerce-table__item.date-time'
		);
		const firstDepositDateOnPage = await firstDepositDateSelector.evaluate(
			( node ) => node.innerText
		);
		const firstDepositInFile = getTextAtLine( fileData, 2 );
		const firstDepositDateInFile = firstDepositInFile.split( ',' )[ 1 ];
		const firstDepositFormattedDateInFile = dateI18n(
			'M j, Y',
			moment.utc( Number( firstDepositDateInFile ) ).toISOString(),
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		);

		expect( firstDepositDateOnPage ).toEqual(
			firstDepositFormattedDateInFile
		);
	} );

	it( 'exported data should match the visible rows in Deposit Transactions', async () => {
		await merchantWCP.openDeposits();
		await page.click(
			'.woocommerce-table__table tr:nth-child(2) .woocommerce-table__item.info-button a'
		); // info button of the first deposit
		await page.waitFor( 5000 );

		await page.click( '.woocommerce-table__download-button' );
		await page.waitFor( 5000 );

		const fileNames = fs.readdirSync( DOWNLOADS_PATH );
		const csvFilePath = `${ DOWNLOADS_PATH }/${ fileNames[ 0 ] }`;
		const fileBuffer = fs.readFileSync( csvFilePath );
		const fileData = fileBuffer.toString( 'utf8' );
		fs.unlinkSync( csvFilePath );

		const tableRowsSelector = await page.$$(
			'.woocommerce-table__table tr'
		);
		const numTransactionsOnPage = tableRowsSelector.length - 1; // subtracting the column headers row
		const numTransactionsInFile = countLines( fileData ) - 1; // subtracting the column headers row

		expect( numTransactionsInFile ).toEqual( numTransactionsOnPage );

		const firstTxnCustomerSelector = await page.$(
			'.woocommerce-table__table tr:nth-child(2) .woocommerce-table__item:last-child'
		);
		const firstTxnCustomerOnPage = await firstTxnCustomerSelector.evaluate(
			( node ) => node.innerText
		);
		const firstTxnInFile = getTextAtLine( fileData, 2 );
		const firstTxnCustomerInFile = firstTxnInFile.split( ',' )[ 8 ];

		expect(
			firstTxnCustomerInFile.indexOf( firstTxnCustomerOnPage )
		).not.toBe( -1 );
	} );
} );
