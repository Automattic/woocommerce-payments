/**
 * External dependencies
 */
import * as React from 'react';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import { formatExplicitCurrency } from 'utils/currency';
import Loadable from 'components/loadable';
import { useActiveLoanSummary } from 'wcpay/data';

import './style.scss';

const Block = ( {
	title,
	children,
}: {
	title: React.ReactNode;
	children: React.ReactNode;
} ): JSX.Element => (
	<FlexBlock className="wcpay-loan-summary-block">
		<div className="wcpay-loan-summary-block__title">{ title }</div>
		<div className="wcpay-loan-summary-block__value">{ children }</div>
	</FlexBlock>
);

const ActiveLoanSummaryLoading = (): JSX.Element => {
	return (
		<Card>
			<CardHeader size="medium" className="wcpay-loan-summary-header">
				<FlexItem>
					{ __( 'Active loan overview', 'woocommerce-payments' ) }
				</FlexItem>
			</CardHeader>
			<CardBody className="wcpay-loan-summary-body">
				<Flex align="normal" className="wcpay-loan-summary-row">
					<Block
						title={ __( 'Total repaid', 'woocommerce-payments' ) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Total repaid placeholder"
						/>
					</Block>
					<Block
						title={ __(
							'Repaid this period',
							'woocommerce-payments'
						) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Repaid this period placeholder"
						/>
					</Block>
				</Flex>
				<Flex
					align="normal"
					className="wcpay-loan-summary-row is-bottom-row"
				>
					<Block
						title={ __( 'Loan disbursed', 'woocommerce-payments' ) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Date disbursed"
						/>
					</Block>
					<Block
						title={ __( 'Loan amount', 'woocommerce-payments' ) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Loan amount"
						/>
					</Block>
					<Block title={ __( 'Fixed fee', 'woocommerce-payments' ) }>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Fixed fee"
						/>
					</Block>
					<Block
						title={ __( 'Withhold rate', 'woocommerce-payments' ) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Rate"
						/>
					</Block>
					<Block
						title={ __( 'First paydown', 'woocommerce-payments' ) }
					>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="First paydown"
						/>
					</Block>
				</Flex>
			</CardBody>
		</Card>
	);
};

const ActiveLoanSummary = (): JSX.Element => {
	const { summary, isLoading } = useActiveLoanSummary();

	if ( isLoading || ! summary ) {
		return <ActiveLoanSummaryLoading />;
	}

	const { details } = summary;

	return (
		<Card>
			<CardHeader size="medium" className="wcpay-loan-summary-header">
				<FlexItem>
					{ __( 'Active loan overview', 'woocommerce-payments' ) }
				</FlexItem>
				<FlexItem>
					<Button
						isLink
						href="javascript:alert('Not implemented yet')"
					>
						{ __( 'View transactions', 'woocommerce-payments' ) }
					</Button>
				</FlexItem>
			</CardHeader>
			<CardBody className="wcpay-loan-summary-body">
				<Flex align="normal" className="wcpay-loan-summary-row">
					<Block
						title={ __( 'Total repaid', 'woocommerce-payments' ) }
					>
						{ createInterpolateElement(
							sprintf(
								__(
									'<big>%s</big> of %s',
									'woocommerce-payments'
								),
								formatExplicitCurrency(
									details.paid_amount,
									details.currency
								),
								formatExplicitCurrency(
									details.fee_amount + details.advance_amount,
									details.currency
								)
							),
							{
								big: <span className="is-big" />,
							}
						) }
					</Block>
					<Block
						title={ sprintf(
							__(
								'Repaid this period (until %s)',
								'woocommerce-payments'
							),
							dateI18n(
								'M j, Y',
								new Date(
									details.current_repayment_interval.due_at *
										1000
								)
							)
						) }
					>
						{ createInterpolateElement(
							sprintf(
								__(
									'<big>%s</big> of %s minimum',
									'woocommerce-payments'
								),
								formatExplicitCurrency(
									details.current_repayment_interval
										.paid_amount,
									details.currency
								),
								formatExplicitCurrency(
									details.current_repayment_interval
										.paid_amount +
										details.current_repayment_interval
											.remaining_amount,
									details.currency
								)
							),
							{
								big: <span className="is-big" />,
							}
						) }
					</Block>
				</Flex>
				<Flex
					align="normal"
					className="wcpay-loan-summary-row is-bottom-row"
				>
					<Block
						title={ __( 'Loan disbursed', 'woocommerce-payments' ) }
					>
						{ dateI18n(
							'M j, Y',
							new Date( details.advance_paid_out_at * 1000 )
						) }
					</Block>
					<Block
						title={ __( 'Loan amount', 'woocommerce-payments' ) }
					>
						{ formatExplicitCurrency(
							details.advance_amount,
							details.currency
						) }
					</Block>
					<Block title={ __( 'Fixed fee', 'woocommerce-payments' ) }>
						{ formatExplicitCurrency(
							details.fee_amount,
							details.currency
						) }
					</Block>
					<Block
						title={ __( 'Withhold rate', 'woocommerce-payments' ) }
					>
						{ details.withhold_rate * 100 }%
					</Block>
					<Block
						title={ __( 'First paydown', 'woocommerce-payments' ) }
					>
						{ dateI18n(
							'M j, Y',
							new Date( details.repayments_begin_at * 1000 )
						) }
					</Block>
				</Flex>
			</CardBody>
		</Card>
	);
};

export default ActiveLoanSummary;
