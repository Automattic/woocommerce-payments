/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './styles.scss';
import { CardFooter } from '@wordpress/components';

interface CardNoticeProps {
	actions: JSX.Element;
}

const CardNotice: React.FC< CardNoticeProps > = ( { children, actions } ) => {
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
