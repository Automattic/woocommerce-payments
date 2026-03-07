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
		footerLink: {
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

const PreviewFooter = ( { themedStyle, linkStyle } ) => {
	return (
		<div className="preview-layout__footer" style={ themedStyle }>
			<div className="preview-layout__footer-links">
				<span style={ linkStyle }>Terms of use</span>
				<span className="preview-layout__footer-dot">·</span>
				<span style={ linkStyle }>Privacy policy</span>
				<span className="preview-layout__footer-dot">·</span>
				<span style={ linkStyle }>Help</span>
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
				linkStyle={ themed.footerLink }
			/>
		</div>
	);
};
