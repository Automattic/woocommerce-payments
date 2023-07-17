/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import WooIcon from 'assets/images/payment-methods/woo.svg?asset';
import interpolateComponents from '@automattic/interpolate-components';
import { getPaymentMethodSettingsUrl } from '../../utils';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { HoverTooltip } from 'components/tooltip';
import {
	useEnabledPaymentMethodIds,
	useWooPayEnabledSettings,
	useWooPayShowIncompatibilityNotice,
} from 'wcpay/data';
import WCPaySettingsContext from '../wcpay-settings-context';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import WooPayIncompatibilityNotice from '../settings-warnings/incompatibility-notice';

const WooPayExpressCheckoutItem = () => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

	const showIncompatibilityNotice = useWooPayShowIncompatibilityNotice();

	const isStripeLinkEnabled = enabledMethodIds.includes( 'link' );

	const {
		featureFlags: { woopay: isWooPayFeatureFlagEnabled },
	} = useContext( WCPaySettingsContext );

	return (
		<>
			{ isWooPayFeatureFlagEnabled && (
				<li className="express-checkout" id="express-checkouts-woopay">
					<div className="express-checkout__row">
						<div className="express-checkout__checkbox">
							{ isStripeLinkEnabled ? (
								<HoverTooltip
									content={ __(
										'To enable WooPay, you must first disable Link by Stripe.',
										'woocommerce-payments'
									) }
								>
									<div className="loadable-checkbox__icon">
										<NoticeOutlineIcon
											style={ {
												color: '#F0B849',
												fill: 'currentColor',
												marginBottom: '-5px',
												marginRight: '16px',
											} }
											size={ 20 }
										/>
										<div
											className="loadable-checkbox__icon-warning"
											data-testid="loadable-checkbox-icon-warning"
										>
											<VisuallyHidden>
												{ __(
													'WooPay cannot be enabled at checkout. Click to expand.',
													'woocommerce-payments'
												) }
											</VisuallyHidden>
										</div>
									</div>
								</HoverTooltip>
							) : (
								<CheckboxControl
									label={ __(
										'WooPay',
										'woocommerce-payments'
									) }
									checked={ isWooPayEnabled }
									onChange={ updateIsWooPayEnabled }
								/>
							) }
						</div>
						<div className="express-checkout__icon">
							<img src={ WooIcon } alt="WooPay" />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __( 'WooPay', 'woocommerce-payments' ) }
							</div>
							<div className="express-checkout__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									isWooPayEnabled
										? __(
												'Boost conversion and customer loyalty by offering a single click, secure way to pay.',
												'woocommerce-payments'
										  )
										: interpolateComponents( {
												mixedString: __(
													/* eslint-disable-next-line max-len */
													'Boost conversion and customer loyalty by offering a single click, secure way to pay. ' +
														'In order to use {{wooPayLink}}WooPay{{/wooPayLink}}, you must agree to our ' +
														'{{tosLink}}WooCommerce Terms of Service{{/tosLink}} ' +
														'and {{privacyLink}}Privacy Policy{{/privacyLink}}. ' +
														'{{trackingLink}}Click here{{/trackingLink}} to learn more about the ' +
														'data you will be sharing and opt-out options.',
													'woocommerce-payments'
												),
												components: {
													wooPayLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://woocommerce.com/document/woopay-merchant-documentation/"
														/>
													),
													tosLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://wordpress.com/tos/"
														/>
													),
													privacyLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://automattic.com/privacy/"
														/>
													),
													trackingLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://woocommerce.com/usage-tracking/"
														/>
													),
												},
										  } )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
						<div className="express-checkout__link">
							<a href={ getPaymentMethodSettingsUrl( 'woopay' ) }>
								{ __( 'Customize', 'woocommerce-payments' ) }
							</a>
						</div>
					</div>
					{ showIncompatibilityNotice && (
						<WooPayIncompatibilityNotice />
					) }
				</li>
			) }
		</>
	);
};

export default WooPayExpressCheckoutItem;
