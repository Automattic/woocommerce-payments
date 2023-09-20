<?php
/**
 * This sniff prohibits the use of add_action and add_filter in __construct.
 */

namespace WCPay\CodingStandards\Sniffs;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class DisallowHooksInConstructorSniff implements Sniff {
	private $forbiddenFunctions = [
		'add_action',
		'add_filter',
	];

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array<int>
	 */
	public function register() {
		return [ \T_FUNCTION ];
	}

	/**
	 * Processes the sniff if one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The current file being checked.
	 * @param int  $stackPtr  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Check that we're looking at the __construct function.
		$namePtr = $phpcsFile->findNext( T_STRING, $stackPtr + 1 );
		$nameToken = $tokens[ $namePtr ];
		if ( $nameToken['content'] !== '__construct' ) {
			return;
		}

		// Retrieve the current token.
		$token = $tokens[ $stackPtr ];

		// Check the current token's scope.
		$scopeOpener = $token['scope_opener'];
		$scopeCloser = $token['scope_closer'];

		// For every string token in the scope...
		for ( $i = $scopeOpener; $i < $scopeCloser; $i++ ) {
			if ( $tokens[ $i ]['type'] !== 'T_STRING' ) {
				continue;
			}
			// ...check if it's one of the forbidden functions.
			$currentToken        = $tokens[ $i ];
			$currentTokenContent = $currentToken['content'];
			if ( in_array( $currentTokenContent, $this->forbiddenFunctions ) ) {
				$phpcsFile->addError(
					"Usage of $currentTokenContent in __construct() is not allowed",
					$i,
					'WCPay.CodingStandards.DisallowHooksInConstructor',
					$token['content']
				);
			}
		}
	}
}
