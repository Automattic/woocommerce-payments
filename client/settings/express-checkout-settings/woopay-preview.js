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

const LoadingBox = ( { height, themedStyle } ) => {
	return (
		<div
			className="preview-layout__loading-box"
			style={ { height, ...themedStyle } }
		/>
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
									<VerticalSpacer height="0.5rem" />
									<LoadingBox
										height="1.875rem"
										themedStyle={ themed.loadingBox }
									/>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										SHIP TO
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox
										height="3.813rem"
										themedStyle={ themed.loadingBox }
									/>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										SHIPPING METHOD
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox
										height="2.313rem"
										themedStyle={ themed.loadingBox }
									/>
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
										themedStyle={ themed.sectionHeader }
									>
										PAY WITH
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox
										height="1.5rem"
										themedStyle={ themed.loadingBox }
									/>
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
							<LoadingBox
								height="1.563rem"
								themedStyle={ themed.loadingBox }
							/>
							<VerticalSpacer height="0.5rem" />
							<LoadingBox
								height="9.438rem"
								themedStyle={ themed.loadingBox }
							/>
							<VerticalSpacer height="0.498rem" />
						</RightColumn>
					</ColumnsContainer>
				</PreviewBody>
			</PreviewContainer>
			<VerticalSpacer height="1.5rem" />
		</div>
	);
};
