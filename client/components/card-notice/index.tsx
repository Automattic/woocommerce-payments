/**
 * External dependencies
 */
import React from 'react';
import { CardFooter } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './styles.scss';

interface CardNoticeProps {
	children: React.ReactNode;
	actions?: JSX.Element;
}

const CardNotice: React.FC< React.PropsWithChildren< CardNoticeProps > > = ( {
	children,
	actions,
} ) => {
	return (
		<CardFooter className="card-notice">
			<div className="card-notice__section">
				<div className="card-notice__text">{ children }</div>
				<div className="card-notice__button">{ actions }</div>
			</div>
		</CardFooter>
	);
};

export default CardNotice;
