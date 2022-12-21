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

use Wikimedia\SimpleI18n\I18nContext;
use Wikimedia\SimpleI18n\JsonCache;
use Wikimedia\Slimapp\AbstractApp;
use Wikimedia\Slimapp\Config;
use Wikimedia\Slimapp\Mailer;
use Wikimedia\Slimapp\ParsoidClient;
use Wikimedia\Slimapp\TwigExtension;

/**
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2015 Bryan Davis and contributors.
 */
class App extends AbstractApp {

	/**
	 * Apply settings to the Slim application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureSlim( \Slim\Slim $slim ) {
		$slim->config( [
			'parsoid.url' => Config::getStr( 'PARSOID_URL',
				'http://parsoid-lb.eqiad.wikimedia.org/enwiki/'
			),
			'parsoid.cache' => Config::getStr( 'CACHE_DIR',
				"{$this->deployDir}/data/cache"
			),
			'es.url' => Config::getStr( 'ES_URL', 'http://127.0.0.1:9200/' ),
			'es.user' => Config::getStr( 'ES_USER', null ),
			'es.password' => Config::getStr( 'ES_PASSWORD', null ),
		] );

		$slim->configureMode( 'production', static function () use ( $slim ) {
			$slim->config( [
				'debug' => false,
				'log.level' => Config::getStr( 'LOG_LEVEL', 'INFO' ),
			] );

			// Install a custom error handler
			$slim->error( static function ( \Exception $e ) use ( $slim ) {
				$errorId = substr( session_id(), 0, 8 ) . '-' .
					substr( uniqid(), -8 );
				$slim->log->critical( $e->getMessage(), [
					'exception' => $e,
					'errorId' => $errorId,
				] );
				$slim->view->set( 'errorId', $errorId );
				$slim->render( 'error.html' );
			} );
		} );

		$slim->configureMode( 'development', static function () use ( $slim ) {
			$slim->config( [
				'debug' => true,
				'log.level' => Config::getStr( 'LOG_LEVEL', 'DEBUG' ),
				'view.cache' => false,
			] );
		} );
	}

	/**
	 * Configure inversion of control/dependency injection container.
	 *
	 * @param \Slim\Helper\Set $container IOC container
	 */
	protected function configureIoc( \Slim\Helper\Set $container ) {
		$container->singleton( 'i18nCache', static function ( $c ) {
			return new JsonCache(
				$c->settings['i18n.path'], $c->log
			);
		} );

		$container->singleton( 'i18nContext', static function ( $c ) {
			return new I18nContext(
				$c->i18nCache, $c->settings['i18n.default'], $c->log
			);
		} );

		$container->singleton( 'mailer',  static function ( $c ) {
			return new Mailer(
				[ 'Host' => $c->settings['smtp.host'] ],
				$c->log
			);
		} );

		$container->singleton( 'parsoid', static function ( $c ) {
			return new ParsoidClient(
				$c->settings['parsoid.url'],
				$c->settings['parsoid.cache'],
				$c->log
			);
		} );

		$container->singleton( 'logs', static function ( $c ) {
			$settings = [
				'url' => $c->settings['es.url'],
			];
			if ( $c->settings['es.user'] !== '' ) {
				$creds = base64_encode(
					$c->settings['es.user'] . ':' .
					$c->settings['es.password']
				);
				$settings['headers'] = [
					'Authorization' => "Basic {$creds}",
				];
			}
			return new Logs( new \Elastica\Client( $settings ), $c->log );
		} );

		// TODO: figure out where to send logs
	}

	/**
	 * Configure view behavior.
	 *
	 * @param \Slim\View $view Default view
	 */
	protected function configureView( \Slim\View $view ) {
		$view->parserOptions = [
			'charset' => 'utf-8',
			'cache' => $this->slim->config( 'view.cache' ),
			'debug' => $this->slim->config( 'debug' ),
			'auto_reload' => true,
			'strict_variables' => false,
			'autoescape' => true,
		];

		// Install twig parser extensions
		$view->parserExtensions = [
			new \Slim\Views\TwigExtension(),
			new TwigExtension( $this->slim->parsoid ),
			new \Wikimedia\SimpleI18n\TwigExtension( $this->slim->i18nContext ),
			new \Twig_Extension_Debug(),
			new LinkifyExtension( [
				// Gerrit change-id
				'/(?<=^|\s)\b(I[0-9a-f]{6,})\b(?=\s|:|,|$)/' => [
					'https://gerrit.wikimedia.org/r/#/q/$1,n,z', '$1'
				],
				// Git commit hash
				'/(?<=^|\s|\(|\[)\b([0-9a-f]{7,})\b(?=\s|:|,|\)|\]|$)/' => [
					'https://gerrit.wikimedia.org/r/#/q/$1,n,z', '$1'
				],
				// Gerrit patch
				'/\b([Gg]errit[:|](\d+))\b/' => [
					'https://gerrit.wikimedia.org/r/#/c/$2', '$1'
				],
				// Phab task
				'#(?<!/)\b(T\d+)\b#' => [
					'https://phabricator.wikimedia.org/$1', '$1'
				],
				// Bugzilla bug
				'/\b([Bb]ugzilla[:|](\d+))\b/' => [
					'https://bugzilla.wikimedia.org/show_bug.cgi?id=$2', '$1'
				],
				// SVN revisions
				'/(?<=^|\s)\br(\d+)\b(?=\s|:|,|$)/' => [
					'https://www.mediawiki.org/wiki/Special:Code/MediaWiki/$1',
					'$0'
				],
				// Raw url
				'#(?<=^|\s)<?(https?://\S+)>?(?=\s|$)#' => [ '$1', '$0' ],
			] ),
		];

		// Set default view data
		$view->replace( [
			'app' => $this->slim,
			'i18nCtx' => $this->slim->i18nContext,
		] );
	}

	/**
	 * Configure routes to be handled by application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureRoutes( \Slim\Slim $slim ) {
		$slim->group( '/',
			static function () use ( $slim ) {
				App::template( $slim, 'about' );

				$slim->get( 'projects', static function () use ( $slim ) {
					$page = new Pages\Projects( $slim );
					$page->setI18nContext( $slim->i18nContext );
					$page->setLogs( $slim->logs );
					$page();
				} )->name( 'projects' );

				$slim->get( 'log/:id', static function ( $id ) use ( $slim ) {
					$page = new Pages\Log( $slim );
					$page->setI18nContext( $slim->i18nContext );
					$page->setLogs( $slim->logs );
					$page( $id );
				} )->name( 'log' );

				$slim->get( '(:project)', static function ( $project = 'production' ) use ( $slim ) {
					$page = new Pages\Sal( $slim );
					$page->setI18nContext( $slim->i18nContext );
					$page->setLogs( $slim->logs );
					$page( $project );
				} )->name( 'SAL' );

				$slim->get( 'atom/(:project)', static function ( $project = 'production' ) use ( $slim ) {
					$page = new Pages\SalAtom( $slim );
					$page->setI18nContext( $slim->i18nContext );
					$page->setLogs( $slim->logs );
					$page( $project );
				} )->name( 'SAL (Atom)' );
			}
		); // end group '/'

		$slim->notFound( static function () use ( $slim ) {
			$slim->render( '404.html' );
		} );
	} // end configureRoutes
}
