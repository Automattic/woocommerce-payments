/**
 * External dependencies
 */
import React, { ComponentProps, useContext } from 'react';
import {
	Card as BundledWordPressComponentsCard,
	CardBody as BundledWordPressComponentsCardBody,
	Button as BundledWordPressComponentsButton,
	PanelBody as BundledWordPressComponentsPanelBody,
	ExternalLink as BundledWordPressComponentsExternalLink,
	Flex as BundledWordPressComponentsFlex,
	FlexItem as BundledWordPressComponentsFlexItem,
	Icon as BundledWordPressComponentsIcon,
	Modal as BundledWordPressComponentsModal,
	HorizontalRule as BundledWordPressComponentsHorizontalRule,
	CardFooter as BundledWordPressComponentsCardFooter,
	CardHeader as BundledWordPressComponentsCardHeader,
	CardDivider as BundledWordPressComponentsCardDivider,
	DropdownMenu as BundledWordPressComponentsDropdownMenu,
	MenuGroup as BundledWordPressComponentsMenuGroup,
	MenuItem as BundledWordPressComponentsMenuItem,
} from '@wordpress/components';
import BundledWordPressComponentsCardNotice from 'wcpay/components/card-notice';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const WrappedCard = (
	props: ComponentProps< typeof BundledWordPressComponentsCard > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCard { ...props } />;
	}

	const { Card } = context;

	return <Card { ...props } />;
};

const WrappedCardBody = (
	props: ComponentProps< typeof BundledWordPressComponentsCardBody > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCardBody { ...props } />;
	}

	const { CardBody } = context;

	return <CardBody { ...props } />;
};

const WrappedButton = (
	props: ComponentProps< typeof BundledWordPressComponentsButton > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsButton { ...props } />;
	}

	const { Button } = context;

	return <Button { ...props } />;
};

const WrappedPanelBody = (
	props: ComponentProps< typeof BundledWordPressComponentsPanelBody > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsPanelBody { ...props } />;
	}

	const { PanelBody } = context;

	return <PanelBody { ...props } />;
};

const WrappedExternalLink = (
	props: ComponentProps< typeof BundledWordPressComponentsExternalLink > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsExternalLink { ...props } />;
	}

	const { ExternalLink } = context;

	return <ExternalLink { ...props } />;
};

const WrappedFlex = (
	props: ComponentProps< typeof BundledWordPressComponentsFlex > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsFlex { ...props } />;
	}

	const { Flex } = context;

	return <Flex { ...props } />;
};

const WrappedFlexItem = (
	props: ComponentProps< typeof BundledWordPressComponentsFlexItem > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsFlexItem { ...props } />;
	}

	const { FlexItem } = context;

	return <FlexItem { ...props } />;
};

const WrappedIcon = (
	props: ComponentProps< typeof BundledWordPressComponentsIcon > & {
		forceUseBundledComponent?: boolean;
		className?: string;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsIcon { ...props } />;
	}

	const { Icon } = context;

	return <Icon { ...props } />;
};

const WrappedModal = (
	props: ComponentProps< typeof BundledWordPressComponentsModal > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsModal { ...props } />;
	}

	const { Modal } = context;

	return <Modal { ...props } />;
};

const WrappedHorizontalRule = (
	props: ComponentProps< typeof BundledWordPressComponentsHorizontalRule > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsHorizontalRule { ...props } />;
	}

	const { HorizontalRule } = context;

	return <HorizontalRule { ...props } />;
};

const WrappedCardFooter = (
	props: ComponentProps< typeof BundledWordPressComponentsCardFooter > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCardFooter { ...props } />;
	}

	const { CardFooter } = context;

	return <CardFooter { ...props } />;
};

const WrappedCardHeader = (
	props: ComponentProps< typeof BundledWordPressComponentsCardHeader > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCardHeader { ...props } />;
	}

	const { CardHeader } = context;

	return <CardHeader { ...props } />;
};

const WrappedCardDivider = (
	props: ComponentProps< typeof BundledWordPressComponentsCardDivider > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCardDivider { ...props } />;
	}

	const { CardDivider } = context;

	return <CardDivider { ...props } />;
};

const WrappedDropdownMenu = (
	props: ComponentProps< typeof BundledWordPressComponentsDropdownMenu > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsDropdownMenu { ...props } />;
	}

	const { DropdownMenu } = context;

	return <DropdownMenu { ...props } />;
};

const WrappedMenuGroup = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuGroup > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsMenuGroup { ...props } />;
	}

	const { MenuGroup } = context;

	return <MenuGroup { ...props } />;
};

const WrappedMenuItem = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuItem > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsMenuItem { ...props } />;
	}

	const { MenuItem } = context;

	return <MenuItem { ...props } />;
};

const WrappedCardNotice = (
	props: ComponentProps< typeof BundledWordPressComponentsCardNotice > & {
		forceUseBundledComponent?: boolean;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context || props.forceUseBundledComponent ) {
		return <BundledWordPressComponentsCardNotice { ...props } />;
	}

	const { CardNotice } = context;

	return <CardNotice { ...props } />;
};

export {
	WrappedCard as Card,
	WrappedCardBody as CardBody,
	WrappedButton as Button,
	WrappedPanelBody as PanelBody,
	WrappedExternalLink as ExternalLink,
	WrappedFlex as Flex,
	WrappedFlexItem as FlexItem,
	WrappedIcon as Icon,
	WrappedModal as Modal,
	WrappedHorizontalRule as HorizontalRule,
	WrappedCardFooter as CardFooter,
	WrappedCardHeader as CardHeader,
	WrappedCardDivider as CardDivider,
	WrappedDropdownMenu as DropdownMenu,
	WrappedMenuGroup as MenuGroup,
	WrappedMenuItem as MenuItem,
	WrappedCardNotice as CardNotice,
};
