/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import { tip } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import InlineNotice from 'components/inline-notice';

/**
 * Renders a notice informing the user that their deposits are suspended.
 */
export const SuspendedDepositNotice: React.FC = () => {
	return (
		<InlineNotice
			className="wcpay-deposits-overview__suspended-notice"
			icon
			isDismissible={ false }
			status="warning"
		>
			{ interpolateComponents( {
				/** translators: {{strong}}: placeholders are opening and closing strong tags. {{suspendLink}}: is a <a> link element */
				mixedString: __(
					'Your deposits are {{strong}}temporarily suspended{{/strong}}. {{suspendLink}}Learn more{{/suspendLink}}',
					'woocommerce-payments'
				),
				components: {
					strong: <strong />,
					suspendLink: (
						<Link
							href={
								'https://woocommerce.com/document/woopayments/deposits/why-deposits-suspended/'
							}
						/>
					),
				},
			} ) }
		</InlineNotice>
	);
};

/**
 * Renders a notice informing the user that the next deposit will include funds from a loan payout.
 */
export const DepositIncludesLoanPayoutNotice: React.FC = () => (
	<InlineNotice icon status="warning" isDismissible={ false }>
		{ interpolateComponents( {
			mixedString: __(
				'This deposit will include funds from your WooCommerce Capital loan. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
				'woocommerce-payments'
			),
			components: {
				learnMoreLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={
							'https://woocommerce.com/document/woopayments/stripe-capital/overview/'
						}
						target="_blank"
						rel="noreferrer"
					/>
				),
			},
		} ) }
	</InlineNotice>
);

/**
 * Renders a notice informing the user of the new account waiting period.
 */
export const NewAccountWaitingPeriodNotice: React.FC = () => (
	<InlineNotice
		status="warning"
		icon
		className="new-account-waiting-period-notice"
		isDismissible={ false }
	>
		{ interpolateComponents( {
			mixedString: __(
				'Your first deposit is held for seven business days. {{whyLink}}Why?{{/whyLink}}',
				'woocommerce-payments'
			),
			components: {
				whyLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noopener noreferrer"
						href="https://woocommerce.com/document/woopayments/deposits/deposit-schedule/#new-accounts"
					/>
				),
			},
		} ) }
	</InlineNotice>
);

/**
 * Renders a notice informing the user of the time it takes for deposits to appear in their bank account.
 */
export const DepositTransitDaysNotice: React.FC = () => (
	<InlineNotice
		icon={ tip }
		children={
			'It may take 1-3 business days for deposits to reach your bank account.'
		}
		isDismissible={ false }
	/>
);

/**
 * Renders a notice informing the user that their deposits may be paused due to a negative balance.
 */
export const NegativeBalanceDepositsPausedNotice: React.FC = () => (
	<InlineNotice
		status="warning"
		icon
		className="negative-balance-deposits-paused-notice"
		isDismissible={ false }
	>
		{ interpolateComponents( {
			mixedString: sprintf(
				/* translators: %s: WooPayments */
				__(
					'Deposits may be interrupted while your %s balance remains negative. {{whyLink}}Why?{{/whyLink}}',
					'woocommerce-payments'
				),
				'WooPayments'
			),
			components: {
				whyLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noopener noreferrer"
						href="https://woocommerce.com/document/woopayments/fees-and-debits/account-showing-negative-balance/"
					/>
				),
			},
		} ) }
	</InlineNotice>
);
