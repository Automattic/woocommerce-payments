/**
 * External dependencies
 */
import React, { ComponentProps } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import { CardBody } from 'wcpay/components/card';
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
