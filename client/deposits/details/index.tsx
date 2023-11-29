/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
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
import { useDeposit } from 'wcpay/data';
import { displayStatus } from '../strings';
import TransactionsList from 'transactions/list';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import { CachedDeposit } from 'wcpay/types/deposits';
import { TestModeNotice } from 'wcpay/components/test-mode-notice';
import './style.scss';

const Status = ( { status }: { status: string } ): JSX.Element => (
	// Re-purpose order status indicator for deposit status.
	<OrderStatus order={ { status } } orderStatusMap={ displayStatus } />
);

// Custom SummaryNumber with custom value className reusing @woocommerce/components styles.
const SummaryItem = ( {
	label,
	value,
	valueClass,
	detail,
}: {
	label: string;
	value: string | JSX.Element;
	valueClass?: string | false;
	detail?: string;
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

interface DepositOverviewProps {
	deposit: CachedDeposit;
}

export const DepositOverview: React.FC< DepositOverviewProps > = ( {
	deposit,
} ) => {
	const depositDateLabel = deposit.automatic
		? __( 'Deposit date', 'woocommerce-payments' )
		: __( 'Instant deposit date', 'woocommerce-payments' );

	const depositDateItem = (
		<SummaryItem
			key="depositDate"
			label={
				`${ depositDateLabel }: ` +
				dateI18n(
					'M j, Y',
					moment.utc( deposit.date ).toISOString(),
					true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
				)
			}
			value={ <Status status={ deposit.status } /> }
			detail={ deposit.bankAccount }
		/>
	);

	return (
		<div className="wcpay-deposit-overview">
			{ deposit.automatic ? (
				<Card className="wcpay-deposit-automatic">
					<ul>
						{ depositDateItem }
						<li className="wcpay-deposit-amount">
							{ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
						</li>
					</ul>
				</Card>
			) : (
				<SummaryList
					label={ __( 'Deposit overview', 'woocommerce-payments' ) }
				>
					{ () => [
						depositDateItem,
						<SummaryItem
							key="depositAmount"
							label={ __(
								'Deposit amount',
								'woocommerce-payments'
							) }
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
							label={ __(
								'Net deposit amount',
								'woocommerce-payments'
							) }
							value={ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
							valueClass="wcpay-deposit-net"
						/>,
					] }
				</SummaryList>
			) }
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
			<TestModeNotice currentPage="deposits" isDetailsView={ true } />
			<ErrorBoundary>
				{ isLoading ? (
					<SummaryListPlaceholder numberOfItems={ 2 } />
				) : (
					<DepositOverview deposit={ deposit } />
				) }
			</ErrorBoundary>

			<ErrorBoundary>
				{ isInstantDeposit ? (
					// If instant deposit, show a message instead of the transactions list.
					// Matching the components used in @woocommerce/components TableCard for consistent UI.
					<Card>
						<CardHeader>
							<Text size={ 16 } weight={ 600 } as="h2">
								{ __(
									'Deposit transactions',
									'woocommerce-payments'
								) }
							</Text>
						</CardHeader>
						<CardBody className="wcpay-deposit-overview--instant__transactions-list-message">
							{ interpolateComponents( {
								/* Translators: {{learnMoreLink}} is a link element (<a/>). */
								mixedString: __(
									`We're unable to show transaction history on instant deposits. {{learnMoreLink}}Learn more{{/learnMoreLink}}`,
									'woocommerce-payments'
								),
								components: {
									learnMoreLink: (
										<ExternalLink href="https://woo.com/document/woopayments/deposits/instant-deposits/#transactions" />
									),
								},
							} ) }
						</CardBody>
					</Card>
				) : (
					<TransactionsList depositId={ depositId } />
				) }
			</ErrorBoundary>
		</Page>
	);
};

export default DepositDetails;
