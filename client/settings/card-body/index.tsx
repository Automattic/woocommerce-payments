/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { CardBody as BundledWordPressComponentsCardBody } from 'wcpay/components/wp-components-wrapped/components/card-body';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './styles.scss';
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

interface WcpayCardBodyProps {
	className?: string;
}

const WcpayCardBody: React.FC< React.PropsWithChildren<
	WcpayCardBodyProps
> > = ( { className, ...props } ): JSX.Element => {
	const context = useContext( WordPressComponentsContext );

	// including the woopayments-specific styles only for the "bundled" CardBody component.
	// leaving the `CardBody` bundled within the WP installation as "pristine" as possible, instead.
	if ( ! context ) {
		return (
			<BundledWordPressComponentsCardBody
				className={ clsx( 'wcpay-card-body', className ) }
				{ ...props }
			/>
		);
	}

	const { CardBody } = context;

	return <CardBody className={ className } { ...props } />;
};

export default WcpayCardBody;
