/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import classNames from 'classnames';
import { render, fireEvent, screen, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentGatewaysConfirmation from '..';

const PaymentGatewaysTableMock = ( { isWCPayEnabled } ) => (
	<table className="wc_gateways">
		<tbody>
			<tr data-gateway_id="woocommerce_payments">
				<td>WooCommerce Payments</td>
				<td>
					<a
						className="wc-payment-gateway-method-toggle-enabled"
						href="/wcpay"
					>
						<span
							className={ classNames(
								// when the user has interacted with the toggles, IRL it can happen that
								// both classes `woocommerce-input-toggle--disabled` and `woocommerce-input-toggle--enabled`
								// are applied to the element.
								'woocommerce-input-toggle woocommerce-input-toggle--enabled',
								{
									'woocommerce-input-toggle--disabled': ! isWCPayEnabled,
								}
							) }
							aria-label={ `The "WooCommerce Payments" payment method is ${
								isWCPayEnabled ? 'enabled' : 'disabled'
							}` }
						>
							{ isWCPayEnabled ? 'Yes' : 'No' }
						</span>
					</a>
				</td>
			</tr>
			<tr data-gateway_id="bacs">
				<td>Direct Bank Transfer</td>
				<td>
					<a
						className="wc-payment-gateway-method-toggle-enabled"
						href="/bacs"
					>
						<span className="woocommerce-input-toggle woocommerce-input-toggle--enabled">
							Yes
						</span>
					</a>
				</td>
			</tr>
		</tbody>
	</table>
);

const WooCommerceAdminEventListenerMock = ( { onClick } ) => {
	useEffect( () => {
		const container = document.querySelector( '.wc_gateways' );
		const handler = ( event ) => {
			if (
				event.target.className.includes(
					'wc-payment-gateway-method-toggle-enabled'
				)
			) {
				onClick( event );
			}
		};

		container.addEventListener( 'click', handler );

		return () => {
			container.removeEventListener( 'click', handler );
		};
	}, [ onClick ] );

	return null;
};

describe( 'PaymentGatewaysConfirmation', () => {
	it( 'should ask confirmation to disable WC Pay', async () => {
		const WCAdminClickHandler = jest.fn();

		render(
			<>
				<PaymentGatewaysTableMock isWCPayEnabled />
				<PaymentGatewaysConfirmation />
				<WooCommerceAdminEventListenerMock
					onClick={ WCAdminClickHandler }
				/>
			</>
		);

		const WCPayToggle = document.querySelector(
			'[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);

		expect( WCAdminClickHandler ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Are you sure you want to disable?' )
		).not.toBeInTheDocument();

		act( () => {
			fireEvent.click( WCPayToggle );
		} );

		expect( WCAdminClickHandler ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Are you sure you want to disable?' )
		).toBeInTheDocument();

		act( () => {
			fireEvent.click( screen.queryByText( 'Yes, disable' ) );
		} );

		expect( WCAdminClickHandler ).toHaveBeenCalled();
	} );

	it( 'should not ask confirmation to disable another payment method', async () => {
		const WCAdminClickHandler = jest.fn();

		render(
			<>
				<PaymentGatewaysTableMock isWCPayEnabled />
				<PaymentGatewaysConfirmation />
				<WooCommerceAdminEventListenerMock
					onClick={ WCAdminClickHandler }
				/>
			</>
		);

		act( () => {
			fireEvent.click(
				document.querySelector(
					'[data-gateway_id="bacs"] .wc-payment-gateway-method-toggle-enabled'
				)
			);
		} );

		expect( WCAdminClickHandler ).toHaveBeenCalled();
		expect(
			screen.queryByText( 'Are you sure you want to disable?' )
		).not.toBeInTheDocument();
	} );
} );
