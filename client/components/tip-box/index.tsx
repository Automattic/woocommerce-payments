/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import LightbulbIcon from 'components/icons/lightbulb';
import './style.scss';

interface Props {
	color: 'purple' | 'blue' | 'gray';
	className?: string;
}
const TipBox: React.FC< Props > = ( { color, className, children } ) => {
	return (
		<div
			className={ classNames(
				'wcpay-component-tip-box',
				color,
				className
			) }
		>
			<LightbulbIcon />
			<div className="wcpay-component-tip-box__content">{ children }</div>
		</div>
	);
};

export default TipBox;
