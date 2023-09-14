/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon, Notice, Button } from '@wordpress/components';
import classNames from 'classnames';
import CheckmarkIcon from 'gridicons/dist/checkmark';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import InfoOutlineIcon from 'gridicons/dist/info-outline';

/**
 * Internal dependencies.
 */
import './styles.scss';

interface InlineNoticeProps extends Notice.Props {
	/**
	 * Whether to display the default icon based on status prop or the icon to display.
	 * Supported values are: boolean, JSX.Element and `undefined`.
	 *
	 * @default undefined
	 */
	icon?: boolean | JSX.Element;
}

/**
 * Renders a banner notice.
 */
function InlineNotice( props: InlineNoticeProps ): JSX.Element {
	const { icon, actions, children, ...noticeProps } = props;

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
			>
				{ action.label }
			</Button>
		);
	} );

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
