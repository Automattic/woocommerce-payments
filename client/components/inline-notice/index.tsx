/**
 * External dependencies
 */
import * as React from 'react';
import {
	Button as ButtonType,
	Notice as NoticeType,
} from '@wordpress/components';
import {
	Flex,
	FlexItem,
	Icon,
	Notice,
	Button,
} from 'wcpay/components/wp-components-wrapped';
import classNames from 'classnames';
import CheckmarkIcon from 'gridicons/dist/checkmark';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { Action } from 'wcpay/types/notices';

/**
 * Internal dependencies.
 */
import './styles.scss';
import ButtonVariant = ButtonType.ButtonVariant;

interface InlineNoticeProps extends NoticeType.Props {
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

	/**
	 * Whether to use the bundled WordPress components.
	 *
	 * @default false
	 */
	forceUseBundledComponent?: boolean;
}

/**
 * Renders a banner notice.
 */
function InlineNotice( props: InlineNoticeProps ): JSX.Element {
	const {
		icon,
		actions,
		children,
		buttonVariant,
		forceUseBundledComponent = true,
		...noticeProps
	} = props;

	// Add the default class name to the notice.
	noticeProps.className = classNames(
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

	return (
		<Notice
			forceUseBundledComponent={ forceUseBundledComponent }
			{ ...noticeProps }
		>
			<Flex
				forceUseBundledComponent={ forceUseBundledComponent }
				align="center"
				justify="flex-start"
			>
				{ iconToDisplay && (
					<FlexItem
						forceUseBundledComponent={ forceUseBundledComponent }
						className={ `wcpay-inline-notice__icon wcpay-inline-${ noticeProps.status }-notice__icon` }
					>
						<Icon icon={ iconToDisplay } size={ 24 } />
					</FlexItem>
				) }
				<FlexItem
					forceUseBundledComponent={ forceUseBundledComponent }
					className={ `wcpay-inline-notice__content wcpay-inline-${ noticeProps.status }-notice__content` }
				>
					{ children }
					{ mappedActions && (
						<Flex
							forceUseBundledComponent={
								forceUseBundledComponent
							}
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
