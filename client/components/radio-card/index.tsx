/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface Props {
	name: string;
	selected: string;
	options: {
		label: string;
		value: string;
		icon: React.ReactNode;
		content: React.ReactNode;
	}[];
	onChange: ( value: string ) => void;
	className?: string;
}
const RadioCard: React.FC< Props > = ( {
	name,
	selected,
	options,
	onChange,
	className,
} ) => {
	const handleChange = ( event: React.ChangeEvent< HTMLInputElement > ) =>
		onChange( event.target.value );

	return (
		<>
			{ options.map( ( { label, icon, value, content } ) => {
				const checked = value === selected;

				return (
					<label
						key={ value }
						className={ classNames(
							'wcpay-component-radio-card',
							{ checked },
							className
						) }
					>
						<div className="wcpay-component-radio-card__label">
							<input
								type="radio"
								name={ name }
								value={ value }
								checked={ !! checked }
								onChange={ handleChange }
							/>
							{ label }
							{ icon }
						</div>
						{ checked && content }
					</label>
				);
			} ) }
		</>
	);
};

export default RadioCard;
