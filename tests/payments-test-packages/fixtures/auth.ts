/**
 * External dependencies
 */
import {
	test as base,
	Browser,
	BrowserContext,
	Page,
	StorageState,
} from '@playwright/test';
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */

import { config } from '../config/default';

export type Role = 'admin' | 'customer' | 'editor';

type RoleConfig = {
	login: ( page: Page ) => Promise< void >;
};

const roles: Record< Role, RoleConfig > = {
	admin: {
		login: ( page ) => qit.loginAsAdmin( page ),
	},
	customer: {
		login: async ( page ) => {
			const { username, password } = config.users.customer;
			await qit.loginAs( page, username, password );
		},
	},
	editor: {
		login: async ( page ) => {
			const { username, password } = config.users.editor;
			await qit.loginAs( page, username, password );
		},
	},
};

const stateCache = new Map< Role, Promise< StorageState > >();

const getState = ( browser: Browser, role: Role ) => {
	if ( ! stateCache.has( role ) ) {
		stateCache.set(
			role,
			( async () => {
				const context = await browser.newContext();
				const page = await context.newPage();
				await roles[ role ].login( page );
				await page.waitForLoadState( 'domcontentloaded' );
				const state = await context.storageState();
				await context.close();
				return state;
			} )()
		);
	}

	return stateCache.get( role )!;
};

type Fixtures = {
	adminContext: BrowserContext;
	adminPage: Page;
	customerContext: BrowserContext;
	customerPage: Page;
	editorContext: BrowserContext;
	editorPage: Page;
};

export const test = base.extend< Fixtures >( {
	adminContext: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: await getState( browser, 'admin' ),
		} );
		await use( context );
		await context.close();
	},
	adminPage: async ( { adminContext }, use ) => {
		const page = await adminContext.newPage();
		await use( page );
	},
	customerContext: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: await getState( browser, 'customer' ),
		} );
		await use( context );
		await context.close();
	},
	customerPage: async ( { customerContext }, use ) => {
		const page = await customerContext.newPage();
		await use( page );
	},
	editorContext: async ( { browser }, use ) => {
		const context = await browser.newContext( {
			storageState: await getState( browser, 'editor' ),
		} );
		await use( context );
		await context.close();
	},
	editorPage: async ( { editorContext }, use ) => {
		const page = await editorContext.newPage();
		await use( page );
	},
} );

export const expect = test.expect;

export const getAuthState = ( browser: Browser, role: Role ) =>
	getState( browser, role );
