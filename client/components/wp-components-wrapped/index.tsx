/**
 * External dependencies
 */
import React, { ComponentProps, useContext } from 'react';
// eslint-disable-next-line no-restricted-syntax
import {
	BaseControl as BundledBaseControl,
	CheckboxControl as BundledCheckboxControl,
	Card as BundledCard,
	CardBody as BundledCardBody,
	Button as BundledButton,
	PanelBody as BundledPanelBody,
	ExternalLink as BundledExternalLink,
	Flex as BundledFlex,
	FlexItem as BundledFlexItem,
	Icon as BundledIcon,
	Modal as BundledModal,
	CardFooter as BundledCardFooter,
	CardHeader as BundledCardHeader,
	CardDivider as BundledCardDivider,
	DropdownMenu as BundledDropdownMenu,
	MenuGroup as BundledMenuGroup,
	MenuItem as BundledMenuItem,
	Notice as BundledNotice,
	SelectControl as BundledSelectControl,
	TextControl as BundledTextControl,
	TextareaControl as BundledTextareaControl,
	FormFileUpload as BundledFormFileUpload,
	Tooltip as BundledTooltip,
	RadioControl as BundledRadioControl,
	ToggleControl as BundledToggleControl,
	FlexBlock as BundledFlexBlock,
	DropZone as BundledDropZone,
	Popover as BundledPopover,
	TabPanel as BundledTabPanel,
	HorizontalRule as BundledHorizontalRule,
	Spinner as BundledSpinner,
	Panel as BundledPanel,
	SnackbarList as BundledSnackbarList,
	RangeControl as BundledRangeControl,
} from '@wordpress/components';
import BundledCardNotice from 'wcpay/components/card-notice';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const makeWrappedComponent = <
	T extends React.ComponentType< any >,
	N extends string
>(
	BundledComponent: T,
	componentName: N
) =>
	React.forwardRef<
		any,
		ComponentProps< T > & { useBundledComponent?: boolean }
	>( ( props, ref ) => {
		const { useBundledComponent, ...rest } = props;
		const context = useContext( WordPressComponentsContext );

		if ( ! context || useBundledComponent ) {
			// @ts-expect-error: the type of props is not always well-defined, ignoring the error.
			return <BundledComponent { ...rest } ref={ ref } />;
		}

		const ContextComponent = context[
			componentName as keyof typeof context
		] as React.ComponentType< any >;

		return <ContextComponent { ...rest } ref={ ref } />;
	} );

export const BaseControl = makeWrappedComponent(
	BundledBaseControl,
	'BaseControl'
);

export const Tooltip = makeWrappedComponent( BundledTooltip, 'Tooltip' );

export const ToggleControl = makeWrappedComponent(
	BundledToggleControl,
	'ToggleControl'
);

export const RadioControl = makeWrappedComponent(
	BundledRadioControl,
	'RadioControl'
);

export const CheckboxControl = makeWrappedComponent(
	BundledCheckboxControl,
	'CheckboxControl'
);

export const Card = makeWrappedComponent( BundledCard, 'Card' );

export const CardBody = makeWrappedComponent( BundledCardBody, 'CardBody' );

export const Button = makeWrappedComponent( BundledButton, 'Button' );

export const PanelBody = makeWrappedComponent( BundledPanelBody, 'PanelBody' );

export const ExternalLink = makeWrappedComponent(
	BundledExternalLink,
	'ExternalLink'
);

export const Flex = makeWrappedComponent( BundledFlex, 'Flex' );

export const FlexItem = makeWrappedComponent( BundledFlexItem, 'FlexItem' );

export const Icon = makeWrappedComponent( BundledIcon, 'Icon' );

export const Modal = makeWrappedComponent( BundledModal, 'Modal' );

export const CardFooter = makeWrappedComponent(
	BundledCardFooter,
	'CardFooter'
);

export const CardHeader = makeWrappedComponent(
	BundledCardHeader,
	'CardHeader'
);

export const CardDivider = makeWrappedComponent(
	BundledCardDivider,
	'CardDivider'
);

export const DropdownMenu = makeWrappedComponent(
	BundledDropdownMenu,
	'DropdownMenu'
);

export const MenuGroup = makeWrappedComponent( BundledMenuGroup, 'MenuGroup' );

export const MenuItem = makeWrappedComponent( BundledMenuItem, 'MenuItem' );

export const CardNotice = makeWrappedComponent(
	BundledCardNotice,
	'CardNotice'
);

export const Notice = makeWrappedComponent( BundledNotice, 'Notice' );

export const SelectControl = makeWrappedComponent(
	BundledSelectControl,
	'SelectControl'
);

export const TextControl = makeWrappedComponent(
	BundledTextControl,
	'TextControl'
);

export const TextareaControl = makeWrappedComponent(
	BundledTextareaControl,
	'TextareaControl'
);

export const FormFileUpload = makeWrappedComponent(
	BundledFormFileUpload,
	'FormFileUpload'
);

export const FlexBlock = makeWrappedComponent( BundledFlexBlock, 'FlexBlock' );

export const DropZone = makeWrappedComponent( BundledDropZone, 'DropZone' );

export const Popover = makeWrappedComponent( BundledPopover, 'Popover' );

export const TabPanel = makeWrappedComponent( BundledTabPanel, 'TabPanel' );

export const HorizontalRule = makeWrappedComponent(
	// @ts-expect-error: suppressing because of how the HorizontalRule component is defined, but it's no problem.
	BundledHorizontalRule,
	'HorizontalRule'
);

export const Spinner = makeWrappedComponent( BundledSpinner, 'Spinner' );

export const Panel = makeWrappedComponent( BundledPanel, 'Panel' );

export const SnackbarList = makeWrappedComponent(
	BundledSnackbarList,
	'SnackbarList'
);

export const RangeControl = makeWrappedComponent(
	BundledRangeControl,
	'RangeControl'
);
