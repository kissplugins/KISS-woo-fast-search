<?php
/**
 * Search Strategy Selector
 *
 * Selects the best available search strategy based on priority.
 * Tries strategies in order until one succeeds.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Search_Strategy_Selector {

	/**
	 * Registered strategies
	 *
	 * @var array
	 */
	protected $strategies = array();

	/**
	 * Register a strategy
	 *
	 * @param Hypercart_Search_Strategy $strategy Strategy instance
	 * @return void
	 */
	public function register( $strategy ) {
		if ( ! $strategy instanceof Hypercart_Search_Strategy ) {
			return;
		}

		$this->strategies[] = $strategy;

		// Sort by priority (highest first)
		usort(
			$this->strategies,
			function ( $a, $b ) {
				return $b->get_priority() - $a->get_priority();
			}
		);
	}

	/**
	 * Select best available strategy
	 *
	 * @return Hypercart_Search_Strategy|null Best strategy or null if none available
	 */
	public function select() {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->is_available() ) {
				return $strategy;
			}
		}

		return null;
	}

	/**
	 * Get all available strategies
	 *
	 * @return array Available strategies
	 */
	public function get_available() {
		$available = array();

		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->is_available() ) {
				$available[] = $strategy;
			}
		}

		return $available;
	}

	/**
	 * Get strategy by name
	 *
	 * @param string $name Strategy name
	 * @return Hypercart_Search_Strategy|null Strategy or null if not found
	 */
	public function get_by_name( $name ) {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->get_name() === $name ) {
				return $strategy;
			}
		}

		return null;
	}
}

