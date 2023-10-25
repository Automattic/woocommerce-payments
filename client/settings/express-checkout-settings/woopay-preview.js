/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { decodeEntities } from '@wordpress/html-entities';
import { chevronDown, Icon } from '@wordpress/icons';
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

const TextBox = ( { children, height } ) => {
	return (
		<div className="preview-layout__text-box" style={ { height } }>
			{ children }
		</div>
	);
};

const CheckoutButton = ( { height } ) => {
	return (
		<div className="preview-layout__checkout-button" style={ { height } }>
			Place order
		</div>
	);
};

export default ( { storeName, storeLogo, customMessage, ...props } ) => {
	const { style, ...restProps } = props;
	const trimmedCustomMessage = ( customMessage || '' ).trim();
	const hasMessage = trimmedCustomMessage.length > 0;

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

	const calculatedMarginBottom = useMemo( () => {
		if ( hasMessage ) {
			return 0;
		}

		// If there is no message, we need to calculate the amount of space
		// that the message would have taken up, and then subtract that from
		// the total height of the preview container.
		//
		// 28.567rem is the height of the preview container.
		// 89.498% is the height of the preview container minus sibling elements.
		// 91.142% is the height of the preview body minus sibling elements.
		// 88.218% is the height of the columns container minus sibling elements.
		// 12.172% + 3.636% is the height of the custom message plus the spacer below it.
		return 28.567 * 0.89498 * 0.91142 * 0.88218 * ( 0.12172 + 0.03636 );
	}, [ hasMessage ] );

	return (
		<div
			className="preview-layout"
			style={ {
				height: '28.567rem',
				marginBottom: -1 * calculatedMarginBottom + 'rem',
				...style,
			} }
			{ ...restProps }
		>
			<VerticalSpacer height="5.251%" />
			<PreviewContainer height="89.498%">
				<StoreHeader
					className="preview-layout__store-header"
					variant={ storeLogo ? 'logo' : 'text' }
					height={ storeLogo ? '7.335%' : '4.921%' }
				>
					{ storeHeader }
				</StoreHeader>
				<VerticalSpacer height={ storeLogo ? '1.523%' : '3.937%' } />
				<PreviewBody height="91.142%">
					<CheckoutRow height="5.368%">
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
					<VerticalSpacer height="3.207%" />
					<hr className="preview-layout__hr" />
					<VerticalSpacer height="3.207%" />
					<ColumnsContainer height="88.218%">
						<LeftColumn height="86.232%">
							<SectionHeader height="4.231%">
								Contact
							</SectionHeader>
							<VerticalSpacer height="2.807%" />
							<LoadingBox height="8.463%" />
							<VerticalSpacer height="6.319%" />

							<SectionHeader isDropdownIncluded height="4.231%">
								Ship to
							</SectionHeader>
							<VerticalSpacer height="2.807%" />
							<LoadingBox height="21.509%" />
							<VerticalSpacer height="7.020%" />

							<SectionHeader isDropdownIncluded height="4.231%">
								Shipping method
							</SectionHeader>
							<VerticalSpacer height="2.807%" />
							<LoadingBox height="13.047%" />
							<VerticalSpacer height="7.02%" />

							<SectionHeader isDropdownIncluded height="4.231%">
								Pay with
							</SectionHeader>
							<VerticalSpacer height="2.807%" />
							<LoadingBox height="8.463%" />
						</LeftColumn>
						<RightColumn height="100%">
							<SectionHeader height="3.65%">
								Summary
							</SectionHeader>
							<VerticalSpacer height="2.422%" />
							<LoadingBox height="7.302%" />
							<VerticalSpacer height="1.816%" />
							<LoadingBox height="45.940%" />
							<VerticalSpacer height="2.422%" />
							<LoadingBox height="9.736%" />
							<VerticalSpacer height="3.636%" />
							{ trimmedCustomMessage && (
								<>
									<TextBox height="12.172%">
										{ decodeEntities(
											trimmedCustomMessage
										) }
									</TextBox>
									<VerticalSpacer height="3.636%" />
								</>
							) }
							<CheckoutButton height="7.268%" />
						</RightColumn>
					</ColumnsContainer>
				</PreviewBody>
			</PreviewContainer>
			<VerticalSpacer height="5.251%" />
		</div>
	);
};
