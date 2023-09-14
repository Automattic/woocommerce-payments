/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';
import { Button, CardFooter, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */

import './style.scss';

interface Props {
	message: React.ReactNode;
	buttonLabel?: string;
	onButtonClick?: () => void;
	isPrimary?: boolean;
}

const DisputeResolvedMessage: React.FC< Props > = ( {
	message,
	buttonLabel,
	onButtonClick,
	isPrimary,
} ) => {
	return (
		<CardFooter
			className={ classNames( 'transaction-details-dispute-footer', {
				'transaction-details-dispute-footer--primary': isPrimary,
			} ) }
		>
			<Flex justify="space-between">
				<FlexItem>{ message }</FlexItem>
				{ buttonLabel && onButtonClick && (
					<FlexItem className="transaction-details-dispute-footer__actions">
						<Button variant="secondary" onClick={ onButtonClick }>
							{ buttonLabel }
						</Button>
					</FlexItem>
				) }
			</Flex>
		</CardFooter>
	);
};

export default DisputeResolvedMessage;
