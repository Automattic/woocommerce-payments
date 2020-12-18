/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { Card } from '@woocommerce/components';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import moment from 'moment';
import { find, get } from 'lodash';

/**
 * Internal dependencies.
 */
import { getChargeAmounts, getChargeStatus } from 'utils/charge';
import PaymentStatusChip from 'components/payment-status-chip';
import PaymentMethodDetails from 'components/payment-method-details';
import HorizontalList from 'components/horizontal-list';
import Loadable, { LoadableBlock } from 'components/loadable';
import riskMappings from 'components/risk-level/strings';
import OrderLink from 'components/order-link';
import './style.scss';

const currencyData = getCurrencyData();

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {String} currencyCode Currency code
 *
 * @return {Currency} Currency object
 */
const getCurrency = ( currencyCode ) => {
	const currency = find( currencyData, { code: currencyCode.toUpperCase() } );
	if ( currency ) {
		return new Currency( currency );
	}
	window.console.warn(
		sprintf(
			'"%s" is not supported by @woocommerce/currency, falling back to "USD"',
			currencyCode
		)
	);
	return new Currency();
};

const placeholderValues = {
	net: 0,
	fee: 0,
	refunded: null,
};

const composePaymentSummaryItems = ( { charge } ) =>
	[
		{
			title: __( 'Date', 'woocommerce-payments' ),
			content: charge.created
				? dateI18n(
						'M j, Y, g:ia',
						moment( charge.created * 1000 ).toISOString()
				  )
				: '–',
		},
		{
			title: __( 'Customer', 'woocommerce-payments' ),
			content: get( charge, 'billing_details.name' ) || '–',
		},
		{
			title: __( 'Order', 'woocommerce-payments' ),
			content: <OrderLink order={ charge.order } />,
		},
		wcpaySettings.isSubscriptionsActive && {
			title: __( 'Subscription', 'woocommerce-payments' ),
			content:
				charge.order && charge.order.subscriptions.length ? (
					charge.order.subscriptions.map(
						( subscription, i, all ) => [
							<OrderLink key={ i } order={ subscription } />,
							i !== all.length - 1 && ', ',
						]
					)
				) : (
					<OrderLink />
				),
		},
		{
			title: __( 'Payment method', 'woocommerce-payments' ),
			content: (
				<PaymentMethodDetails
					payment={ charge.payment_method_details }
				/>
			),
		},
		{
			title: __( 'Risk evaluation', 'woocommerce-payments' ),
			content: riskMappings[ get( charge, 'outcome.risk_level' ) ] || '–',
		},
	].filter( Boolean );

const PaymentDetailsSummary = ( { charge = {}, isLoading } ) => {
	const formatCurrency = ( amount, currency ) => {
		const currencyCode = currency || charge.currency || 'USD';
		const zeroDecimalCurrencies = [
			'bif',
			'clp',
			'djf',
			'gnf',
			'jpy',
			'kmf',
			'krw',
			'mga',
			'pyg',
			'rwf',
			'ugx',
			'vnd',
			'vuv',
			'xaf',
			'xof',
			'xpf',
		];
		const isZeroDecimalCurrency = zeroDecimalCurrencies.includes(
			currencyCode.toLowerCase()
		);
		if ( isZeroDecimalCurrency ) {
			amount *= 100;
		}

		return getCurrency( currencyCode ).formatCurrency( amount / 100 );
	};
	const { net, fee, refunded } = charge.amount
		? getChargeAmounts( charge )
		: placeholderValues;

	return (
		<Card className="payment-details-summary-details">
			<div className="payment-details-summary">
				<div className="payment-details-summary__section">
					<p className="payment-details-summary__amount">
						<Loadable
							isLoading={ isLoading }
							placeholder="Amount placeholder"
						>
							{ formatCurrency(
								charge.amount || 0,
								charge.currency
							) }
							<span className="payment-details-summary__amount-currency">
								{ charge.currency || 'usd' }
							</span>
							<PaymentStatusChip
								status={ getChargeStatus( charge ) }
							/>
						</Loadable>
					</p>
					<div className="payment-details-summary__breakdown">
						{ refunded ? (
							<p>
								{ `${ __(
									'Refunded',
									'woocommerce-payments'
								) }: ` }
								{ formatCurrency(
									-refunded,
									charge.currency || 'usd'
								) }
							</p>
						) : (
							''
						) }
						<p>
							<Loadable
								isLoading={ isLoading }
								placeholder="Fee amount"
							>
								{ `${ __( 'Fee', 'woocommerce-payments' ) }: ` }
								{ formatCurrency(
									-fee,
									charge.currency || 'usd'
								) }
							</Loadable>
						</p>
						<p>
							<Loadable
								isLoading={ isLoading }
								placeholder="Net amount"
							>
								{ `${ __( 'Net', 'woocommerce-payments' ) }: ` }
								{ formatCurrency(
									net,
									charge.currency || 'usd'
								) }
							</Loadable>
						</p>
					</div>
				</div>
				<div className="payment-details-summary__section">
					<div className="payment-details-summary__id">
						<Loadable
							isLoading={ isLoading }
							placeholder="Payment ID: ch_xxxxxxxxxxxxxxxxxxxxxxxx"
						>
							{ `${ __(
								'Payment ID',
								'woocommerce-payments'
							) }: ` }
							{ charge.id }
						</Loadable>
					</div>
				</div>
			</div>
			<hr className="full-width" />
			<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
				<HorizontalList
					items={ composePaymentSummaryItems( { charge } ) }
				/>
			</LoadableBlock>
		</Card>
	);
};

export default PaymentDetailsSummary;
