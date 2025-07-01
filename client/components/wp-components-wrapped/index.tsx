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
	SelectControl as BundledWordPressComponentsSelectControl,
	TextControl as BundledWordPressComponentsTextControl,
	TextareaControl as BundledWordPressComponentsTextareaControl,
	FormFileUpload as BundledWordPressComponentsFormFileUpload,
	RadioControl as BundledWordPressComponentsRadioControl,
} from '@wordpress/components';
import BundledWordPressComponentsCardNotice from 'wcpay/components/card-notice';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const WrappedCard = (
	props: ComponentProps< typeof BundledWordPressComponentsCard > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCard { ...rest } />;
	}

	const { Card } = context;

	return <Card { ...rest } />;
};

const WrappedCardBody = (
	props: ComponentProps< typeof BundledWordPressComponentsCardBody > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCardBody { ...rest } />;
	}

	const { CardBody } = context;

	return <CardBody { ...rest } />;
};

const WrappedButton = (
	props: ComponentProps< typeof BundledWordPressComponentsButton > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsButton { ...rest } />;
	}

	const { Button } = context;

	return <Button { ...rest } />;
};

const WrappedPanelBody = (
	props: ComponentProps< typeof BundledWordPressComponentsPanelBody > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsPanelBody { ...rest } />;
	}

	const { PanelBody } = context;

	return <PanelBody { ...rest } />;
};

const WrappedExternalLink = (
	props: ComponentProps< typeof BundledWordPressComponentsExternalLink > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsExternalLink { ...rest } />;
	}

	const { ExternalLink } = context;

	return <ExternalLink { ...rest } />;
};

const WrappedFlex = (
	props: ComponentProps< typeof BundledWordPressComponentsFlex > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsFlex { ...rest } />;
	}

	const { Flex } = context;

	return <Flex { ...rest } />;
};

const WrappedFlexItem = (
	props: ComponentProps< typeof BundledWordPressComponentsFlexItem > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsFlexItem { ...rest } />;
	}

	const { FlexItem } = context;

	return <FlexItem { ...rest } />;
};

const WrappedIcon = (
	props: ComponentProps< typeof BundledWordPressComponentsIcon > & {
		useBundledComponent?: boolean;
		className?: string;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsIcon { ...rest } />;
	}

	const { Icon } = context;

	return <Icon { ...rest } />;
};

const WrappedModal = (
	props: ComponentProps< typeof BundledWordPressComponentsModal > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsModal { ...rest } />;
	}

	const { Modal } = context;

	return <Modal { ...rest } />;
};

const WrappedHorizontalRule = (
	props: ComponentProps< typeof BundledWordPressComponentsHorizontalRule > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsHorizontalRule { ...rest } />;
	}

	const { HorizontalRule } = context;

	return <HorizontalRule { ...rest } />;
};

const WrappedCardFooter = (
	props: ComponentProps< typeof BundledWordPressComponentsCardFooter > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCardFooter { ...rest } />;
	}

	const { CardFooter } = context;

	return <CardFooter { ...rest } />;
};

const WrappedCardHeader = (
	props: ComponentProps< typeof BundledWordPressComponentsCardHeader > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCardHeader { ...rest } />;
	}

	const { CardHeader } = context;

	return <CardHeader { ...rest } />;
};

const WrappedCardDivider = (
	props: ComponentProps< typeof BundledWordPressComponentsCardDivider > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCardDivider { ...rest } />;
	}

	const { CardDivider } = context;

	return <CardDivider { ...rest } />;
};

const WrappedDropdownMenu = (
	props: ComponentProps< typeof BundledWordPressComponentsDropdownMenu > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsDropdownMenu { ...rest } />;
	}

	const { DropdownMenu } = context;

	return <DropdownMenu { ...rest } />;
};

const WrappedMenuGroup = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuGroup > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsMenuGroup { ...rest } />;
	}

	const { MenuGroup } = context;

	return <MenuGroup { ...rest } />;
};

const WrappedMenuItem = (
	props: ComponentProps< typeof BundledWordPressComponentsMenuItem > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsMenuItem { ...rest } />;
	}

	const { MenuItem } = context;

	return <MenuItem { ...rest } />;
};

const WrappedCardNotice = (
	props: ComponentProps< typeof BundledWordPressComponentsCardNotice > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsCardNotice { ...rest } />;
	}

	const { CardNotice } = context;

	return <CardNotice { ...rest } />;
};

const WrappedNotice = (
	props: ComponentProps< typeof BundledWordPressComponentsNotice > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsNotice { ...rest } />;
	}

	const { Notice } = context;

	return <Notice { ...rest } />;
};

const WrappedSelectControl = (
	props: ComponentProps< typeof BundledWordPressComponentsSelectControl > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsSelectControl { ...rest } />;
	}

	const { SelectControl } = context;

	return <SelectControl { ...rest } />;
};

const WrappedTextControl = (
	props: ComponentProps< typeof BundledWordPressComponentsTextControl > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsTextControl { ...rest } />;
	}

	const { TextControl } = context;

	return <TextControl { ...rest } />;
};

const WrappedTextareaControl = (
	props: ComponentProps<
		typeof BundledWordPressComponentsTextareaControl
	> & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsTextareaControl { ...rest } />;
	}

	const { TextareaControl } = context;

	return <TextareaControl { ...rest } />;
};

const WrappedFormFileUpload = (
	props: ComponentProps< typeof BundledWordPressComponentsFormFileUpload > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsFormFileUpload { ...rest } />;
	}

	const { FormFileUpload } = context;

	return <FormFileUpload { ...rest } />;
};

const WrappedRadioControl = (
	props: ComponentProps< typeof BundledWordPressComponentsRadioControl > & {
		useBundledComponent?: boolean;
	}
) => {
	const { useBundledComponent, ...rest } = props;
	const context = useContext( WordPressComponentsContext );

	if ( ! context || useBundledComponent ) {
		return <BundledWordPressComponentsRadioControl { ...rest } />;
	}

	const { RadioControl } = context;

	return <RadioControl { ...rest } />;
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
	WrappedSelectControl as SelectControl,
	WrappedTextControl as TextControl,
	WrappedTextareaControl as TextareaControl,
	WrappedFormFileUpload as FormFileUpload,
	WrappedRadioControl as RadioControl,
};
