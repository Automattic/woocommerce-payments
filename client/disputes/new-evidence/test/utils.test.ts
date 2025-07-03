/**
 * External dependencies
 */
import { formatFileSize, getFileExtension, formatFileNameWithSize } from '../utils';

describe( 'File utility functions', () => {
	describe( 'formatFileSize', () => {
		it( 'should format bytes correctly', () => {
			expect( formatFileSize( 0 ) ).toBe( '0B' );
			expect( formatFileSize( 1024 ) ).toBe( '1.0KB' );
			expect( formatFileSize( 1536 ) ).toBe( '1.5KB' );
			expect( formatFileSize( 1048576 ) ).toBe( '1.0MB' );
			expect( formatFileSize( 3145728 ) ).toBe( '3.0MB' );
		} );
	} );

	describe( 'getFileExtension', () => {
		it( 'should extract file extensions correctly', () => {
			expect( getFileExtension( 'document.pdf' ) ).toBe( '.pdf' );
			expect( getFileExtension( 'image.jpg' ) ).toBe( '.jpg' );
			expect( getFileExtension( 'file.PNG' ) ).toBe( '.PNG' );
			expect( getFileExtension( 'noextension' ) ).toBe( '' );
			expect( getFileExtension( 'multiple.dots.in.name.txt' ) ).toBe( '.txt' );
		} );
	} );

	describe( 'formatFileNameWithSize', () => {
		it( 'should format short filenames without truncation', () => {
			const result = formatFileNameWithSize( 'document.pdf', 3145728 );
			expect( result ).toBe( 'document.pdf (3.0mb)' );
		} );

		it( 'should truncate long filenames at 25 characters', () => {
			const result = formatFileNameWithSize( 
				'very_long_filename_that_exceeds_twenty_five_characters.pdf', 
				5242880 
			);
			expect( result ).toBe( 'very_long_filename_that_e... .pdf (5.0mb)' );
		} );

		it( 'should handle filenames without extensions', () => {
			const result = formatFileNameWithSize( 'filename_without_extension', 2097152 );
			expect( result ).toBe( 'filename_without_extensio... (2.0mb)' );
		} );

		it( 'should handle filenames with multiple dots', () => {
			const result = formatFileNameWithSize( 'file.name.with.multiple.dots.jpg', 1048576 );
			expect( result ).toBe( 'file.name.with.multiple.d... .jpg (1.0mb)' );
		} );

		it( 'should handle very short filenames', () => {
			const result = formatFileNameWithSize( 'a.txt', 512000 );
			expect( result ).toBe( 'a.txt (500.0kb)' );
		} );

		it( 'should handle exact 25 character filenames without truncation', () => {
			const result = formatFileNameWithSize( 'exactly_twenty_five_chars.pdf', 1572864 );
			expect( result ).toBe( 'exactly_twenty_five_chars.pdf (1.5mb)' );
		} );

		it( 'should handle 26 character filenames with truncation', () => {
			const result = formatFileNameWithSize( 'exactly_twenty_six_chars.pdf', 2097152 );
			expect( result ).toBe( 'exactly_twenty_six_chars.pdf (2.0mb)' );
		} );

		it( 'should handle files with decimal sizes', () => {
			const result = formatFileNameWithSize( 'test.jpg', 1572864 );
			expect( result ).toBe( 'test.jpg (1.5mb)' );
		} );

		it( 'should handle zero byte files', () => {
			const result = formatFileNameWithSize( 'empty.txt', 0 );
			expect( result ).toBe( 'empty.txt (0.0kb)' );
		} );

		it( 'should format files less than 1 MB in KB', () => {
			const result = formatFileNameWithSize( 'small.txt', 512000 );
			expect( result ).toBe( 'small.txt (500.0kb)' );
		} );

		it( 'should format files exactly 1 MB in MB', () => {
			const result = formatFileNameWithSize( 'exactly_1mb.txt', 1048576 );
			expect( result ).toBe( 'exactly_1mb.txt (1.0mb)' );
		} );

		it( 'should format very small files in KB', () => {
			const result = formatFileNameWithSize( 'tiny.txt', 1024 );
			expect( result ).toBe( 'tiny.txt (1.0kb)' );
		} );
	} );
} ); 