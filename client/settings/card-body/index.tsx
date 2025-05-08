/**
 * External dependencies
 */
import React from 'react';
import { CardBody } from '@wordpress/components';
import clsx from 'clsx';

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
	<CardBody className={ clsx( 'wcpay-card-body', className ) } { ...props } />
);

export default WcpayCardBody;
