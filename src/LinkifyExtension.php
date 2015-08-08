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

/**
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2015 Bryan Davis and contributors.
 */
class LinkifyExtension extends \Twig_Extension {

	/**
	 * @var array $mappings
	 */
	protected $mappings;

	/**
	 * @param array $mappings regex => [href, text]
	 */
	public function __construct( array $mappings ) {
		$this->mappings = $mappings;
	}

	public function getName() {
		return 'linkify';
	}

	public function getFilters() {
		return array(
			new \Twig_SimpleFilter(
				'linkify', array( $this, 'linkifyFilterCallback' ),
				array( 'is_safe' => array( 'html' ) )
			),
		);
	}

	public function linkifyFilterCallback( $text ) {
		$text = preg_replace(
			array_keys( $this->mappings ),
			array_map( function( $v ) {
				return "<a href=\"{$v[0]}\" target=\"_blank\">{$v[1]}</a>";
			}, array_values( $this->mappings ) ),
			$text
		);
		return $text;
	}
}
