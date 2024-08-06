/**
 * External dependencies
 */
import getAccounts from '../requests/accounts.js';

export default function testUserFlow() {
	const accountId = '1';
	getAccounts( accountId );
}
