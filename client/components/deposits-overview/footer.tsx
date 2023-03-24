/**
 * External dependencies
 */
import * as React from 'react';
import { CardFooter, Button, Flex } from '@wordpress/components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { getAdminUrl } from 'wcpay/utils';
import strings from './strings';

/**
 * Renders the footer of the deposits overview card.
 *
 * @return {JSX.Element} Rendered footer of the deposits overview card.
 */
const DepositsOverviewFooter: React.FC = () => {
	// The URL to the deposits list table.
	const depositListTableUrl = getAdminUrl( {
		page: 'wc-admin',
		path: '/payments/deposits',
	} );

	// The URL to the deposit schedule settings page.
	const depositScheduleUrl =
		getAdminUrl( {
			page: 'wc-settings',
			tab: 'checkout',
			section: 'woocommerce_payments',
		} ) + '#deposit-schedule';

	return (
		<CardFooter className="wcpay-deposits-overview__footer">
			<Flex align="center" justify="flex-start">
				<Button isSecondary={ true } href={ depositListTableUrl }>
					{ strings.viewAllDeposits }
				</Button>
				<Link type="wp-admin" href={ depositScheduleUrl }>
					{ strings.changeDepositSchedule }
				</Link>
			</Flex>
		</CardFooter>
	);
};

export default DepositsOverviewFooter;
