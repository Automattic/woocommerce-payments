/**
 * External dependencies
 */
import React from 'react';
import { CardBody } from '@wordpress/components';
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
	<CardBody
		className={ classNames( 'wcpay-card-body', className ) }
		{ ...props }
	/>
);

export default WcpayCardBody;
