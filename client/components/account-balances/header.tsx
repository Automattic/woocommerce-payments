/**
 * External dependencies
 */
import React from 'react';
import { CardHeader, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getGreeting } from './utils';
import { useCurrentWpUser } from './hooks';
import wooPaymentsLogo from '../../../assets/images/woopayments.svg';
import './style.scss';

/**
 * Renders the header of the account's balances card.
 *
 * @return {JSX.Element} Rendered element with the account balances card header.
 */
const AccountBalancesHeader: React.FC = () => {
	const { displayName } = useCurrentWpUser();
	const greeting = getGreeting( displayName );

	return (
		<CardHeader>
			<Flex
				align="center"
				justify="space-between"
				className="wcpay-account-balances__header"
			>
				<FlexItem className="wcpay-account-balances__header__greeting">
					{ greeting }
				</FlexItem>
				<FlexItem>
					<img
						className="wcpay-account-balances__header__greeting__logo"
						src={ wooPaymentsLogo }
						alt="Woo Payments logo"
						width={ 107 }
					/>
				</FlexItem>
			</Flex>
		</CardHeader>
	);
};

export default AccountBalancesHeader;
