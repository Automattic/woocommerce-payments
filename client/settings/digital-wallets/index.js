/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CheckboxControl,
	Animate,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';

const DigitalWallets = () => {
	const [ isEnabled, setIsEnabled ] = useState( false );
	const [ sectionsStatus, setSectionsStatus ] = useState( {
		productPage: false,
		cart: false,
	} );

	const makeHandleSectionStatusChange = ( section ) => ( status ) => {
		setSectionsStatus( ( oldStatuses ) => ( {
			...oldStatuses,
			[ section ]: status,
		} ) );
	};

	return (
		<Card className="digital-wallets">
			<CardBody size="large">
				<CheckboxControl
					checked={ isEnabled }
					onChange={ setIsEnabled }
					label={ __(
						'Enable digital wallets & express payment methods',
						'woocommerce-payments'
					) }
				/>
				<p className="digital-wallets__terms">
					{ interpolateComponents( {
						mixedString: __(
							// eslint-disable-next-line max-len
							"By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, {{appleLink}}Apple{{/appleLink}}, {{googleLink}}Google{{/googleLink}} and {{microsoftLink}}Microsoft{{/microsoftLink}}'s terms of use.",
							'woocommerce-payments'
						),
						components: {
							stripeLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://stripe.com/apple-pay/legal"
								/>
							),
							appleLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
								/>
							),
							googleLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="/TODO-google"
								/>
							),
							microsoftLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="/TODO-microsoft"
								/>
							),
						},
					} ) }
				</p>
				{ isEnabled && (
					<Animate type="appear">
						{ ( { className } ) => (
							<div className={ className }>
								<h4>
									{ __(
										'Show digital wallets & express payment methods on:',
										'woocommerce-payments'
									) }
								</h4>
								<ul>
									<li>
										<CheckboxControl
											label={ __(
												'Checkout',
												'woocommerce-payments'
											) }
											disabled
											checked
										/>
									</li>
									<li>
										<CheckboxControl
											disabled={ ! isEnabled }
											checked={
												sectionsStatus.productPage
											}
											onChange={ makeHandleSectionStatusChange(
												'productPage'
											) }
											label={ __(
												'Product page',
												'woocommerce-payments'
											) }
										/>
									</li>
									<li>
										<CheckboxControl
											disabled={ ! isEnabled }
											checked={ sectionsStatus.cart }
											onChange={ makeHandleSectionStatusChange(
												'cart'
											) }
											label={ __(
												'Cart',
												'woocommerce-payments'
											) }
										/>
									</li>
								</ul>
							</div>
						) }
					</Animate>
				) }
			</CardBody>
			<CardDivider />
			<CardBody>
				<Button isDefault href="/TODO">
					{ __( 'Customize appearance', 'woocommerce-payments' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default DigitalWallets;
