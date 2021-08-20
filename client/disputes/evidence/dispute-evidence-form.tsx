/**
 * External dependencies
 */
import * as React from 'react';
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
import type { Section, Field } from './fields';

/* If description is an array, separate with newline elements. */
const expandHelp = ( description: string | string[] ) => {
	return Array.isArray( description )
		? flatten(
				description.map( ( line, i ) => [ line, <br key={ i } /> ] )
		  )
		: description;
};

type Evidence = {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
	isUploading: Record< string, boolean >;
	metadata: Record< string, string >;
	uploadingErrors: Record< string, string >;
};

export type FormProps = {
	fields: Section[];
	evidence: Evidence;
	onChange: ( arg0: string, arg1: unknown ) => void;
	onFileChange: ( key: string, file?: Blob ) => void;
	onFileRemove: ( key: string ) => void;
	onSave: ( arg0: boolean ) => void;
	readOnly: boolean;
	isSavingEvidence: boolean;
};

export const DisputeEvidenceForm = ( props: FormProps ): JSX.Element | null => {
	const {
		fields,
		evidence,
		onChange,
		onFileChange,
		onFileRemove,
		onSave,
		readOnly,
		isSavingEvidence,
	} = props;

	if ( ! fields || ! fields.length ) {
		return null;
	}

	const composeDefaultControlProps = ( field: Field ) => ( {
		label: field.label,
		value:
			( evidence[
				field.key
			] as string ) /* we know this will be a string, but it's difficult to represent in types */ ||
			'',
		onChange: ( value: unknown ) => onChange( field.key, value ),
		disabled: readOnly,
		help: expandHelp( field.description ?? '' ),
	} );

	const composeFileUploadProps = ( field: Field ) => {
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
			help: expandHelp( field.description ?? '' ),
		};
	};

	const composeFieldControl = ( field: Field ) => {
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
								isBusy={ isSavingEvidence }
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
