/**
 * External dependencies
 */
import React from 'react';
import { List } from '@woocommerce/components';
import { useDispatch } from '@wordpress/data';

import { Card, CardBody, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';

export interface Apm {
	id: string;
	title: string;
	image_72x72: string;
	content: string;
	plugins: Array< string >;
}

const getRecommendeddApms = () => {
	const { paymentGatewaySuggestions = [], activePlugins = [] } =
		wcMarketplaceSuggestions || {};

	return paymentGatewaySuggestions
		.filter( ( apm: Apm ) => apm.plugins && apm.plugins.length > 0 )
		.filter(
			( apm: Apm ) =>
				! Object.values( activePlugins ).includes( apm.plugins[ 0 ] )
		);
};

const ApmList: React.FC = () => {
	const { installAndActivatePlugins } = useDispatch( 'wc/admin/plugins' );

	const handleInstall = async ( apm: Apm ) => {
		try {
			const installAndActivateResponse = await installAndActivatePlugins(
				apm.plugins
			);

			if ( ! installAndActivateResponse?.success ) {
				throw new Error( installAndActivateResponse.message );
			}
		} catch ( e ) {
			if ( e instanceof Error ) {
				throw new Error( `Unexpected error occurred. ${ e }` );
			}
		}
	};

	const apmsList = getRecommendeddApms().map( ( apm: Apm ) => ( {
		key: apm.id,
		title: apm.title,
		content: apm.content,
		before: <img src={ apm.image_72x72 } alt="" />,
		after: (
			<Button isSecondary onClick={ () => handleInstall( apm ) }>
				{ strings.additionalPaymentMethods.install }
			</Button>
		),
	} ) );

	return (
		<>
			<Card
				size="large"
				className="connect-account-page__additional-payment-methods"
			>
				<CardBody>
					<List items={ apmsList } />
				</CardBody>
			</Card>
		</>
	);
};

export default ApmList;
