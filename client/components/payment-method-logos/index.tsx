/**
 * External dependencies
 */
import React, { useState, useEffect, useRef } from 'react';
import { Popover } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import WooPay from 'assets/images/payment-method-icons/woopay.svg?asset';
import ApplePay from 'assets/images/payment-method-icons/applepay.svg?asset';
import AfterPay from 'assets/images/payment-method-icons/afterpay.svg?asset';
import Affirm from 'assets/images/payment-method-icons/affirm.svg?asset';
import Klarna from 'assets/images/payment-method-icons/klarna.svg?asset';
import Jcb from 'assets/images/payment-method-icons/jcb.svg?asset';
import GooglePay from 'assets/images/payment-method-icons/gpay.svg?asset';
import Cartebancaire from 'assets/images/cards/cartes_bancaires.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import Diners from 'assets/images/cards/diners.svg?asset';
import Eftpos from 'assets/images/cards/eftpos.svg?asset';
import Ideal from 'assets/images/payment-methods/ideal.svg?asset';
import Bancontact from 'assets/images/payment-methods/bancontact.svg?asset';
import Eps from 'assets/images/payment-methods/eps.svg?asset';
import Becs from 'assets/images/payment-methods/becs.svg?asset';
import Przelewy24 from 'assets/images/payment-methods/przelewy24.svg?asset';
import './style.scss';

const PaymentMethods = [
	{
		name: 'visa',
		component: Visa,
	},
	{
		name: 'mastercard',
		component: Mastercard,
	},
	{
		name: 'amex',
		component: Amex,
	},
	{
		name: 'discover',
		component: Discover,
	},
	{
		name: 'woopay',
		component: WooPay,
	},
	{
		name: 'applepay',
		component: ApplePay,
	},
	{
		name: 'googlepay',
		component: GooglePay,
	},
	{
		name: 'afterpay',
		component: AfterPay,
	},
	{
		name: 'affirm',
		component: Affirm,
	},
	{
		name: 'klarna',
		component: Klarna,
	},
	{
		name: 'cartebancaire',
		component: Cartebancaire,
	},
	{
		name: 'unionpay',
		component: UnionPay,
	},
	{
		name: 'diners',
		component: Diners,
	},
	{
		name: 'eftpos',
		component: Eftpos,
	},
	{
		name: 'jcb',
		component: Jcb,
	},
	{
		name: 'bancontact',
		component: Bancontact,
	},
	{
		name: 'becs',
		component: Becs,
	},
	{
		name: 'eps',
		component: Eps,
	},
	{
		name: 'ideal',
		component: Ideal,
	},
	{
		name: 'przelewy24',
		component: Przelewy24,
	},
];

export const WooPaymentsMethodsLogos: React.VFC< {
	maxElements: number;
	isWooPayEligible: boolean;
} > = ( {
	maxElements = 10,
	/**
	 * Whether the store (location) is eligible for WooPay.
	 * Based on this we will include or not the WooPay logo in the list.
	 */
	isWooPayEligible = false,
} ) => {
	const totalPaymentMethods = 20;
	const [ maxShownElements, setMaxShownElements ] = useState( maxElements );
	const [ isPopoverVisible, setIsPopoverVisible ] = useState( false );
	const popoverTimeoutRef = useRef< NodeJS.Timeout >();

	// Reduce the total number of payment methods by one if the store is not eligible for WooPay.
	const maxSupportedPaymentMethods = isWooPayEligible
		? totalPaymentMethods
		: totalPaymentMethods - 1;

	const getMaxShownElements = ( maxElementsNumber: number ) => {
		if ( ! isWooPayEligible ) {
			return maxElementsNumber + 1;
		}

		return maxElementsNumber;
	};

	useEffect( () => {
		const updateMaxElements = () => {
			if ( window.innerWidth <= 480 ) {
				setMaxShownElements( 5 );
			} else if ( window.innerWidth <= 768 ) {
				setMaxShownElements( 7 );
			} else {
				setMaxShownElements( maxElements );
			}
		};

		updateMaxElements();
		window.addEventListener( 'resize', updateMaxElements );
	}, [ maxElements ] );

	const visiblePaymentMethods = PaymentMethods.slice(
		0,
		getMaxShownElements( maxShownElements )
	).filter( ( pm ) => isWooPayEligible || pm.name !== 'woopay' );

	const hiddenPaymentMethods = PaymentMethods.slice(
		getMaxShownElements( maxShownElements )
	).filter( ( pm ) => isWooPayEligible || pm.name !== 'woopay' );

	const showPopover = () => {
		if ( popoverTimeoutRef.current ) {
			clearTimeout( popoverTimeoutRef.current );
		}
		setIsPopoverVisible( true );
	};

	const hidePopover = () => {
		// Add a delay before hiding the popover
		popoverTimeoutRef.current = setTimeout( () => {
			setIsPopoverVisible( false );
		}, 300 ); // 300ms delay
	};

	// Cleanup timeout on unmount
	useEffect( () => {
		return () => {
			if ( popoverTimeoutRef.current ) {
				clearTimeout( popoverTimeoutRef.current );
			}
		};
	}, [] );

	return (
		<div className="connect-account-page__payment-methods--logos">
			{ visiblePaymentMethods
				.slice( 0, maxShownElements )
				.map( ( pm ) => {
					return (
						<img
							key={ pm.name }
							alt={ pm.name }
							src={ pm.component }
							width={ 38 }
							height={ 24 }
						/>
					);
				} ) }
			{ maxShownElements < maxSupportedPaymentMethods && (
				<div
					className="connect-account-page__payment-methods--logos-count"
					onClick={ () => setIsPopoverVisible( ! isPopoverVisible ) }
					onMouseEnter={ showPopover }
					onMouseLeave={ hidePopover }
					role="button"
					tabIndex={ 0 }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' || event.key === ' ' ) {
							setIsPopoverVisible( ! isPopoverVisible );
						}
					} }
				>
					+ { maxSupportedPaymentMethods - maxShownElements }
					{ isPopoverVisible && (
						<Popover
							position="bottom left"
							noArrow={ true }
							onClose={ () => setIsPopoverVisible( false ) }
							onMouseEnter={ showPopover }
							onMouseLeave={ hidePopover }
						>
							<div
								className="connect-account-page__payment-methods--logos connect-account-page__payment-methods--logos-inside-popover"
								onMouseEnter={ showPopover }
								onMouseLeave={ hidePopover }
							>
								{ hiddenPaymentMethods.map( ( pm ) => {
									return (
										<img
											key={ pm.name }
											alt={ pm.name }
											src={ pm.component }
											width={ 38 }
											height={ 24 }
										/>
									);
								} ) }
							</div>
						</Popover>
					) }
				</div>
			) }
		</div>
	);
};
