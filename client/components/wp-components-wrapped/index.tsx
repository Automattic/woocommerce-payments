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
	Notice as BundledWordPressComponentsNotice,
} from '@wordpress/components';
import BundledWordPressComponentsCardNotice from 'wcpay/components/card-notice';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const WrappedCard = (
	props: ComponentProps< typeof BundledWordPressComponentsCard >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCard { ...props } />;
	}

	const { Card } = context;

	return <Card { ...props } />;
};

const WrappedCardBody = (
	props: ComponentProps< typeof BundledWordPressComponentsCardBody >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCardBody { ...props } />;
	}

	const { CardBody } = context;

	return <CardBody { ...props } />;
};

const WrappedButton = (
	props: ComponentProps< typeof BundledWordPressComponentsButton >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsButton { ...props } />;
	}

	const { Button } = context;

	return <Button { ...props } />;
};

const WrappedPanelBody = (
	props: ComponentProps< typeof BundledWordPressComponentsPanelBody >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsPanelBody { ...props } />;
	}

	const { PanelBody } = context;

	return <PanelBody { ...props } />;
};

const WrappedExternalLink = (
	props: ComponentProps< typeof BundledWordPressComponentsExternalLink >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsExternalLink { ...props } />;
	}

	const { ExternalLink } = context;

	return <ExternalLink { ...props } />;
};

const WrappedFlex = (
	props: ComponentProps< typeof BundledWordPressComponentsFlex >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsFlex { ...props } />;
	}

	const { Flex } = context;

	return <Flex { ...props } />;
};

const WrappedFlexItem = (
	props: ComponentProps< typeof BundledWordPressComponentsFlexItem >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsFlexItem { ...props } />;
	}

	const { FlexItem } = context;

	return <FlexItem { ...props } />;
};

const WrappedIcon = (
	props: ComponentProps< typeof BundledWordPressComponentsIcon > & {
		className?: string;
	}
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsIcon { ...props } />;
	}

	const { Icon } = context;

	return <Icon { ...props } />;
};

const WrappedModal = (
	props: ComponentProps< typeof BundledWordPressComponentsModal >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsModal { ...props } />;
	}

	const { Modal } = context;

	return <Modal { ...props } />;
};

const WrappedHorizontalRule = (
	props: ComponentProps< typeof BundledWordPressComponentsHorizontalRule >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsHorizontalRule { ...props } />;
	}

	const { HorizontalRule } = context;

	return <HorizontalRule { ...props } />;
};

const WrappedCardFooter = (
	props: ComponentProps< typeof BundledWordPressComponentsCardFooter >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCardFooter { ...props } />;
	}

	const { CardFooter } = context;

	return <CardFooter { ...props } />;
};

const WrappedCardHeader = (
	props: ComponentProps< typeof BundledWordPressComponentsCardHeader >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCardHeader { ...props } />;
	}

	const { CardHeader } = context;

	return <CardHeader { ...props } />;
};

const WrappedCardDivider = (
	props: ComponentProps< typeof BundledWordPressComponentsCardDivider >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCardDivider { ...props } />;
	}

	const { CardDivider } = context;

	return <CardDivider { ...props } />;
};

const WrappedDropdownMenu = (
	props: ComponentProps< typeof BundledWordPressComponentsDropdownMenu >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsDropdownMenu { ...props } />;
	}

	const { DropdownMenu } = context;

	return <DropdownMenu { ...props } />;
};

const WrappedMenuGroup = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuGroup >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsMenuGroup { ...props } />;
	}

	const { MenuGroup } = context;

	return <MenuGroup { ...props } />;
};

const WrappedMenuItem = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuItem >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsMenuItem { ...props } />;
	}

	const { MenuItem } = context;

	return <MenuItem { ...props } />;
};

const WrappedCardNotice = (
	props: ComponentProps< typeof BundledWordPressComponentsCardNotice >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCardNotice { ...props } />;
	}

	const { CardNotice } = context;

	return <CardNotice { ...props } />;
};

const WrappedNotice = (
	props: ComponentProps< typeof BundledWordPressComponentsNotice >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsNotice { ...props } />;
	}

	const { Notice } = context;

	return <Notice { ...props } />;
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
	WrappedNotice as Notice,
};
