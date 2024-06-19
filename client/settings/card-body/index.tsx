/**
 * External dependencies
 */
import React from 'react';
import { WPCardBody } from 'hack-week-2024-components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './styles.scss';

interface WcpayCardBodyProps {
	className?: string;
}

const WcpayCardBody: React.FC< WcpayCardBodyProps > = ( {
	className,
	...props
} ): JSX.Element => (
	<WPCardBody
		size={ 'none' }
		className={ classNames( 'wcpay-card-body', className ) }
		{ ...props }
	/>
);

export default WcpayCardBody;
