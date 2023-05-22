/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon, Notice, Button } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies.
 */
import './styles.scss';

/**
 * Props for the BannerNotice component.
 *
 * @typedef {Object} BannerNoticeProps
 * @property {Icon.IconType<unknown>} icon The icon to display.
 */
interface BannerNoticeProps extends Notice.Props {
	icon?: Icon.IconType< unknown >;
}

/**
 * Renders a banner notice.
 *
 * @param {BannerNoticeProps} props                    Banner notice props.
 * @param {Icon.IconType<unknown>} props.icon          The icon to display. Supports all icons from @wordpress/icons.
 * @param {Notice.Props} props.noticeProps             The props for the Notice component.
 * @param {string} props.noticeProps.status            The status of the notice.
 * @param {boolean} props.noticeProps.isDismissible    Whether the notice is dismissible.
 * @param {string} props.noticeProps.className         The class name for the notice.
 * @param {React.ReactNode} props.noticeProps.children The children of the notice.
 * @param {Notice.Action[]} props.noticeProps.actions  The actions for the notice.
 *
 * @return {JSX.Element} Rendered banner notice.
 */
function BannerNotice( props: BannerNoticeProps ): JSX.Element {
	const { icon, ...noticeProps } = props;

	// Add the default class name to the notice.
	noticeProps.className = classNames(
		'wcpay-banner-notice',
		`wcpay-banner-${ noticeProps.status }-notice`,
		noticeProps.className
	);

	// Convert the notice actions to buttons or link elements.
	let actions = null;
	if ( noticeProps.actions ) {
		const actionClass = 'wcpay-banner-notice__action';
		actions = noticeProps.actions.map( ( action, index ) => {
			// Actions that contain a URL will be rendered as a link.
			// This matches WP Notice component behavior.
			if ( 'url' in action ) {
				return (
					<a
						key={ index }
						className={ actionClass }
						href={ action.url }
					>
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

		// We'll render the actions ourselves so we need to remove them from the props sent to the notice component.
		delete noticeProps.actions;
	}

	return (
		<Notice { ...noticeProps }>
			<Flex align="center" justify="flex-start">
				{ icon && (
					<FlexItem
						className={ `wcpay-banner-notice__icon wcpay-banner-${ noticeProps.status }-notice__icon` }
					>
						<Icon icon={ icon } size={ 24 } />
					</FlexItem>
				) }
				<FlexItem
					className={ `wcpay-banner-notice__content wcpay-banner-${ noticeProps.status }-notice__content` }
				>
					{ noticeProps.children }
					{ actions && (
						<Flex
							className="wcpay-banner-notice__content__actions"
							align="baseline"
							justify="flex-start"
							gap={ 4 }
						>
							{ actions }
						</Flex>
					) }
				</FlexItem>
			</Flex>
		</Notice>
	);
}

export default BannerNotice;
