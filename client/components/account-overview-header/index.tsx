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
import wooPaymentsLogo from 'assets/images/woopayments.svg?asset';
import './style.scss';

/**
 * Renders the header of the account's balances card.
 *
 * @return {JSX.Element} Rendered element with the account balances card header.
 */
const AccountOverviewHeader: React.FC = () => {
	const { user } = useCurrentWpUser();
	const greeting = getGreeting( user?.first_name );

	return (
		<CardHeader className="wcpay-account-overview-header">
			<Flex
				align="center"
				justify="space-between"
				className="wcpay-account-overview-header__flex"
			>
				<FlexItem className="wcpay-account-overview-header__flex__greeting">
					{ greeting }
				</FlexItem>
				<FlexItem>
					<img
						className="wcpay-account-overview-header__flex__logo"
						src={ wooPaymentsLogo }
						alt="Woo Payments logo"
						width={ 107 }
					/>
				</FlexItem>
			</Flex>
		</CardHeader>
	);
};

export default AccountOverviewHeader;
