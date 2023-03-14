/**
 * External dependencies
 */
import React from 'react';
import { CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getGreeting } from './utils';
import { useCurrentWpUser } from './hooks';

/**
 * Renders the header of the account's balances card.
 *
 * @return {JSX.Element} Rendered element with the account balances card header.
 */
const AccountBalancesHeader: React.FC = () => {
	const { displayName } = useCurrentWpUser();
	const greeting = getGreeting( displayName );

	return <CardHeader>{ greeting }</CardHeader>;
};

export default AccountBalancesHeader;
