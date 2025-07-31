/**
 * External dependencies
 */
import React, { ComponentProps } from 'react';
import { CardBody } from '@wordpress/components';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './styles.scss';

interface WcpayCardBodyProps {
	className?: string;
}

const WcpayCardBody = ( {
	className,
	...props
}: WcpayCardBodyProps & ComponentProps< typeof CardBody > ) => {
	return (
		<CardBody
			className={ clsx( 'wcpay-card-body', className ) }
			{ ...props }
		/>
	);
};

export default WcpayCardBody;
