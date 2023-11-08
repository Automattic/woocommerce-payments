/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { decodeEntities } from '@wordpress/html-entities';
import { chevronDown, Icon } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { NAMESPACE } from 'wcpay/data/constants';
import LockKeyhole from 'wcpay/settings/express-checkout-settings/assets/icons/lock-keyhole';

const VerticalSpacer = ( { height } ) => {
	return <div className="preview-layout__v-spacer" style={ { height } } />;
};

const PreviewContainer = ( { height, children } ) => {
	return (
		<div className="preview-layout__container" style={ { height } }>
			{ children }
		</div>
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

const PreviewBody = ( { height, children } ) => {
	return (
		<div className="preview-layout__body" style={ { height } }>
			{ children }
		</div>
	);
};

const CheckoutRow = ( { height, children } ) => {
	return (
		<div className="preview-layout__checkout-row" style={ { height } }>
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

const RightColumn = ( { height, children } ) => {
	return (
		<div className="preview-layout__right-column" style={ { height } }>
			{ children }
		</div>
	);
};

const SectionHeader = ( { children, height, isDropdownIncluded = false } ) => {
	return (
		<div className="preview-layout__section-header" style={ { height } }>
			{ children }
			{ isDropdownIncluded && <Icon icon={ chevronDown } size={ 12 } /> }
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
	} else {
		storeHeader = (
			<span className="header-text">{ decodeEntities( storeName ) }</span>
		);
	}

	return (
		<div className="preview-layout" style={ style } { ...restProps }>
			<VerticalSpacer height="1.5rem" />
			<PreviewContainer>
				<StoreHeader
					className="preview-layout__store-header"
					variant={ storeLogo ? 'logo' : 'text' }
					height={ storeLogo ? '2rem' : '1.5rem' }
				>
					{ storeHeader }
				</StoreHeader>
				<VerticalSpacer height={ storeLogo ? '0.5rem' : '1rem' } />
				<PreviewBody>
					<CheckoutRow height="1.25rem">
						<div className="checkout-text">Checkout</div>
						<div className="secure-block">
							<LockKeyhole
								width="8"
								height="8"
								viewBox="0 0 16 16"
							/>
							<span className="secure-block__text">
								Secure and encrypted with AES-256
							</span>
						</div>
					</CheckoutRow>
					<VerticalSpacer height="0.747rem" />
					<hr className="preview-layout__hr" />
					<VerticalSpacer height="0.747rem" />
					<ColumnsContainer>
						<LeftColumn>
							<SectionHeader height="0.75rem">
								Contact
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="1.5rem" />
							<VerticalSpacer height="1.12rem" />

							<SectionHeader isDropdownIncluded height="0.75rem">
								Ship to
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="3.813rem" />
							<VerticalSpacer height="1.244rem" />

							<SectionHeader isDropdownIncluded height="0.75rem">
								Shipping method
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="2.313rem" />
							<VerticalSpacer height="1.244rem" />

							<SectionHeader isDropdownIncluded height="0.75rem">
								Pay with
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="1.5rem" />
						</LeftColumn>
						<RightColumn>
							<SectionHeader height="0.75rem">
								Summary
							</SectionHeader>
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="1.5rem" />
							<VerticalSpacer height="0.373rem" />
							<LoadingBox height="9.438rem" />
							<VerticalSpacer height="0.498rem" />
							<LoadingBox height="2rem" />
							<VerticalSpacer height="0.747rem" />
							{ preparedCustomMessage && (
								<>
									<TextBox maxHeight="2.5rem">
										{ preparedCustomMessage }
									</TextBox>
									<VerticalSpacer height="0.747rem" />
								</>
							) }
							<CheckoutButton height="1.493rem" />
						</RightColumn>
					</ColumnsContainer>
				</PreviewBody>
			</PreviewContainer>
			<VerticalSpacer height="1.5rem" />
		</div>
	);
};
