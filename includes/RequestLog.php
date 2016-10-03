<?php

namespace CirrusSearch;

use User;

/**
 * Represents logging information for a single request made between php and
 * elasticsearch. Information returned from here goes through the RequestLogger
 * class and gets logged to the CirrusSearchRequestSet channel for later
 * processing in analytics platforms.
 */
interface RequestLog {
	/**
	 * Called when the network request is started
	 */
	function start();

	/**
	 * Called when the network request has finished
	 */
	function finish();

	/**
	 * @return string The type of query that was performed
	 */
	function getQueryType();

	/**
	 * @return string Get the raw psr-3 compliant request description
	 */
	function getDescription();

	/**
	 * @return string Get the request description, formatted as per psr-3 guidelines
	 *  with self::getLogVariables()
	 */
	function formatDescription();

	/**
	 * @return int|null The number of ms php spend waiting for the request,
	 *  or null if the request has not finished yet.
	 */
	function getTookMs();

	/**
	 * @return int The number of ms elasticsearch reported spending on the request,
	 *  or -1 if no request was made (such as cached responses).
	 */
	function getElasticTookMs();

	/**
	 * @return bool Was this query answered without talking to elasticsearch?
	 */
	function isCachedResponse();

	/**
	 * @return array Various information about the request. The exact set of returned keys can vary,
	 *  but should generally conform to what is expected in RequestLogger::buildRequestSetLog().
	 */
	function getLogVariables();
}
