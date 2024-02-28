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

type TotalBalanceTooltipProps = {
	balance: number;
};

type AvailableBalanceTooltipProps = {
	balance: number;
};

export const TotalBalanceTooltip: React.FC< TotalBalanceTooltipProps > = ( {
	balance,
} ) => {
	const isBalanceNegative = balance < 0;
	return (
		<ClickTooltip
			className="wcpay-account-balances__balances__item__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ `${ fundLabelStrings.total } tooltip` }
			maxWidth={ '315px' } // So that tooltip is wide enough and the content in the inline notice is not wrapped.
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
						{ isBalanceNegative &&
							interpolateComponents( {
								mixedString: __(
									'Negative account balance? {{discoverWhyLink}}Discover why.{{/discoverWhyLink}}',
									'woocommerce-payments'
								),
								components: {
									discoverWhyLink: (
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
	const isBalanceNegative = balance < 0;
	return (
		<ClickTooltip
			className="wcpay-account-balances__balances__item__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ `${ fundLabelStrings.available } tooltip` }
			maxWidth={ isBalanceNegative ? '280px' : undefined } // So that the negative balance sentence is not wrapped and looks like the design.
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
						{ isBalanceNegative &&
							interpolateComponents( {
								mixedString: __(
									'Negative account balance? {{discoverWhyLink}}Discover why.{{/discoverWhyLink}}',
									'woocommerce-payments'
								),
								components: {
									discoverWhyLink: (
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
