<?php
/**
 * This file is part of bd808's sal application
 * Copyright (C) 2015  Bryan Davis and contributors
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Bd808\Sal;

use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Ids;
use Elastica\Query\Range;
use Elastica\Query\SimpleQueryString;
use Elastica\Query\Term;
use Elastica\ResultSet;
use Elastica\Search;
use Psr\Log\LoggerInterface;

/**
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2015 Bryan Davis and contributors.
 */
class Logs {
	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		Client $client, LoggerInterface $logger = null
	) {
		$this->client = $client;
		$this->logger = $logger ?: new \Psr\Log\NullLogger();
	}

	/**
	 * Run a search.
	 *
	 * @param Query $q
	 * @return ResultSet
	 */
	protected function doSearch( Query $q ) {
		$search = new Search( $this->client );
		$search->addIndex( 'sal' );
		$search->setQuery( $q );
		return $search->search();
	}

	/**
	 * Get a list of all projects.
	 * @return array
	 */
	public function getProjects() {
		$agg = new Terms( 'projects' );
		$agg->setField( 'project.keyword' );
		$agg->setSize( 10000 );
		$agg->setShardSize( 10000 );
		$agg->setOrder( '_term', 'asc' );
		$query = new Query();
		$query->addAggregation( $agg );
		$res = $this->doSearch( $query )->getAggregation( 'projects' );
		return array_map( static function ( $b ) {
			return $b['key'];
		}, $res['buckets'] );
	}

	/**
	 * Get a log
	 *
	 * @param string $id
	 * @return ResultSet
	 */
	public function getLog( $id ) {
		$ids = new Ids();
		$ids->setIds( $id );
		$query = new Query( $ids );
		$query->setFrom( 0 )
			->setSize( 1 );
		return $this->doSearch( $query );
	}

	/**
	 * Search for logs
	 *
	 * @param array $params Search parameters:
	 *   - project: Project to find logs for
	 *   - query: Elasticsearch simple query string
	 *   - items: Number of results to return per page
	 *   - page: Page of results to return (0-index)
	 * @return ResultSet
	 */
	public function search( array $params = [] ) {
		$params = array_merge( [
			'project' => 'production',
			'query' => null,
			'items' => 20,
			'page' => 0,
			'date' => null,
		], $params );

		$filters = new BoolQuery();

		if ( $params['query'] !== null ) {
			$filters->addMust( new SimpleQueryString(
				$params['query'], [ 'message', 'nick' ]
			) );
		}
		if ( $params['date'] !== null ) {
			$filters->addMust( new Range(
				'@timestamp', [ 'lte' => "{$params['date']}||/d" ]
			) );
		}

		$query = new Query( $filters );
		if ( $params['project'] !== '__all__' ) {
			$query->setPostFilter(
				new Term( [ 'project.keyword' => $params['project'] ] )
			);
		}
		$query->setFrom( $params['page'] * $params['items'] )
			->setSize( $params['items'] )
			->setSort( [ '@timestamp' => [ 'order' => 'desc' ] ] );
		return $this->doSearch( $query );
	}
}
