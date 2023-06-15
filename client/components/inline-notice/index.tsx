/**
 * External dependencies
 */
import React from 'react';
import { Notice } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface InlineNoticeProps extends Notice.Props {
	className?: string;
}

const InlineNotice: React.FC< InlineNoticeProps > = ( {
	className,
	...restProps
} ) => (
	<Notice
		className={ classNames( 'wcpay-inline-notice', className ) }
		{ ...restProps }
	/>
);

export default InlineNotice;
