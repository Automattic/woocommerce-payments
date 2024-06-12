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

const VerticalSpacer = ( { height } ) => {
	return <div className="preview-layout__v-spacer" style={ { height } } />;
};

// TODO: Commented out for now. Will be used in a future iteration.
// See https://github.com/Automattic/woopay/issues/2559#issuecomment-2064013672
// const PreviewButton = () => {
// 	return <div className="preview-layout__preview-button">Preview</div>;
// };

const PreviewContainer = ( { height, children } ) => {
	return (
		<div className="preview-layout__container" style={ { height } }>
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

const StoreHeader = ( { height, variant = 'test', children } ) => {
	return (
		<div
			className="preview-layout__store-header"
			variant={ variant }
			style={ { height } }
		>
			{ children }
		</div>
	);
};

const PreviewBody = ( { children } ) => {
	return <div className="preview-layout__body">{ children }</div>;
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

const SectionHeader = ( { children, height } ) => {
	return (
		<div className="preview-layout__section-header" style={ { height } }>
			{ children }
		</div>
	);
};

const LoadingBox = ( { height } ) => {
	return <div className="preview-layout__loading-box" style={ { height } } />;
};

const TextBox = ( { children, maxHeight } ) => {
	return (
		<div
			className="preview-layout__text-box"
			style={ { maxHeight } }
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

export default ( { storeName, storeLogo, customMessage, ...props } ) => {
	const { style, ...restProps } = props;

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
			<span className="header-text">{ decodeEntities( storeName ) }</span>
		);
	}

	return (
		<div className="preview-layout" style={ style } { ...restProps }>
			{
				// TODO: Commented out for now. Will be used in a future iteration.
				// See https://github.com/Automattic/woopay/issues/2559#issuecomment-2064013672
				// <PreviewButton />
			 }
			<PreviewContainer>
				<VerticalSpacer height="0.75rem" />
				<StoreHeader
					className="preview-layout__store-header"
					variant={ storeLogo ? 'logo' : 'text' }
					height={ storeLogo ? '2rem' : '1.5rem' }
				>
					<ChevronLeft />
					{ storeHeader }
				</StoreHeader>
				<VerticalSpacer height={ storeLogo ? '0.4rem' : '0.75rem' } />
				<hr className="preview-layout__hr" />
				<PreviewBody>
					<VerticalSpacer height="1.5rem" />
					<ColumnsContainer>
						<LeftColumn>
							<ContactSection>
								<ContactField>
									<SectionHeader height="0.75rem">
										CONTACT
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox height="1.875rem" />
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
									>
										SHIP TO
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox height="3.813rem" />
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
									>
										SHIPPING METHOD
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox height="2.313rem" />
								</ContactField>
								<ContactField>
									<SectionHeader
										isDropdownIncluded
										height="0.75rem"
									>
										PAY WITH
									</SectionHeader>
									<VerticalSpacer height="0.5rem" />
									<LoadingBox height="1.5rem" />
								</ContactField>
							</ContactSection>

							<VerticalSpacer height="1.244rem" />
							{ preparedCustomMessage && (
								<>
									<TextBox maxHeight="2.5rem">
										{ preparedCustomMessage }
									</TextBox>
									<VerticalSpacer height="0.75rem" />
								</>
							) }
							<CheckoutButton height="1.875rem" />

							<VerticalSpacer height="0.498rem" />
						</LeftColumn>
						<RightColumn>
							<SectionHeader height="0.75rem">
								ORDER SUMMARY
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="1.563rem" />
							<VerticalSpacer height="0.5rem" />
							<LoadingBox height="9.438rem" />
							<VerticalSpacer height="0.498rem" />
						</RightColumn>
					</ColumnsContainer>
				</PreviewBody>
			</PreviewContainer>
			<VerticalSpacer height="1.5rem" />
		</div>
	);
};
