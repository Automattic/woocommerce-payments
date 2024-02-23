/**
 * External dependencies
 */
import * as React from 'react';
import { useState } from 'react';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Dropdown,
	DropdownMenu,
} from '@wordpress/components';
import { check } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { DateRange } from '@woocommerce/components';
import interpolateComponents from '@automattic/interpolate-components';
import { ranges } from './constants';

const getLabelFromRange = ( range: string | null ) => {
	switch ( range ) {
		case ranges.TODAY_VALUE:
			return ranges.TODAY_LABEL;
		case ranges.SEVEN_DAYS_VALUE:
			return ranges.SEVEN_DAYS_LABEL;
		case ranges.FOUR_WEEKS_VALUE:
			return ranges.FOUR_WEEKS_LABEL;
		case ranges.THREE_MONTHS_VALUE:
			return ranges.THREE_MONTHS_LABEL;
		case ranges.TWELVE_MONTHS_VALUE:
			return ranges.TWELVE_MONTHS_LABEL;
		case ranges.MONTH_TO_DATE_VALUE:
			return ranges.MONTH_TO_DATE_LABEL;
		case ranges.QUARTER_TO_DATE_VALUE:
			return ranges.QUARTER_TO_DATE_LABEL;
		case ranges.YEAR_TO_DATE_VALUE:
			return ranges.YEAR_TO_DATE_LABEL;
		case ranges.CUSTOM_VALUE:
			return ranges.CUSTOM_LABEL;
	}
};
/**
 * Internal dependencies.
 */
import EmptyStateAsset from 'assets/images/empty-activity-state.svg?asset';

import './style.scss';

const PaymentActivity: React.FC = () => {
	const {
		accountDefaultCurrency,
		transactionsData: { tpv },
	} = wcpaySettings;
	const [ before, setBefore ] = useState( moment() );
	const [ after, setAfter ] = useState(
		moment().subtract( 7, 'days' ).startOf( 'day' )
	);
	const [ selectedRange, setSelectedRange ] = useState< string | null >(
		ranges.SEVEN_DAYS_VALUE
	);

	// These are not used, nor displayed (via CSS). But necessary for the DateRange component to work.
	const afterText = after ? moment( after ).format( 'DD/MM/YYYY' ) : 'From';
	const beforeText = before ? moment( before ).format( 'DD/MM/YYYY' ) : 'To';

	const rangeLabel = getLabelFromRange( selectedRange );

	// Empty Icon to display next to ranges when not selected.
	const emptyIcon = <div style={ { width: '24px' } }></div>;

	const renderContent = ( { onToggle }: { onToggle: () => void } ) => {
		return (
			<div className="wcpay-payments-activity__date__picker__wrapper">
				<div className="wcpay-payments-activity__date__picker__ranges">
					<Button
						icon={
							selectedRange === ranges.TODAY_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter( moment().startOf( 'day' ) );
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.TODAY_VALUE );
							onToggle();
						} }
					>
						{ ranges.TODAY_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.SEVEN_DAYS_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter(
								moment().subtract( 7, 'days' ).startOf( 'day' )
							);
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.SEVEN_DAYS_VALUE );
							onToggle();
						} }
					>
						{ ranges.SEVEN_DAYS_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.FOUR_WEEKS_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter(
								moment().subtract( 28, 'days' ).startOf( 'day' )
							);
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.FOUR_WEEKS_VALUE );
							onToggle();
						} }
					>
						{ ranges.FOUR_WEEKS_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.THREE_MONTHS_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter(
								moment()
									.subtract( 3, 'months' )
									.startOf( 'day' )
							);
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.THREE_MONTHS_VALUE );
							onToggle();
						} }
					>
						{ ranges.THREE_MONTHS_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.TWELVE_MONTHS_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter(
								moment().subtract( 1, 'years' ).startOf( 'day' )
							);
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.TWELVE_MONTHS_VALUE );
							onToggle();
						} }
					>
						{ ranges.TWELVE_MONTHS_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.MONTH_TO_DATE_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter( moment().startOf( 'month' ) );
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.MONTH_TO_DATE_VALUE );
							onToggle();
						} }
					>
						{ ranges.MONTH_TO_DATE_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.QUARTER_TO_DATE_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter( moment().startOf( 'quarter' ) );
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.QUARTER_TO_DATE_VALUE );
							onToggle();
						} }
					>
						{ ranges.QUARTER_TO_DATE_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.YEAR_TO_DATE_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter( moment().startOf( 'year' ) );
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.YEAR_TO_DATE_VALUE );
							onToggle();
						} }
					>
						{ ranges.YEAR_TO_DATE_LABEL }
					</Button>
					<Button
						icon={
							selectedRange === ranges.CUSTOM_VALUE
								? check
								: emptyIcon
						}
						onClick={ () => {
							setAfter(
								moment().subtract( 7, 'days' ).startOf( 'day' )
							);
							setBefore( moment().endOf( 'day' ) );
							setSelectedRange( ranges.CUSTOM_VALUE );
						} }
					>
						{ ranges.CUSTOM_LABEL }
					</Button>
				</div>
				{ selectedRange === ranges.CUSTOM_VALUE && (
					<div className="wcpay-payments-activity__date__picker">
						<DateRange
							after={ after || moment() }
							// afterText is hidden via CSS but necessary for the component to render/work as expected
							afterText={ afterText }
							before={ before || moment() }
							// beforeText is hidden via CSS but necessary for the component to render/work as expected
							beforeText={ beforeText }
							onUpdate={ ( data ) => {
								setSelectedRange( ranges.CUSTOM_VALUE );
								if ( data.after ) {
									setAfter( data.after );
								}

								if ( data.before ) {
									setBefore( data.before );
								}
							} }
							shortDateFormat="DD/MM/YYYY"
							focusedInput="endDate"
							isInvalidDate={ ( date ) =>
								// Can not select a future date
								moment().isBefore( moment( date ), 'date' )
							}
						/>
						<div
							style={ {
								textAlign: 'end',
							} }
						>
							<Button
								onClick={ () => {
									setSelectedRange( ranges.SEVEN_DAYS_VALUE );

									setAfter(
										moment()
											.subtract( 7, 'days' )
											.startOf( 'day' )
									);
									setBefore( moment().endOf( 'day' ) );

									onToggle();
								} }
							>
								Reset
							</Button>
							<Button
								variant="primary"
								onClick={ () => {
									// TODO: Need to implement logic for applying changes here
									onToggle();
								} }
							>
								Apply
							</Button>
						</div>
					</div>
				) }
			</div>
		);
	};

	return (
		<Card className="">
			<CardHeader>
				{ __( 'You payment activity', 'woocommerce-payments' ) }
			</CardHeader>
			<CardBody className="wcpay-payments-activity__card__body">
				<div>
					<DropdownMenu
						label="Select a currency"
						icon={
							<Button variant="secondary">
								Currency:{ ' ' }
								<span className="wcpay-payments-activity__label__span">
									{ accountDefaultCurrency.toUpperCase() }
								</span>
							</Button>
						}
						controls={ [
							{
								title: 'EUR',
								icon: <></>,
							},
						] }
					/>
					<Dropdown
						className="my-container-class-name"
						contentClassName="my-dropdown-content-classname"
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								variant="secondary"
								onClick={ onToggle }
								aria-expanded={ isOpen }
							>
								{ rangeLabel }:
								<span className="wcpay-payments-activity__label__span">
									{ after.format( 'MMM D' ) } -{ ' ' }
									{ before.format( 'MMM D, YYYY' ) }
								</span>
							</Button>
						) }
						renderContent={ renderContent }
					/>
				</div>

				{ tpv === 0 && (
					<>
						<img src={ EmptyStateAsset } alt="" />
						<p>
							{ interpolateComponents( {
								mixedString: sprintf(
									__(
										'{{strong}}No payments… yet!{{/strong}}',
										'woocommerce-payments'
									),
									'WooPayments'
								),
								components: {
									strong: <strong />,
								},
							} ) }
						</p>
						<p>
							{ __(
								"Once your first order comes in, you'll start seeing your payment activity right here.",
								'woocommerce-payments'
							) }
						</p>
					</>
				) }
				{ /* TODO: This should be replaced with the correct graphs */ }
				{ tpv !== 0 && <>You have some money</> }
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
