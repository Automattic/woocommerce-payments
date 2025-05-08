/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { CardBody as BundledWordPressComponentsCardBody } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './styles.scss';
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

interface WcpayCardBodyProps {
	className?: string;
}

const WcpayCardBody: React.FC< WcpayCardBodyProps > = ( {
	className,
	...props
} ): JSX.Element => {
	const context = useContext( WordPressComponentsContext );

	// including the woopayments-specific styles only for the card body. leaving the `CardBody` bundled within the WP installation as "pristine" as possible, instead.
	if ( ! context ) {
		return (
			<BundledWordPressComponentsCardBody
				className={ classNames( 'wcpay-card-body', className ) }
				{ ...props }
			/>
		);
	}

	const { CardBody } = context;

	return <CardBody className={ className } { ...props } />;
};

export default WcpayCardBody;
