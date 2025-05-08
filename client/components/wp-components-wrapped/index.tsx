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
} from '@wordpress/components';

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
	props: ComponentProps< typeof BundledWordPressComponentsIcon >
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
};
