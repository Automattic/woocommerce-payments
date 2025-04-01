/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	// @ts-expect-error: Suppressing Module '"@wordpress/components"' has no exported member '__experimentalText'.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- used by TableCard component which we replicate here.
	__experimentalText as Text,
} from '@wordpress/components';
import {
	SummaryListPlaceholder,
	SummaryList,
	OrderStatus,
} from '@woocommerce/components';
import interpolateComponents from '@automattic/interpolate-components';
import classNames from 'classnames';

/**
 * Internal dependencies.
 */
import type { CachedDeposit } from 'types/deposits';
import { useDeposit } from 'data';
import TransactionsList from 'transactions/list';
import { CopyButton } from 'components/copy-button';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice } from 'components/test-mode-notice';
import BannerNotice from 'components/banner-notice';
import InlineNotice from 'components/inline-notice';
import {
	formatCurrency,
	formatExplicitCurrency,
} from 'multi-currency/interface/functions';
import { depositStatusLabels, payoutFailureMessages } from '../strings';
import './style.scss';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { MaybeShowMerchantFeedbackPrompt } from 'wcpay/merchant-feedback-prompt';

/**
 * Renders the deposit status indicator UI, re-purposing the OrderStatus component from @woocommerce/components.
 */
const DepositStatusIndicator: React.FC< {
	deposit: Pick< CachedDeposit, 'status' | 'type' >;
} > = ( { deposit } ) => {
	let displayStatusMap = depositStatusLabels;

	// Withdrawals are displayed as 'Deducted' instead of 'Paid' when the status is 'paid'.
	if ( deposit.type === 'withdrawal' ) {
		displayStatusMap = {
			...displayStatusMap,
			paid: displayStatusMap.deducted,
		};
	}

	return (
		<OrderStatus
			order={ { status: deposit.status } }
			orderStatusMap={ displayStatusMap }
		/>
	);
};

interface SummaryItemProps {
	label: string;
	value: string | JSX.Element;
	valueClass?: string | false;
	detail?: string | JSX.Element;
}

/**
 * A custom SummaryNumber with custom value className, reusing @woocommerce/components styles.
 */
const SummaryItem: React.FC< SummaryItemProps > = ( {
	label,
	value,
	valueClass,
	detail,
} ) => (
	<li className="woocommerce-summary__item-container">
		<div className="woocommerce-summary__item">
			<div className="woocommerce-summary__item-label">{ label }</div>
			<div className="woocommerce-summary__item-data">
				<div
					className={ classNames(
						'woocommerce-summary__item-value',
						valueClass
					) }
				>
					{ value }
				</div>
			</div>
			{ detail && (
				<div className="wcpay-summary__item-detail">{ detail }</div>
			) }
		</div>
	</li>
);

interface DepositDateItemProps {
	deposit: CachedDeposit;
}

const DepositDateItem: React.FC< DepositDateItemProps > = ( { deposit } ) => {
	let depositDateLabel = __( 'Payout date', 'woocommerce-payments' );
	if ( ! deposit.automatic ) {
		depositDateLabel = __( 'Instant payout date', 'woocommerce-payments' );
	}
	if ( deposit.type === 'withdrawal' ) {
		depositDateLabel = __( 'Withdrawal date', 'woocommerce-payments' );
	}

	return (
		<SummaryItem
			key="depositDate"
			label={
				`${ depositDateLabel }: ` +
				formatDateTimeFromString( deposit.date )
			}
			value={ <DepositStatusIndicator deposit={ deposit } /> }
		/>
	);
};
interface DepositOverviewProps {
	deposit: CachedDeposit | undefined;
}

export const DepositOverview: React.FC< DepositOverviewProps > = ( {
	deposit,
} ) => {
	if ( ! deposit ) {
		return (
			<InlineNotice icon status="error" isDismissible={ false }>
				{ __(
					`The deposit you are looking for cannot be found.`,
					'woocommerce-payments'
				) }
			</InlineNotice>
		);
	}

	const isWithdrawal = deposit.type === 'withdrawal';

	return (
		<div className="wcpay-deposit-overview">
			{ deposit.automatic ? (
				<Card className="wcpay-deposit-automatic">
					<ul>
						<DepositDateItem deposit={ deposit } />
						<li className="wcpay-deposit-amount">
							{ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
						</li>
					</ul>
				</Card>
			) : (
				<SummaryList // For instant deposits only
					label={
						isWithdrawal
							? __(
									'Withdrawal overview',
									'woocommerce-payments'
							  )
							: __( 'Payout overview', 'woocommerce-payments' )
					}
				>
					{ () => [
						<DepositDateItem key="dateItem" deposit={ deposit } />,
						<SummaryItem
							key="depositAmount"
							label={
								isWithdrawal
									? __(
											'Withdrawal amount',
											'woocommerce-payments'
									  )
									: __(
											'Payout amount',
											'woocommerce-payments'
									  )
							}
							value={ formatExplicitCurrency(
								deposit.amount + deposit.fee,
								deposit.currency
							) }
						/>,
						<SummaryItem
							key="depositFees"
							label={ sprintf(
								/* translators: %s - amount representing the fee percentage */
								__( '%s service fee', 'woocommerce-payments' ),
								`${ deposit.fee_percentage }%`
							) }
							value={ formatCurrency(
								deposit.fee,
								deposit.currency
							) }
							valueClass={
								0 < deposit.fee && 'wcpay-deposit-fee'
							}
						/>,
						<SummaryItem
							key="netDepositAmount"
							label={
								isWithdrawal
									? __(
											'Net withdrawal amount',
											'woocommerce-payments'
									  )
									: __(
											'Net payout amount',
											'woocommerce-payments'
									  )
							}
							value={ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
							valueClass="wcpay-deposit-net"
						/>,
					] }
				</SummaryList>
			) }
			{ deposit.status === 'failed' && (
				<BannerNotice
					status="error"
					isDismissible={ false }
					key="payout-failure-notice"
				>
					<strong>
						{ __( 'Failure reason: ', 'woocommerce-payments' ) }
					</strong>
					{ payoutFailureMessages[ deposit.failure_code ] ||
						deposit.failure_message ||
						__( 'Unknown', 'woocommerce-payments' ) }
				</BannerNotice>
			) }
			<Card>
				<CardHeader>
					<Text size={ 16 } weight={ 600 }>
						{ isWithdrawal
							? __( 'Withdrawal details', 'woocommerce-payments' )
							: __( 'Payout details', 'woocommerce-payments' ) }
					</Text>
				</CardHeader>
				<CardBody>
					<div className="woopayments-payout-details-header">
						<div className="woopayments-payout-details-header__item">
							<h2>
								{ __( 'Bank account', 'woocommerce-payments' ) }
							</h2>
							<div className="woopayments-payout-details-header__value">
								{ deposit.bankAccount }
							</div>
						</div>
						<div className="woopayments-payout-details-header__item">
							<h2>
								{ __(
									'Bank reference ID',
									'woocommerce-payments'
								) }
							</h2>
							<div className="woopayments-payout-details-header__value">
								{ deposit.bank_reference_key ? (
									<>
										<span className="woopayments-payout-details-header__bank-reference-id">
											{ deposit.bank_reference_key }
										</span>
										<CopyButton
											textToCopy={
												deposit.bank_reference_key
											}
											label={ __(
												'Copy bank reference ID to clipboard',
												'woocommerce-payments'
											) }
										/>
									</>
								) : (
									<div className="woopayments-payout-details-header__value">
										{ __(
											'Not available',
											'woocommerce-payments'
										) }
									</div>
								) }
							</div>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

interface DepositDetailsProps {
	query: {
		id: string;
	};
}

export const DepositDetails: React.FC< DepositDetailsProps > = ( {
	query: { id: depositId },
} ) => {
	const { deposit, isLoading } = useDeposit( depositId );

	const isInstantDeposit = ! isLoading && deposit && ! deposit.automatic;

	return (
		<Page>
			<MaybeShowMerchantFeedbackPrompt />
			<TestModeNotice currentPage="deposits" isDetailsView={ true } />
			<ErrorBoundary>
				{ isLoading ? (
					<SummaryListPlaceholder numberOfItems={ 2 } />
				) : (
					<DepositOverview deposit={ deposit } />
				) }
			</ErrorBoundary>

			{ deposit && (
				<ErrorBoundary>
					{ isInstantDeposit ? (
						// If instant deposit, show a message instead of the transactions list.
						// Matching the components used in @woocommerce/components TableCard for consistent UI.
						<Card>
							<CardHeader>
								<Text size={ 16 } weight={ 600 } as="h2">
									{ __(
										'Payout transactions',
										'woocommerce-payments'
									) }
								</Text>
							</CardHeader>
							<CardBody className="wcpay-deposit-overview--instant__transactions-list-message">
								{ interpolateComponents( {
									/* Translators: {{learnMoreLink}} is a link element (<a/>). */
									mixedString: __(
										`We're unable to show transaction history on instant payouts. {{learnMoreLink}}Learn more{{/learnMoreLink}}`,
										'woocommerce-payments'
									),
									components: {
										learnMoreLink: (
											<ExternalLink href="https://woocommerce.com/document/woopayments/payouts/instant-payouts/#transactions" />
										),
									},
								} ) }
							</CardBody>
						</Card>
					) : (
						<TransactionsList depositId={ depositId } />
					) }
				</ErrorBoundary>
			) }
		</Page>
	);
};

export default DepositDetails;
