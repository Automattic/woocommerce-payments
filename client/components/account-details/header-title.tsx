/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, FlexBlock, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Chip from 'wcpay/components/chip';
import { recordEvent } from 'wcpay/tracks';
import { AccountDetailsData } from 'wcpay/types/account/account-details';
import { getChipTypeFromColor } from './utils';

interface HeaderTitleProps {
	accountStatus: AccountDetailsData[ 'account_status' ];
	accountLink?: string | null;
}

const HeaderTitle: React.FC< HeaderTitleProps > = ( {
	accountStatus,
	accountLink,
} ) => {
	return (
		<>
			<FlexItem className="account-details">
				{ __( 'Account details', 'woocommerce-payments' ) }
			</FlexItem>
			<FlexBlock className="account-status">
				<Chip
					message={ accountStatus.text }
					type={ getChipTypeFromColor(
						accountStatus.background_color
					) }
				/>
			</FlexBlock>
			{ accountLink && (
				<FlexItem className="edit-details">
					<Button
						variant="link"
						onClick={ () =>
							recordEvent( 'wcpay_account_details_link_clicked', {
								from: 'WCPAY_ACCOUNT_DETAILS',
								source: 'wcpay-account-details',
							} )
						}
						href={ accountLink }
						target="_blank"
						__next40pxDefaultSize
					>
						{ __( 'Edit details', 'woocommerce-payments' ) }
					</Button>
				</FlexItem>
			) }
		</>
	);
};

export default HeaderTitle;
