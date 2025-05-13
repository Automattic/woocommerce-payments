/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import LightbulbIcon from 'components/icons/lightbulb';
import './style.scss';

interface Props {
	color: 'purple' | 'blue' | 'gray' | 'yellow';
	className?: string;
}
const TipBox: React.FC< Props > = ( { color, className, children } ) => {
	return (
		<div className={ clsx( 'wcpay-component-tip-box', color, className ) }>
			<LightbulbIcon />
			<div className="wcpay-component-tip-box__content">{ children }</div>
		</div>
	);
};

export default TipBox;
