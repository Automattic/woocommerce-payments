/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { decodeEntities } from '@wordpress/html-entities';
import { chevronLeft, Icon } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { NAMESPACE } from 'wcpay/data/constants';

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
		},
		chevron: {
			color: rules[ '.Header' ]?.color || undefined,
		},
		hr: {
			color: vars.colorText ? `${ vars.colorText }33` : undefined,
		},
		sectionHeader: {
			color: rules[ '.Label' ]?.color || undefined,
		},
		loadingBox: {
			backgroundColor: rules[ '.Input' ]?.backgroundColor || undefined,
		},
		textBox: {
			color: vars.colorText || undefined,
		},
		link: {
			color: rules[ '.Link' ]?.color || undefined,
		},
		footer: {
			backgroundColor: rules[ '.Footer' ]?.backgroundColor || undefined,
			color: rules[ '.Footer' ]?.color || undefined,
		},
		footerGuestLink: {
			color: rules[ '.Footer-link' ]?.color || undefined,
		},
		footerLink: {
			color: rules[ '.Footer' ]?.color || undefined,
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

const ChevronLeft = () => {
	return (
		<Icon
			className="preview-layout__chevron-left"
			icon={ chevronLeft }
			size={ 24 }
		/>
	);
};

const StoreHeader = ( { height, variant = 'test', themedStyle, children } ) => {
	return (
		<div
			className="preview-layout__store-header"
			variant={ variant }
			style={ { height, ...themedStyle } }
		>
			{ children }
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

const RightColumn = ( { height, children } ) => {
	return (
		<div className="preview-layout__right-column" style={ { height } }>
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

const OrderItem = ( { name, price, themedStyle } ) => {
	return (
		<div className="preview-layout__order-item" style={ themedStyle }>
			<div className="preview-layout__order-item-box" />
			<span className="preview-layout__order-item-name">{ name }</span>
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
		<div className="preview-layout__footer-cards">
			{ /* Visa, Mastercard, Discover, Diners, Amex, JCB, UnionPay */ }
			<svg
				width="200"
				height="14"
				viewBox="0 0 284 19"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				{ /* Visa */ }
				<rect
					x="0.03"
					y="0.5"
					width="28.5"
					height="18"
					rx="1.5"
					fill="white"
				/>
				<rect
					x="0.4"
					y="0.88"
					width="27.75"
					height="17.25"
					rx="1.12"
					stroke="black"
					strokeOpacity="0.25"
					strokeWidth="0.75"
				/>
				<path
					d="M12.79 12.76H11.07L12.15 6.46H13.87L12.79 12.76Z"
					fill="#1C34C3"
				/>
				<path
					d="M19.02 6.62C18.68 6.49 18.15 6.35 17.48 6.35C15.78 6.35 14.59 7.21 14.58 8.43C14.57 9.34 15.44 9.84 16.09 10.14C16.75 10.45 16.98 10.65 16.98 10.93C16.97 11.35 16.44 11.54 15.95 11.54C15.26 11.54 14.89 11.44 14.33 11.21L14.11 11.11L13.87 12.52C14.27 12.7 15.01 12.85 15.78 12.86C17.59 12.86 18.76 12.01 18.78 10.71C18.78 9.99 18.32 9.44 17.33 8.99C16.73 8.7 16.36 8.51 16.36 8.21C16.37 7.95 16.67 7.67 17.35 7.67C17.91 7.66 18.32 7.78 18.63 7.91L18.79 7.98L19.02 6.62Z"
					fill="#1C34C3"
				/>
				<path
					d="M22.1 6.47H23.43L24.82 12.76H23.23C23.23 12.76 23.07 12.04 23.02 11.82H20.81L20.45 12.76H18.65L21.2 6.99C21.38 6.58 21.69 6.47 22.1 6.47ZM22 8.77L21.31 10.53H22.74L22.34 8.71L22.22 8.17L22 8.77Z"
					fill="#1C34C3"
				/>
				<path
					d="M3.62 6.47H6.39C6.76 6.48 7.07 6.59 7.17 6.99L7.77 9.89L7.95 10.76L9.64 6.47H11.45L8.75 12.76H6.93L5.4 7.28C4.87 6.99 4.27 6.76 3.59 6.59L3.62 6.47Z"
					fill="#1C34C3"
				/>
				{ /* Mastercard */ }
				<rect
					x="36.53"
					y="0.5"
					width="28.5"
					height="18"
					rx="1.5"
					fill="white"
				/>
				<rect
					x="36.9"
					y="0.88"
					width="27.75"
					height="17.25"
					rx="1.12"
					stroke="black"
					strokeOpacity="0.25"
					strokeWidth="0.75"
				/>
				<circle cx="47.26" cy="9.56" r="5.91" fill="#EA001B" />
				<circle cx="54.46" cy="9.56" r="5.91" fill="#F79F1A" />
				<path
					d="M50.86 4.91C49.5 5.99 48.62 7.68 48.62 9.56C48.62 11.45 49.5 13.13 50.86 14.22C52.22 13.13 53.09 11.45 53.09 9.56C53.09 7.68 52.22 5.99 50.86 4.91Z"
					fill="#FF5F01"
				/>
				{ /* Amex */ }
				<rect
					x="146.02"
					y="0.5"
					width="28.5"
					height="18"
					rx="1.5"
					fill="white"
				/>
				<rect
					x="146.39"
					y="0.88"
					width="27.75"
					height="17.25"
					rx="1.12"
					stroke="black"
					strokeOpacity="0.25"
					strokeWidth="0.75"
				/>
				<path
					d="M170.94 4.93L171.52 3.36L174.02 3.35V0.5L145.52 0.51V18.5L174.02 18.49V15.65L171.67 15.66L170.75 14.58L169.79 15.66H162.74V9.94H160.42L163.33 3.36H166.16L166.84 4.85V3.36H170.35L170.94 4.93Z"
					fill="#006FCF"
				/>
				{ /* Diners Club */ }
				<rect
					x="109.52"
					y="0.5"
					width="28.5"
					height="18"
					rx="1.5"
					fill="white"
				/>
				<rect
					x="109.89"
					y="0.88"
					width="27.75"
					height="17.25"
					rx="1.12"
					stroke="black"
					strokeOpacity="0.25"
					strokeWidth="0.75"
				/>
				<path
					d="M122.67 3.5C119.43 3.52 116.77 6.46 116.77 9.55C116.77 12.83 119.43 15.5 122.66 15.5H124.17C127.36 15.5 130.27 12.83 130.27 9.55C130.27 6.46 127.36 3.5 124.17 3.5H122.67Z"
					fill="#254D98"
				/>
			</svg>
		</div>
	);
};

const PreviewFooter = ( { themedStyle, guestLinkStyle, linkStyle } ) => {
	return (
		<div className="preview-layout__footer" style={ themedStyle }>
			<div className="preview-layout__footer-links">
				<span
					className="preview-layout__footer-guest-link"
					style={ guestLinkStyle }
				>
					Checkout as guest
				</span>
				<span className="preview-layout__footer-dot">•</span>
				<span style={ linkStyle }>Terms of use</span>
				<span className="preview-layout__footer-dot">•</span>
				<span style={ linkStyle }>Privacy policy</span>
				<span className="preview-layout__footer-dot">•</span>
				<span style={ linkStyle }>Help</span>
			</div>
			<PaymentCardIcons />
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

const CheckoutButton = ( { height } ) => {
	return (
		<div className="preview-layout__checkout-button" style={ { height } }>
			Place order
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

export default ( {
	storeName,
	storeLogo,
	customMessage,
	appearance,
	...props
} ) => {
	const { style, ...restProps } = props;

	const themed = useMemo( () => getThemedStyles( appearance ), [
		appearance,
	] );

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
			{ ...restProps }
		>
			{
				// TODO: Commented out for now. Will be used in a future iteration.
				// See https://github.com/Automattic/woopay/issues/2559#issuecomment-2064013672
				// <PreviewButton />
			 }
			<PreviewContainer themedStyle={ themed.container }>
				<VerticalSpacer height="0.75rem" />
				<StoreHeader
					className="preview-layout__store-header"
					variant={ storeLogo ? 'logo' : 'text' }
					height={ storeLogo ? '2rem' : '1.5rem' }
					themedStyle={ themed.storeHeader }
				>
					<ChevronLeft />
					{ storeHeader }
				</StoreHeader>
				<VerticalSpacer height={ storeLogo ? '0.4rem' : '0.75rem' } />
				<hr className="preview-layout__hr" style={ themed.hr } />
				<PreviewBody themedStyle={ themed.body }>
					<VerticalSpacer height="1.5rem" />
					<ColumnsContainer>
						<LeftColumn>
							<ContactSection>
								<ContactField>
									<SectionHeader
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										CONTACT
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										jane@example.com
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										SHIP TO
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										Jane Smith, 123 Main St,
										<br />
										San Francisco, CA 94105
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										SHIPPING METHOD
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										Free shipping — Free
									</FieldValue>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										PAY WITH
										<ChevronDown />
									</SectionHeader>
									<FieldValue themedStyle={ themed.textBox }>
										Visa ···· 4242 Exp. 12/29
									</FieldValue>
								</ContactField>
							</ContactSection>

							<VerticalSpacer height="1.244rem" />
							{ preparedCustomMessage && (
								<>
									<TextBox
										maxHeight="2.5rem"
										themedStyle={ themed.textBox }
									>
										{ preparedCustomMessage }
									</TextBox>
									<VerticalSpacer height="0.75rem" />
								</>
							) }
							<CheckoutButton height="1.875rem" />

							<VerticalSpacer height="0.498rem" />
						</LeftColumn>
						<RightColumn>
							<SectionHeader
								height="0.75rem"
								themedStyle={ themed.sectionHeader }
							>
								ORDER SUMMARY
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<OrderItem
								name="Beanie"
								price="$ 18.00"
								themedStyle={ themed.textBox }
							/>
							<VerticalSpacer height="0.25rem" />
							<hr className="preview-layout__hr preview-layout__hr--dotted" />
							<VerticalSpacer height="0.25rem" />
							<OrderRow
								label="Subtotal"
								value="$ 18.00"
								themedStyle={ themed.textBox }
							/>
							<OrderRow
								label="Shipping"
								value="Free"
								themedStyle={ themed.textBox }
							/>
							<hr className="preview-layout__hr" />
							<OrderRow
								label="Total"
								value="$ 18.00"
								themedStyle={ {
									...themed.textBox,
									fontWeight: 600,
								} }
							/>
							<VerticalSpacer height="0.498rem" />
						</RightColumn>
					</ColumnsContainer>
				</PreviewBody>
			</PreviewContainer>
			<PreviewFooter
				themedStyle={ themed.footer }
				guestLinkStyle={ themed.footerGuestLink }
				linkStyle={ themed.footerLink }
			/>
		</div>
	);
};
