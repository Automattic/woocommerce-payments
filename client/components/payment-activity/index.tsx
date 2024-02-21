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
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { DateRange } from '@woocommerce/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */
import EmptyStateAsset from 'assets/images/empty-activity-state.svg?asset';

import './style.scss';

const PaymentActivity: React.FC = () => {
	const {
		accountDefaultCurrency,
		transactions_data: { tpv },
	} = wcpaySettings;
	const [ before, setBefore ] = useState( moment() );
	const [ after, setAfter ] = useState(
		moment().subtract( 7, 'days' ).startOf( 'day' )
	);
	const [ rangeLabel, setRangeLabel ] = useState( 'Last 7 days' );

	const afterText = after ? moment( after ).format( 'MM/DD/YYYY' ) : 'From';
	const beforeText = before ? moment( before ).format( 'MM/DD/YYYY' ) : 'To';

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
						renderContent={ () => (
							<div className="wcpay-payments-activity__date__picker__wrapper">
								<ul className="wcpay-payments-activity__date__picker__ranges">
									<Button
										onClick={ () => {
											setAfter(
												moment().startOf( 'day' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Today' );
										} }
									>
										Today
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment()
													.subtract( 7, 'days' )
													.startOf( 'day' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Last 7 days' );
										} }
									>
										Last 7 days
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment()
													.subtract( 28, 'days' )
													.startOf( 'day' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Last 4 weeks' );
										} }
									>
										Last 4 weeks
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment()
													.subtract( 3, 'months' )
													.startOf( 'day' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Last 3 months' );
										} }
									>
										Last 3 months
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment()
													.subtract( 1, 'years' )
													.startOf( 'day' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Last 12 months' );
										} }
									>
										Last 12 months
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment().startOf( 'month' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Month to date' );
										} }
									>
										Month to date
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment().startOf( 'quarter' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Quarter to date' );
										} }
									>
										Quarter to date
									</Button>
									<Button
										onClick={ () => {
											setAfter(
												moment().startOf( 'year' )
											);
											setBefore(
												moment().endOf( 'day' )
											);
											setRangeLabel( 'Year to date' );
										} }
									>
										Year to date
									</Button>
									{ /* TODO: Need to implement all time */ }
									{ /* <Button>All time</Button> */ }
								</ul>
								<div className="wcpay-payments-activity__date__picker">
									<DateRange
										after={ after || moment() }
										afterText={ afterText }
										before={ before || moment() }
										beforeText={ beforeText }
										onUpdate={ ( data ) => {
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
											// not a future date
											moment().isBefore(
												moment( date ),
												'date'
											)
										}
									/>
								</div>
							</div>
						) }
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
				{ /* This should be replaced with the correct graphs */ }
				{ tpv !== 0 && <>You have some money</> }
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
