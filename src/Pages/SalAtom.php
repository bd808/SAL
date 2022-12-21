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

namespace Bd808\Sal\Pages;

use Bd808\Sal\Page;

/**
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2015 Bryan Davis and contributors.
 */
class SalAtom extends Page {
	protected function handleGet( $project ) {
		$params = [
			'project' => $project,
			'query' => null,
			'page' => 0,
			'items' => 50,
			'date' => null,
		];
		$ret = $this->logs->search( $params );

		$this->view->set( 'project', $project );
		$this->view->set( 'results', $ret );

		$this->response->headers->set( 'Content-Type', 'application/atom+xml' );

		$this->render( 'sal.atom' );
	}
}
