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
 * Renders a welcome card header with a greeting and the Woo Payments logo.
 *
 * @return {JSX.Element} Rendered element with the account balances card header.
 */
const Welcome: React.FC = () => {
	const { user } = useCurrentWpUser();
	const greeting = getGreeting( user?.first_name );

	return (
		<CardHeader className="wcpay-welcome">
			<Flex
				align="center"
				justify="space-between"
				className="wcpay-welcome__flex"
			>
				<FlexItem className="wcpay-welcome__flex__greeting">
					{ greeting }
				</FlexItem>
				<FlexItem>
					<img
						className="wcpay-welcome__flex__logo"
						src={ wooPaymentsLogo }
						alt="Woo Payments logo"
						width={ 107 }
					/>
				</FlexItem>
			</Flex>
		</CardHeader>
	);
};

export default Welcome;
