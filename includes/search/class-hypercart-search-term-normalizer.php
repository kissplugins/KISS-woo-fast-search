<?php
/**
 * Search Term Normalizer
 *
 * Normalizes and analyzes search terms to determine search strategy.
 * Extracts name parts, detects email patterns, sanitizes input.
 *
 * This is the SINGLE SOURCE OF TRUTH for term normalization.
 * All search strategies use this to ensure consistent behavior.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Search_Term_Normalizer {

	/**
	 * Normalize a search term
	 *
	 * @param string $term Raw search term from user input
	 * @return array Normalized term data
	 */
	public function normalize( $term ) {
		$term = trim( (string) $term );

		$normalized = array(
			'original'          => $term,
			'sanitized'         => sanitize_text_field( $term ),
			'is_email'          => is_email( $term ),
			'is_partial_email'  => $this->is_partial_email( $term ),
			'name_parts'        => $this->split_name( $term ),
			'is_numeric'        => is_numeric( $term ),
			'length'            => strlen( $term ),
		);

		return $normalized;
	}

	/**
	 * Split name into parts (first, last)
	 *
	 * Handles:
	 * - "John Smith" → ['John', 'Smith']
	 * - "John" → ['John']
	 * - "John Q. Smith" → ['John', 'Smith'] (ignores middle)
	 *
	 * @param string $term Search term
	 * @return array Name parts (empty if not a name search)
	 */
	protected function split_name( $term ) {
		// Don't split emails or numbers
		if ( $this->is_partial_email( $term ) || is_numeric( $term ) ) {
			return array();
		}

		// Split on whitespace
		$parts = preg_split( '/\s+/', $term );
		$parts = array_values( array_filter( array_map( 'trim', (array) $parts ) ) );

		// Single word - could be first OR last name
		if ( count( $parts ) === 1 ) {
			return array( $parts[0] );
		}

		// Multiple words - take first and last (ignore middle initials)
		if ( count( $parts ) >= 2 ) {
			return array(
				'first' => $parts[0],
				'last'  => $parts[ count( $parts ) - 1 ],
			);
		}

		return array();
	}

	/**
	 * Check if term looks like a partial email
	 *
	 * @param string $term Search term
	 * @return bool True if contains @ or looks email-ish
	 */
	protected function is_partial_email( $term ) {
		// Contains @ symbol
		if ( false !== strpos( $term, '@' ) ) {
			return true;
		}

		// Looks like email prefix (alphanumeric with dots/dashes)
		if ( preg_match( '/^[a-zA-Z0-9._-]+$/', $term ) && strlen( $term ) >= 3 ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate normalized term meets minimum requirements
	 *
	 * @param array $normalized Normalized term data
	 * @return bool True if valid for search
	 */
	public function is_valid( $normalized ) {
		// Must have at least 2 characters
		if ( $normalized['length'] < 2 ) {
			return false;
		}

		// Must have sanitized value
		if ( empty( $normalized['sanitized'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get search type hint
	 *
	 * Helps strategies decide which fields to search
	 *
	 * @param array $normalized Normalized term data
	 * @return string Search type: 'email', 'name', 'numeric', 'general'
	 */
	public function get_search_type( $normalized ) {
		if ( $normalized['is_email'] ) {
			return 'email';
		}

		if ( $normalized['is_partial_email'] ) {
			return 'partial_email';
		}

		if ( $normalized['is_numeric'] ) {
			return 'numeric';
		}

		if ( ! empty( $normalized['name_parts'] ) && isset( $normalized['name_parts']['first'] ) ) {
			return 'full_name';
		}

		if ( ! empty( $normalized['name_parts'] ) ) {
			return 'single_name';
		}

		return 'general';
	}
}

