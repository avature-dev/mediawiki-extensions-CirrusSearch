<?php

namespace CirrusSearch;
use Elastica\Exception\PartialShardFailureException;
use Elastica\Exception\ResponseException;
use MediaWiki\Logger\LoggerFactory;
use \Status;
use User;

/**
 * Base class with useful functions for communicating with Elasticsearch.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class ElasticsearchIntermediary {
	/**
	 * @var User|null user for which we're performing this search or null in the case of
	 * requests kicked off by jobs
	 */
	protected $user;
	/**
	 * @var float|null start time of current request or null if none is running
	 */
	private $requestStart = null;
	/**
	 * @var string|null description of the next request to be sent to Elasticsearch or null if not yet decided
	 */
	private $description = null;
	/**
	 * @var int how many millis a request through this intermediary needs to take before it counts as slow.
	 * 0 means none count as slow.
	 */
	private $slowMillis;

	/**
	 * @var array Metrics about a completed search
	 */
	private $searchMetrics = array();

	/**
	 * Constructor.
	 *
	 * @param User|null $user user for which this search is being performed.  Attached to slow request logs.  Note that
	 * null isn't for anonymous users - those are still User objects and should be provided if possible.  Null is for
	 * when the action is being performed in some context where the user that caused it isn't available.  Like when an
	 * action is being performed during a job.
	 * @param float $slowSeconds how many seconds a request through this intermediary needs to take before it counts as
	 * slow.  0 means none count as slow.
	 */
	protected function __construct( User $user = null, $slowSeconds ) {
		$this->user = $user;
		$this->slowMillis = round( 1000 * $slowSeconds );
	}

	/**
	 * Mark the start of a request to Elasticsearch.  Public so it can be called from pool counter methods.
	 *
	 * @param string $description name of the action being started
	 */
	public function start( $description ) {
		$this->description = $description;
		$this->requestStart = microtime( true );
	}

	/**
	 * Log a successful request and return the provided result in a good Status.  If you don't need the status
	 * just ignore the return.  Public so it can be called from pool counter methods.
	 *
	 * @param mixed $result result of the request.  defaults to null in case the request doesn't have a result
	 * @return \Status wrapping $result
	 */
	public function success( $result = null ) {
		$this->finishRequest();
		return Status::newGood( $result );
	}

	/**
	 * Log a failure and return an appropriate status.  Public so it can be called from pool counter methods.
	 *
	 * @param \Elastica\Exception\ExceptionInterface|null $exception if the request failed
	 * @return \Status representing a backend failure
	 */
	public function failure( $exception = null ) {
		$took = $this->finishRequest();
		list( $status, $message ) = $this->extractMessageAndStatus( $exception );
		wfLogWarning( "Search backend error during $this->description after $took.  $message" );
		return $status;
	}

	/**
	 * Get the search metrics we have
	 * @return array
	 */
	public function getSearchMetrics() {
		return $this->searchMetrics;
	}

	/**
	 * Extract an error message from an exception thrown by Elastica.
	 * @param RuntimeException $exception exception from which to extract a message
	 * @return string message from the exception
	 */
	public static function extractMessage( $exception ) {
		if ( !( $exception instanceof ResponseException ) ) {
			return $exception->getMessage();
		}
		if ( $exception instanceof PartialShardFailureException ) {
			$shardStats = $exception->getResponse()->getShardsStatistics();
			$message = array();
			foreach ( $shardStats[ 'failures' ] as $failure ) {
				$message[] = $failure[ 'reason' ];
			}
			return 'Partial failure:  ' . implode( ',', $message );
		}
		return $exception->getElasticsearchException()->getMessage();
	}

	/**
	 * Does this status represent an Elasticsearch parse error?
	 * @param $status Status to check
	 * @return boolean is this a parse error?
	 */
	protected function isParseError( $status ) {
		foreach ( $status->getErrorsArray() as $errorMessage ) {
			if ( $errorMessage[ 0 ] === 'cirrussearch-parse-error' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Log the completion of a request to Elasticsearch.
	 * @return int number of milliseconds it took to complete the request
	 */
	private function finishRequest() {
		if ( !$this->requestStart ) {
			wfLogWarning( 'finishRequest called without staring a request' );
			return;
		}
		// No need to check description because it must be set by $this->start.

		// Build the log message
		$endTime = microtime( true );
		$took = round( ( $endTime - $this->requestStart ) * 1000 );
		$logMessage = "$this->description took $took millis";

		$this->searchMetrics['wgCirrusStartTime'] = $this->requestStart;
		$this->searchMetrics['wgCirrusEndTime'] = $endTime;

		$client = Connection::getClient();
		$query = $client->getLastRequest();
		$result = $client->getLastResponse();
		if ( $result ) {
			$queryData = $query->getData();
			$resultData = $result->getData();
			// Extract the amount of time Elasticsearch reported the last request took if possible.
			if ( isset( $resultData[ 'took' ] ) ) {
				$elasticTook = $resultData[ 'took' ];
				$logMessage .= " and $elasticTook Elasticsearch millis";
				$this->searchMetrics['wgCirrusElasticTime'] = $elasticTook;
			}
			if ( isset( $resultData['hits']['total'] ) ) {
				$logMessage .= ". Found {$resultData['hits']['total']} total results";
			}
			if ( isset( $resultData['hits']['hits'] ) ) {
				$num = count( $resultData['hits']['hits'] );
				$offset = isset( $queryData['from'] ) ? $queryData['from'] : 0;
				$logMessage .= " and returned $num of them starting at $offset";
			}
			if ( isset( $queryData['query']['filtered']['filter']['terms']['namespace'] ) ) {
				$namespaces = $queryData['query']['filtered']['filter']['terms']['namespace'];
				$logMessage .= ' within these namespaces: ' . implode( ', ', $namespaces );
			}
		}
		$request = $client->getLastRequest();

		if ( php_sapi_name() === 'cli' ) {
			$source = 'cli';
		} elseif ( defined( 'MW_API' ) ) {
			$source = 'api';
		} else {
			$source = 'web';
		}
		$logMessage .= ". Requested via $source.";

		// Now log and clear our state.
		LoggerFactory::getInstance( 'CirrusSearchRequests' )->debug( $logMessage );
		if ( $this->slowMillis && $took >= $this->slowMillis ) {
			$logMessage .= $this->user ? ' for ' . $this->user->getName() : '';
			LoggerFactory::getInstance( 'CirrusSearchSlowRequests' )->info( $logMessage );
		}
		$this->requestStart = null;
		return $took;
	}

	private function extractMessageAndStatus( $exception ) {
		if ( !$exception ) {
			return array( Status::newFatal( 'cirrussearch-backend-error' ), '' );
		}

		// Lots of times these are the same as getMessage(), but sometimes
		// they're not. So get the nested exception so we're sure we get
		// what we want. I'm looking at you PartialShardFailureException.
		$message = self::extractMessage( $exception );

		$marker = 'ParseException[Cannot parse ';
		$markerLocation = strpos( $message, $marker );
		if ( $markerLocation !== false ) {
			// The important part of the parse error message comes before the next new line
			// so lets slurp it up and log it rather than the huge clump of error.
			$start = $markerLocation + strlen( $marker );
			$end = strpos( $message, "\n", $start );
			$parseError = substr( $message, $start, $end - $start );
			return array( Status::newFatal( 'cirrussearch-parse-error' ), 'Parse error on ' . $parseError );
		}

		$marker = 'Determinizing';
		$markerLocation = strpos( $message, $marker );
		if ( $markerLocation !== false ) {
			$startOfMessage = $markerLocation;
			$endOfMessage = strpos( $message, ']; nested', $startOfMessage );
			if ( $endOfMessage === false ) {
				$endOfMessage = strpos( $message, '; Determinizing', $startOfMessage );
			}
			$extracted = substr( $message, $startOfMessage, $endOfMessage - $startOfMessage );
			return array( Status::newFatal( 'cirrussearch-regex-too-complex-error' ), $extracted );
		}
		// This is _probably_ a regex syntax error so lets call it that. I can't think of
		// what else would have automatons and illegal argument exceptions. Just looking
		// for the exception won't suffice because other weird things could cause it.
		$seemsToUseRegexes = strpos( $message, 'import org.apache.lucene.util.automaton.*' ) !== false;
		$usesExtraRegex = strpos( $message, 'org.wikimedia.search.extra.regex.SourceRegexFilter' ) !== false;
		$seemsToUseRegexes |= $usesExtraRegex;
		$marker = 'IllegalArgumentException[';
		$markerLocation = strpos( $message, $marker );
		if ( $seemsToUseRegexes && $markerLocation !== false ) {
			$start = $markerLocation + strlen( $marker );
			$end = strpos( $message, "];", $start );
			$syntaxError = substr( $message, $start, $end - $start );
			$errorMessage = 'unknown';
			$position = 'unknown';
			$matches = array();
			if ( preg_match( '/(.+) at position ([0-9]+)/', $syntaxError, $matches ) ) {
				$errorMessage = $matches[ 1 ];
				$position = $matches[ 2 ];
				if ( !$usesExtraRegex ) {
					// The 3 below offsets the .*( in front of the user pattern
					// to make it unanchored.
					$position -= 3;
				}
			} else if ( $syntaxError === 'unexpected end-of-string' ) {
				$errorMessage = 'regex too short to be correct';
			}
			$status = Status::newFatal( 'cirrussearch-regex-syntax-error', $errorMessage, $position );
			return array( $status, 'Regex syntax error:  ' . $syntaxError );
		}
		return array( Status::newFatal( 'cirrussearch-backend-error' ), $message );
	}
}
