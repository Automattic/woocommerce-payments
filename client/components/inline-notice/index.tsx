/**
 * External dependencies
 */
import React, { ComponentProps } from 'react';
import {
	Flex as BundledWordPressComponentsFlex,
	FlexItem as BundledWordPressComponentsFlexItem,
	Icon as BundledWordPressComponentsIcon,
	Notice as BundledWordPressComponentsNotice,
	Button as BundledWordPressComponentsButton,
} from '@wordpress/components';

import clsx from 'clsx';
import CheckmarkIcon from 'gridicons/dist/checkmark';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { Action } from 'wcpay/types/notices';

/**
 * Internal dependencies.
 */
import './styles.scss';
import ButtonVariant = BundledWordPressComponentsButton.ButtonVariant;
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

interface InlineNoticeProps {
	/**
	 * Whether to display the default icon based on status prop or the icon to display.
	 * Supported values are: boolean, JSX.Element and `undefined`.
	 *
	 * @default undefined
	 */
	icon?: boolean | JSX.Element;

	actions?: readonly Action[] | undefined;
	/**
	 * Allows more control over the button variant.
	 * Accepted values are 'primary', 'secondary', 'tertiary', and 'link'.
	 *
	 * @default undefined
	 */
	buttonVariant?: ButtonVariant;
}

/**
 * Renders a banner notice.
 */
function InlineNotice(
	props: InlineNoticeProps &
		ComponentProps< typeof BundledWordPressComponentsNotice >
): JSX.Element {
	const { icon, actions, children, buttonVariant, ...noticeProps } = props;
	const context = React.useContext( WordPressComponentsContext );

	// Add the default class name to the notice.
	noticeProps.className = clsx(
		'wcpay-inline-notice',
		`wcpay-inline-${ noticeProps.status }-notice`,
		noticeProps.className
	);

	// Use default icon based on status if icon === true.
	let iconToDisplay = icon;
	if ( iconToDisplay === true ) {
		switch ( noticeProps.status ) {
			case 'success':
				iconToDisplay = <CheckmarkIcon />;
				break;
			case 'error':
			case 'warning':
				iconToDisplay = <NoticeOutlineIcon />;
				break;
			case 'info':
			default:
				iconToDisplay = <InfoOutlineIcon />;
				break;
		}
	}

	// Convert the notice actions to buttons or link elements.
	const actionClass = 'wcpay-inline-notice__action';
	const mappedActions = actions?.map( ( action, index ) => {
		// Actions that contain a URL will be rendered as a link.
		// This matches WP Notice component behavior.
		if ( 'url' in action ) {
			return (
				<a key={ index } className={ actionClass } href={ action.url }>
					{ action.label }
				</a>
			);
		}

		if ( ! context ) {
			return (
				<BundledWordPressComponentsButton
					key={ index }
					className={ actionClass }
					onClick={ action.onClick }
					isBusy={ action.isBusy ?? false }
					disabled={ action.disabled ?? false }
					variant={ buttonVariant }
				>
					{ action.label }
				</BundledWordPressComponentsButton>
			);
		}

		const { Button } = context;

		return (
			<Button
				key={ index }
				className={ actionClass }
				onClick={ action.onClick }
				isBusy={ action.isBusy ?? false }
				disabled={ action.disabled ?? false }
				variant={ buttonVariant }
			>
				{ action.label }
			</Button>
		);
	} );

	if ( ! context ) {
		return (
			<BundledWordPressComponentsNotice { ...noticeProps }>
				<BundledWordPressComponentsFlex
					align="center"
					justify="flex-start"
				>
					{ iconToDisplay && (
						<BundledWordPressComponentsFlexItem
							className={ `wcpay-inline-notice__icon wcpay-inline-${ noticeProps.status }-notice__icon` }
						>
							<BundledWordPressComponentsIcon
								icon={ iconToDisplay }
								size={ 24 }
							/>
						</BundledWordPressComponentsFlexItem>
					) }
					<BundledWordPressComponentsFlexItem
						className={ `wcpay-inline-notice__content wcpay-inline-${ noticeProps.status }-notice__content` }
					>
						{ children }
						{ mappedActions && (
							<BundledWordPressComponentsFlex
								className="wcpay-inline-notice__content__actions"
								align="baseline"
								justify="flex-start"
								gap={ 4 }
							>
								{ mappedActions }
							</BundledWordPressComponentsFlex>
						) }
					</BundledWordPressComponentsFlexItem>
				</BundledWordPressComponentsFlex>
			</BundledWordPressComponentsNotice>
		);
	}

	const { Notice, Flex, FlexItem, Icon } = context;

	return (
		<Notice { ...noticeProps }>
			<Flex align="center" justify="flex-start">
				{ iconToDisplay && (
					<FlexItem
						className={ `wcpay-inline-notice__icon wcpay-inline-${ noticeProps.status }-notice__icon` }
					>
						<Icon icon={ iconToDisplay } size={ 24 } />
					</FlexItem>
				) }
				<FlexItem
					className={ `wcpay-inline-notice__content wcpay-inline-${ noticeProps.status }-notice__content` }
				>
					{ children }
					{ mappedActions && (
						<Flex
							className="wcpay-inline-notice__content__actions"
							align="baseline"
							justify="flex-start"
							gap={ 4 }
						>
							{ mappedActions }
						</Flex>
					) }
				</FlexItem>
			</Flex>
		</Notice>
	);
}

export default InlineNotice;
