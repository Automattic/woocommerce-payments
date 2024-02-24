/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import { documentationUrls, fundLabelStrings } from './strings';
import InlineNotice from 'components/inline-notice';

type BalanceTooltipProps = {
	label: string;
	content: React.ReactNode;
	maxWidth?: string | undefined;
};

type TotalBalanceTooltipProps = {
	balance: number;
};

type AvailableBalanceTooltipProps = {
	balance: number;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	label,
	content,
	maxWidth,
} ) => {
	return (
		<ClickTooltip
			content={ content }
			className="wcpay-account-balances__balances__item__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ label }
			maxWidth={ maxWidth }
		/>
	);
};

export const TotalBalanceTooltip: React.FC< TotalBalanceTooltipProps > = ( {
	balance,
} ) => {
	return (
		<BalanceTooltip
			label={ `${ fundLabelStrings.total } tooltip` }
			maxWidth={ balance < 0 ? undefined : '315px' }
			content={
				<>
					<>
						{ interpolateComponents( {
							mixedString: __(
								'{{bold}}Total balance{{/bold}} combines both pending funds (transactions under processing) and available funds (ready for deposit). {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							components: {
								bold: <b />,
								learnMoreLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										rel="external noopener noreferrer"
										target="_blank"
										href={
											documentationUrls.depositSchedule
										}
									/>
								),
							},
						} ) }
					</>
					<InlineNotice
						className="wcpay-account-balances__balances-total-balance-tooltip-notice"
						isDismissible={ false }
					>
						{ __(
							'Total balance = Available funds + Pending funds',
							'woocommerce-payments'
						) }
					</InlineNotice>
					<>
						{ balance < 0 &&
							interpolateComponents( {
								mixedString: __(
									'{{learnMoreLink}}Learn more{{/learnMoreLink}} about why your account balance may be negative.',
									'woocommerce-payments'
								),
								components: {
									learnMoreLink: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											rel="external noopener noreferrer"
											target="_blank"
											href={
												documentationUrls.negativeBalance
											}
										/>
									),
								},
							} ) }
					</>
				</>
			}
		/>
	);
};

export const AvailableBalanceTooltip: React.FC< AvailableBalanceTooltipProps > = ( {
	balance,
} ) => {
	return (
		<BalanceTooltip
			label={ `${ fundLabelStrings.available } tooltip` }
			content={
				<>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								'{{bold}}Available funds{{/bold}} have completed processing and are ready to be deposited into your bank account. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							components: {
								bold: <b />,
								learnMoreLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										rel="external noopener noreferrer"
										target="_blank"
										href={
											documentationUrls.depositSchedule
										}
									/>
								),
							},
						} ) }
					</p>
					<p>
						{ balance < 0 &&
							interpolateComponents( {
								mixedString: __(
									'{{learnMoreLink}}Learn more{{/learnMoreLink}} about why your account balance may be negative.',
									'woocommerce-payments'
								),
								components: {
									learnMoreLink: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											rel="external noopener noreferrer"
											target="_blank"
											href={
												documentationUrls.negativeBalance
											}
										/>
									),
								},
							} ) }
					</p>
				</>
			}
		/>
	);
};
