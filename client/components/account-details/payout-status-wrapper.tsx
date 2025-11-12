/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Flex, FlexBlock, FlexItem, ExternalLink } from '@wordpress/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import Chip from 'wcpay/components/chip';
import { ClickTooltip } from 'wcpay/components/tooltip';
import { AccountDetailsData } from 'wcpay/types/account/account-details';
import { getChipTypeFromColor } from './utils';

const PayoutStatus: React.FC< {
	payoutStatus: AccountDetailsData[ 'payout_status' ];
} > = ( { payoutStatus } ) => {
	const chipType = getChipTypeFromColor( payoutStatus.background_color );

	return (
		<Flex align="center" gap={ 0 } justify="flex-start">
			<Chip type={ chipType } message={ payoutStatus.text } />
			{ payoutStatus.popover && (
				<ClickTooltip
					className={ 'payout-click-tooltip' }
					buttonIcon={ <HelpOutlineIcon /> }
					buttonLabel={ __(
						'More information about payout status',
						'woocommerce-payments'
					) }
					buttonSize={ 24 }
					maxWidth={ '300px' }
					content={
						<div>
							{ payoutStatus.popover.text }
							{ payoutStatus.popover.cta_text &&
								payoutStatus.popover.cta_link && (
									<>
										{ ' ' }
										<ExternalLink
											className={
												'payout-tooltip-external-link'
											}
											href={
												payoutStatus.popover.cta_link
											}
										>
											{ payoutStatus.popover.cta_text }
										</ExternalLink>
									</>
								) }
						</div>
					}
				/>
			) }
		</Flex>
	);
};

interface PayoutStatusWrapperProps {
	payoutStatus: AccountDetailsData[ 'payout_status' ];
}

const PayoutStatusWrapper: React.FC< PayoutStatusWrapperProps > = ( {
	payoutStatus,
} ) => {
	return (
		<Flex
			direction="row"
			align="center"
			justify="left"
			gap={ 3 }
			className="woopayments-account-details__payout-status-item"
		>
			<FlexItem className="item-label">
				{ __( 'Payouts:', 'woocommerce-payments' ) }
			</FlexItem>
			<FlexBlock className="item-value">
				<PayoutStatus payoutStatus={ payoutStatus } />
			</FlexBlock>
		</Flex>
	);
};

export default PayoutStatusWrapper;
export { PayoutStatus };
