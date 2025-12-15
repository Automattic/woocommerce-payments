/**
 * External dependencies
 */
import { formatFileNameWithSize } from '../utils';

describe( 'File utility functions', () => {
	describe( 'formatFileNameWithSize', () => {
		it( 'should format short filenames without truncation', () => {
			const result = formatFileNameWithSize( 'document.pdf', 3145728 );
			expect( result.namePart ).toBe( 'document' );
			expect( result.extensionSizePart ).toBe( '.pdf (3MB)' );
		} );

		it( 'should handle long filenames for CSS truncation', () => {
			const result = formatFileNameWithSize(
				'very_long_filename_that_exceeds_twenty_five_characters.pdf',
				5242880
			);
			expect( result.namePart ).toBe(
				'very_long_filename_that_exceeds_twenty_five_characters'
			);
			expect( result.extensionSizePart ).toBe( '.pdf (5MB)' );
		} );

		it( 'should handle filenames without extensions', () => {
			const result = formatFileNameWithSize(
				'filename_without_extension',
				2097152
			);
			expect( result.namePart ).toBe( 'filename_without_extension' );
			expect( result.extensionSizePart ).toBe( ' (2MB)' );
		} );

		it( 'should handle filenames with multiple dots', () => {
			const result = formatFileNameWithSize(
				'file.name.with.multiple.dots.jpg',
				1572864
			);
			expect( result.namePart ).toBe( 'file.name.with.multiple.dots' );
			expect( result.extensionSizePart ).toBe( '.jpg (1.5MB)' );
		} );

		it( 'should handle filenames that are exactly 25 characters', () => {
			const result = formatFileNameWithSize(
				'exactly_twenty_six_chars.pdf',
				1048576
			);
			expect( result.namePart ).toBe( 'exactly_twenty_six_chars' );
			expect( result.extensionSizePart ).toBe( '.pdf (1MB)' );
		} );

		it( 'should handle small files in KB', () => {
			const result = formatFileNameWithSize( 'small_file.txt', 512000 );
			expect( result.namePart ).toBe( 'small_file' );
			expect( result.extensionSizePart ).toBe( '.txt (500KB)' );
		} );

		it( 'should handle very small files with decimal KB', () => {
			const result = formatFileNameWithSize( 'tiny_file.txt', 1536 );
			expect( result.namePart ).toBe( 'tiny_file' );
			expect( result.extensionSizePart ).toBe( '.txt (1.5KB)' );
		} );

		it( 'should handle zero byte files', () => {
			const result = formatFileNameWithSize( 'empty_file.txt', 0 );
			expect( result.namePart ).toBe( 'empty_file' );
			expect( result.extensionSizePart ).toBe( '.txt (0KB)' );
		} );
	} );
} );
