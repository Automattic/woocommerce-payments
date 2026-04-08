/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useEffect, useMemo } from 'react';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { NAMESPACE } from 'wcpay/data/constants';
import PreviewProductImage from 'assets/images/woopay-preview-product.svg?asset';
import VisaIconImage from 'assets/images/woopay-preview-visa.svg?asset';
import WoopayLogoImage from 'assets/images/woopay-preview-logo.svg?asset';
import PaymentCardsImage from 'assets/images/woopay-preview-payment-cards.svg?asset';

import { getCardBorderColor } from './color-utils';

/**
 * Derives inline style objects from a WooPay appearance object.
 * Returns an empty map when appearance is null (unthemed).
 *
 * @param {Object|null} appearance The WooPay appearance object.
 * @return {Object} A map of element keys to inline style objects.
 */
const getThemedStyles = ( appearance ) => {
	if ( ! appearance ) {
		return {};
	}

	const vars = appearance.variables || {};
	const rules = appearance.rules || {};

	const headerBg = rules[ '.Header' ]?.backgroundColor || undefined;
	const cardBorderColor = getCardBorderColor( vars.colorBackground );

	return {
		root: {
			fontFamily: vars.fontFamily || undefined,
		},
		// The area above the store header is visible as a strip — use header
		// background so it blends seamlessly with the header.
		container: {
			backgroundColor: headerBg,
		},
		body: {
			backgroundColor: vars.colorBackground || undefined,
		},
		storeHeader: {
			backgroundColor: headerBg,
		},
		headerText: {
			color: rules[ '.Header' ]?.color || undefined,
			fontFamily: rules[ '.Heading' ]?.fontFamily || undefined,
		},
		chevron: {
			color: rules[ '.Header' ]?.color || undefined,
		},
		sectionHeader: {
			color: rules[ '.Label' ]?.color || undefined,
			fontFamily: rules[ '.Heading' ]?.fontFamily || undefined,
		},
		textBox: {
			color: vars.colorText || undefined,
		},
		card: {
			borderColor: cardBorderColor,
		},
		link: {
			color: rules[ '.Link' ]?.color || undefined,
		},
		footer: {
			backgroundColor: rules[ '.Footer' ]?.backgroundColor || undefined,
			color: rules[ '.Footer' ]?.color || undefined,
		},
		footerGuestText: {
			color: rules[ '.Footer-link' ]?.color || undefined,
		},
	};
};

const VerticalSpacer = ( { height } ) => {
	return <div className="preview-layout__v-spacer" style={ { height } } />;
};

// TODO: Commented out for now. Will be used in a future iteration.
// See https://github.com/Automattic/woopay/issues/2559#issuecomment-2064013672
// const PreviewButton = () => {
// 	return <div className="preview-layout__preview-button">Preview</div>;
// };

const PreviewContainer = ( { height, themedStyle, children } ) => {
	return (
		<div
			className="preview-layout__container"
			style={ { height, ...themedStyle } }
		>
			{ children }
		</div>
	);
};

const BackButton = ( { themedStyle } ) => {
	const strokeColor = themedStyle?.color || '#2C3338';
	return (
		<div className="preview-layout__back-button">
			<svg
				width="24"
				height="24"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path
					d="M14 6.50002L9 12L14 17.5"
					stroke={ strokeColor }
					strokeWidth="1.5"
				/>
			</svg>
			<span
				className="preview-layout__back-button-label"
				style={ themedStyle }
			>
				{ __( 'Return to cart', 'woocommerce-payments' ) }
			</span>
		</div>
	);
};

const StoreHeader = ( { themedStyle, chevronStyle, children } ) => {
	return (
		<div className="preview-layout__store-header" style={ themedStyle }>
			<BackButton themedStyle={ chevronStyle } />
			<div className="preview-layout__store-branding">{ children }</div>
			<div className="preview-layout__store-header-spacer" />
		</div>
	);
};

const PreviewBody = ( { themedStyle, children } ) => {
	return (
		<div className="preview-layout__body" style={ themedStyle }>
			{ children }
		</div>
	);
};

const ColumnsContainer = ( { height, children } ) => {
	return (
		<div className="preview-layout__columns-container" style={ { height } }>
			{ children }
		</div>
	);
};

const LeftColumn = ( { height, children } ) => {
	return (
		<div className="preview-layout__left-column" style={ { height } }>
			{ children }
		</div>
	);
};

const ContactSection = ( { children } ) => {
	return <div className="preview-layout__contact-section">{ children }</div>;
};

const ContactField = ( { children } ) => {
	return <div className="preview-layout__contact-field">{ children }</div>;
};

const RightColumn = ( { height, themedStyle, children } ) => {
	return (
		<div
			className="preview-layout__right-column"
			style={ { height, ...themedStyle } }
		>
			{ children }
		</div>
	);
};

const SectionHeader = ( { children, height, themedStyle } ) => {
	return (
		<div
			className="preview-layout__section-header"
			style={ { height, ...themedStyle } }
		>
			{ children }
		</div>
	);
};

const FieldValue = ( { children, themedStyle } ) => {
	return (
		<div className="preview-layout__field-value" style={ themedStyle }>
			{ children }
		</div>
	);
};

const ChevronDown = () => {
	return <span className="preview-layout__chevron-down">›</span>;
};

const OrderItem = ( {
	name,
	price,
	unitPrice,
	quantity,
	imageSrc,
	themedStyle,
} ) => {
	return (
		<div className="preview-layout__order-item" style={ themedStyle }>
			<div className="preview-layout__order-item-image">
				<img
					src={ imageSrc }
					alt={ name }
					className="preview-layout__order-item-img"
				/>
				<span className="preview-layout__order-item-qty">
					{ quantity }
				</span>
			</div>
			<div className="preview-layout__order-item-details">
				<span className="preview-layout__order-item-name">
					{ name }
				</span>
				{ unitPrice && (
					<span className="preview-layout__order-item-unit-price">
						{ unitPrice }
					</span>
				) }
			</div>
			<span className="preview-layout__order-item-price">{ price }</span>
		</div>
	);
};

const OrderRow = ( { label, value, themedStyle } ) => {
	return (
		<div className="preview-layout__order-row" style={ themedStyle }>
			<span>{ label }</span>
			<span>{ value }</span>
		</div>
	);
};

const PaymentCardIcons = () => {
	return (
		<img
			className="preview-layout__footer-cards"
			src={ PaymentCardsImage }
			alt=""
		/>
	);
};

const PreviewFooter = ( { themedStyle, guestTextStyle } ) => {
	return (
		<div className="preview-layout__footer" style={ themedStyle }>
			<div className="preview-layout__footer-inner">
				<div className="preview-layout__footer-links">
					<span
						className="preview-layout__footer-guest-text"
						style={ guestTextStyle }
					>
						Checkout as guest
					</span>
					<span className="preview-layout__footer-dot">•</span>
					<span>Terms of use</span>
					<span className="preview-layout__footer-dot">•</span>
					<span>Privacy policy</span>
					<span className="preview-layout__footer-dot">•</span>
					<span>Help</span>
				</div>
				<PaymentCardIcons />
			</div>
		</div>
	);
};

const TextBox = ( { children, maxHeight, themedStyle } ) => {
	return (
		<div
			className="preview-layout__text-box"
			style={ { maxHeight, ...themedStyle } }
			dangerouslySetInnerHTML={ {
				__html: children,
			} }
		/>
	);
};

const WooPayLogo = () => {
	return (
		<img
			className="preview-layout__woopay-logo"
			src={ WoopayLogoImage }
			alt="WooPay"
		/>
	);
};

const CheckoutButton = ( { height } ) => {
	return (
		<div className="preview-layout__checkout-button" style={ { height } }>
			{ __( 'Place order', 'woocommerce-payments' ) }
		</div>
	);
};

/**
 * Sanitizes HTML for the preview.
 *
 * @param {string} input The HTML to sanitize.
 * @return {string} The sanitized HTML.
 */
function sanitizeHtmlForPreview( input ) {
	return input.replace( /<\/?([a-zA-Z]+)[^>]*>/g, function (
		fullMatch,
		tagName
	) {
		tagName = tagName.toLowerCase();
		const allowedTags = [ 'a', 'em', 'strong', 'b', 'i' ];
		// Only allow allowedTags.
		if ( ! allowedTags.includes( tagName ) ) {
			return '';
		}

		// 'a' tags are converted to 'span' tags with a class, in the preview.
		if ( tagName === 'a' ) {
			if ( fullMatch.startsWith( '</' ) ) {
				return `</span>`;
			}

			return `<span class="preview-layout__shortcode-link">`;
		}

		// Remaining tags are stripped of attributes, in the preview.
		if ( fullMatch.startsWith( '</' ) ) {
			return `</${ tagName }>`;
		}

		return `<${ tagName }>`;
	} );
}

const ALLOWED_FONT_DOMAINS = [
	'fonts.googleapis.com',
	'fonts.gstatic.com',
	'use.typekit.net',
	'fonts.bunny.net',
	'fonts.wp.com',
];

export default ( {
	storeName,
	storeLogo,
	customMessage,
	appearance,
	fontRules,
	...props
} ) => {
	const { style, ...restProps } = props;

	const themed = useMemo( () => getThemedStyles( appearance ), [
		appearance,
	] );

	// Load merchant font stylesheets from stored font rules.
	useEffect( () => {
		const rules = fontRules || [];
		const links = [];

		rules.forEach( ( rule, index ) => {
			if ( ! rule.cssSrc ) {
				return;
			}
			let validUrl;
			try {
				const url = new URL( rule.cssSrc );
				if (
					url.protocol !== 'https:' ||
					! ALLOWED_FONT_DOMAINS.includes( url.hostname )
				) {
					return;
				}
				validUrl = url.href;
			} catch {
				return;
			}
			const link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = validUrl;
			link.id = `woopay-preview-font-${ index }`;
			document.head.appendChild( link );
			links.push( link );
		} );

		return () => links.forEach( ( link ) => link.remove() );
	}, [ fontRules ] );

	const preparedCustomMessage = useMemo( () => {
		let rawCustomMessage = ( customMessage || '' ).trim();

		if ( rawCustomMessage ) {
			rawCustomMessage = sanitizeHtmlForPreview( rawCustomMessage );
			rawCustomMessage = rawCustomMessage.replace(
				/\[(terms|terms_of_service_link)\]/g,
				'<span class="preview-layout__shortcode-link">Terms of Service</span>'
			);
			rawCustomMessage = rawCustomMessage.replace(
				/\[(privacy_policy|privacy_policy_link)\]/g,
				'<span class="preview-layout__shortcode-link">Privacy Policy</span>'
			);
		}

		return rawCustomMessage;
	}, [ customMessage ] );

	let storeHeader;
	if ( storeLogo ) {
		const storeLogoUrl =
			wcpaySettings.restUrl +
			NAMESPACE.substring( 1 ) +
			'/file/' +
			storeLogo;
		storeHeader = <img src={ storeLogoUrl } alt="Store logo" />;
	} else if ( wcpaySettings?.siteLogoUrl ) {
		storeHeader = (
			<img src={ wcpaySettings?.siteLogoUrl } alt="Store logo" />
		);
	} else {
		storeHeader = (
			<span className="header-text" style={ themed.headerText }>
				{ decodeEntities( storeName ) }
			</span>
		);
	}

	return (
		<div
			className="preview-layout"
			style={ { ...style, ...themed.root } }
			role="img"
			aria-label={ __(
				'WooPay checkout preview',
				'woocommerce-payments'
			) }
			{ ...restProps }
		>
			{
				// TODO: Commented out for now. Will be used in a future iteration.
				// See https://github.com/Automattic/woopay/issues/2559#issuecomment-2064013672
				// <PreviewButton />
			 }
			<PreviewContainer themedStyle={ themed.container }>
				<StoreHeader
					themedStyle={ themed.storeHeader }
					chevronStyle={ themed.chevron }
				>
					{ storeHeader }
				</StoreHeader>
				<PreviewBody themedStyle={ themed.body }>
					<VerticalSpacer height="2rem" />
					<ColumnsContainer>
						<LeftColumn>
							<ContactSection>
								<ContactField>
									<WooPayLogo />
									<FieldValue themedStyle={ themed.textBox }>
										jane@example.com
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										height="0.625rem"
										themedStyle={ themed.sectionHeader }
									>
										{ __(
											'Ship to',
											'woocommerce-payments'
										) }
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										Jane Smith, 123 Main St, San Francisco,
										CA 94105
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										height="0.625rem"
										themedStyle={ themed.sectionHeader }
									>
										{ __(
											'Shipping method',
											'woocommerce-payments'
										) }
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										{ sprintf(
											/* translators: %s: shipping method name */
											__(
												'%s — Free',
												'woocommerce-payments'
											),
											__(
												'Free shipping',
												'woocommerce-payments'
											)
										) }
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										height="0.625rem"
										themedStyle={ themed.sectionHeader }
									>
										{ __(
											'Pay with',
											'woocommerce-payments'
										) }
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										<span className="preview-layout__pay-with-value">
											<img
												className="preview-layout__visa-icon"
												src={ VisaIconImage }
												alt=""
											/>
											Visa ···· 4242 Exp. 12/29
										</span>
									</FieldValue>
								</ContactField>
							</ContactSection>

							<VerticalSpacer height="1.25rem" />
							<CheckoutButton height="1.5rem" />
							{ preparedCustomMessage && (
								<>
									<VerticalSpacer height="0.25rem" />
									<TextBox
										maxHeight="1.5rem"
										themedStyle={ {
											...themed.textBox,
											'--preview-link-color':
												themed.link?.color,
										} }
									>
										{ preparedCustomMessage }
									</TextBox>
								</>
							) }

							<VerticalSpacer height="0.75rem" />
						</LeftColumn>
						<RightColumn themedStyle={ themed.card }>
							<SectionHeader
								height="0.625rem"
								themedStyle={ themed.sectionHeader }
							>
								{ __(
									'Order summary',
									'woocommerce-payments'
								) }
							</SectionHeader>
							<VerticalSpacer height="0.6rem" />
							<div
								className="preview-layout__cart-header"
								style={ themed.textBox }
							>
								<span className="preview-layout__cart-header-text">
									{ sprintf(
										/* translators: %d: number of items in cart */
										__( '%d item', 'woocommerce-payments' ),
										1
									) }
								</span>
								<span
									className="preview-layout__cart-header-toggle"
									style={ themed.link }
								>
									{ __( 'Hide', 'woocommerce-payments' ) }
									<span className="preview-layout__chevron-up">
										›
									</span>
								</span>
							</div>
							<VerticalSpacer height="0.5625rem" />
							<OrderItem
								name="Beanie"
								unitPrice="$ 18.00"
								price="$ 18.00"
								quantity={ 1 }
								imageSrc={ PreviewProductImage }
								themedStyle={ themed.textBox }
							/>
							<VerticalSpacer height="0.75rem" />
							<hr className="preview-layout__hr preview-layout__hr--dotted" />
							<VerticalSpacer height="0.15rem" />
							<div
								className="preview-layout__add-coupon"
								style={ themed.link }
							>
								{ /* Exact coupon-discount icon from WooPay SVG sprite */ }
								<svg
									className="preview-layout__add-coupon-icon"
									viewBox="0 0 24 24"
									fill="none"
								>
									<path
										d="M4.41387 11.8743L11.442 4.84616L18.8667 4.84616L18.8667 12.2708L11.8385 19.299L4.41387 11.8743Z"
										stroke="currentColor"
										strokeWidth="1.5"
									/>
									<circle
										cx="14.667"
										cy="9.04605"
										r="1"
										transform="rotate(45 14.667 9.04605)"
										fill="currentColor"
									/>
								</svg>
								{ __( 'Add a coupon', 'woocommerce-payments' ) }
							</div>
							<VerticalSpacer height="0.108rem" />
							<div
								className="preview-layout__add-coupon"
								style={ themed.link }
							>
								{ /* Exact gift-cards-purple icon from WooPay SVG sprite */ }
								<svg
									className="preview-layout__add-coupon-icon"
									viewBox="0 0 24 24"
									fill="none"
								>
									<rect
										x="-0.75"
										y="-0.75"
										width="9.5"
										height="14.5"
										transform="matrix(3.97376e-08 -1 -1 -4.80825e-08 18.5 18.5)"
										stroke="currentColor"
										strokeWidth="1.5"
									/>
									<path
										fillRule="evenodd"
										clipRule="evenodd"
										d="M13 19L13 9L11.5 9L11.5 19L13 19Z"
										fill="currentColor"
									/>
									<path
										d="M16.5 6.5C16.5 7.4665 15.7165 8.25 14.75 8.25H13V6.5C13 5.5335 13.7835 4.75 14.75 4.75C15.7165 4.75 16.5 5.5335 16.5 6.5Z"
										stroke="currentColor"
										strokeWidth="1.5"
									/>
									<path
										d="M8 6.5C8 7.4665 8.7835 8.25 9.75 8.25H11.5V6.5C11.5 5.5335 10.7165 4.75 9.75 4.75C8.7835 4.75 8 5.5335 8 6.5Z"
										stroke="currentColor"
										strokeWidth="1.5"
									/>
								</svg>
								{ __(
									'Add a gift card',
									'woocommerce-payments'
								) }
							</div>
							<VerticalSpacer height="0.24rem" />
							<OrderRow
								label={ __(
									'Subtotal',
									'woocommerce-payments'
								) }
								value="$ 18.00"
								themedStyle={ themed.textBox }
							/>
							<OrderRow
								label={ __(
									'Shipping',
									'woocommerce-payments'
								) }
								value={ __( 'Free', 'woocommerce-payments' ) }
								themedStyle={ themed.textBox }
							/>
							<VerticalSpacer height="0.5rem" />
							<OrderRow
								label={ __( 'Total', 'woocommerce-payments' ) }
								value="$ 18.00"
								themedStyle={ {
									...themed.textBox,
									fontWeight: 600,
								} }
							/>
							<VerticalSpacer height="0.25rem" />
						</RightColumn>
					</ColumnsContainer>
					<VerticalSpacer height="1.15rem" />
				</PreviewBody>
			</PreviewContainer>
			<PreviewFooter
				themedStyle={ themed.footer }
				guestTextStyle={ themed.footerGuestText }
			/>
		</div>
	);
};
