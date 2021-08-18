/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import { flatten } from 'lodash';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import { FileUploadControl } from './file-upload';

/* If description is an array, separate with newline elements. */
const expandHelp = ( description ) => {
	return Array.isArray( description )
		? flatten(
				description.map( ( line, i ) => [ line, <br key={ i } /> ] )
		  )
		: description;
};

export const DisputeEvidenceForm = ( props ) => {
	const {
		fields,
		evidence,
		onChange,
		onFileChange,
		onFileRemove,
		onSave,
		readOnly,
	} = props;

	if ( ! fields || ! fields.length ) {
		return null;
	}

	const composeDefaultControlProps = ( field ) => ( {
		label: field.label,
		value: evidence[ field.key ] || '',
		onChange: ( value ) => onChange( field.key, value ),
		disabled: readOnly,
		help: expandHelp( field.description ),
	} );

	const composeFileUploadProps = ( field ) => {
		const fileName =
			( evidence.metadata && evidence.metadata[ field.key ] ) || '';
		const isLoading =
			evidence.isUploading &&
			( evidence.isUploading[ field.key ] || false );
		const error =
			evidence.uploadingErrors &&
			( evidence.uploadingErrors[ field.key ] || '' );
		const isDone = ! isLoading && 0 < fileName.length;
		const accept = '.pdf, image/png, image/jpeg';
		return {
			field,
			fileName,
			accept,
			onFileChange,
			onFileRemove,
			disabled: readOnly,
			isLoading,
			isDone,
			error,
			help: expandHelp( field.description ),
		};
	};

	const composeFieldControl = ( field ) => {
		switch ( field.type ) {
			case 'file':
				return (
					<FileUploadControl
						key={ field.key }
						{ ...composeFileUploadProps( field ) }
					/>
				);
			case 'text':
				return (
					<TextControl
						key={ field.key }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
			case 'date':
				return (
					<TextControl
						key={ field.key }
						type={ 'date' }
						max={ moment().format( 'YYYY-MM-DD' ) }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
			default:
				return (
					<TextareaControl
						key={ field.key }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
		}
	};

	const evidenceSections = fields.map( ( section ) => {
		return (
			<Card size="large" key={ section.key }>
				<CardHeader>{ section.title }</CardHeader>
				<CardBody>
					{ section.description && <p>{ section.description }</p> }
					{ section.fields.map( composeFieldControl ) }
				</CardBody>
			</Card>
		);
	} );

	const confirmMessage = __(
		"Are you sure you're ready to submit this evidence? Evidence submissions are final.",
		'woocommerce-payments'
	);
	const handleSubmit = () =>
		window.confirm( confirmMessage ) && onSave( true );

	return (
		<>
			{ evidenceSections }
			{ readOnly ? null : (
				<Card size="large">
					<CardBody>
						<p>
							{ __(
								// eslint-disable-next-line max-len
								"When you submit your evidence, we'll format it and send it to the cardholder's bank, then email you once the dispute has been decided.",
								'woocommerce-payments'
							) }
						</p>
						<p>
							<strong>
								{ __(
									'Evidence submission is final.',
									'woocommerce-payments'
								) }
							</strong>{ ' ' }
							{ __(
								'You can also save this evidence for later instead of submitting it immediately.',
								'woocommerce-payments'
							) }{ ' ' }
							<strong>
								{ __(
									'We will automatically submit any saved evidence at the due date.',
									'woocommerce-payments'
								) }
							</strong>
						</p>
					</CardBody>
					<CardFooter>
						{ /* Use wrapping div to keep buttons grouped together. */ }
						<div>
							<Button isPrimary onClick={ handleSubmit }>
								{ __(
									'Submit evidence',
									'woocommerce-payments'
								) }
							</Button>
							<Button
								isSecondary
								onClick={ () => onSave( false ) }
							>
								{ __(
									'Save for later',
									'woocommerce-payments'
								) }
							</Button>
						</div>
					</CardFooter>
				</Card>
			) }
		</>
	);
};
