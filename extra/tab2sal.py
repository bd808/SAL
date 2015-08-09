#!/usr/bin/env python
# -*- coding: utf-8 -*-
# This file is part of bd808's sal application
# Copyright (c) 2015 Bryan Davis and contributors
#
# This program is free software: you can redistribute it and/or modify it
# under the terms of the GNU General Public License as published by the Free
# Software Foundation, either version 3 of the License, or (at your option)
# any later version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
# more details.
#
# You should have received a copy of the GNU General Public License along
# with this program.  If not, see <http://www.gnu.org/licenses/>.
"""
Convert tab separated data dumped from the revision table to json recors
suitable for bulk insert into Elasticsearch.

Tabular data created using an SQL query like:
    SELECT rev_timestamp, rev_comment
    FROM revision
    WHERE rev_page = (
      SELECT page_id
      FROM page
      WHERE page_namespace = 0
        AND page_title = 'Server_Admin_Log'
    )
      AND rev_user = (
        SELECT user_id
        FROM user
        WHERE user_name = 'Labslogbot'
      );
"""

import json
import re
import sys

RE_LINE = re.compile(r'^(?P<ts>\d{14})\t(?P<message>.*) \((?P<nick>[^(]+)\)$')

def parse_line(lines, logpat=RE_LINE):
    groups = (logpat.match(line) for line in lines)
    return (g.groupdict() for g in groups if g)

for rec in parse_line(sys.stdin):
    ts = rec['ts']
    del rec['ts']
    rec['@timestamp'] = '%s-%s-%sT%s:%s:%s.000Z' % (
        ts[0:4], ts[4:6], ts[6:8],
        ts[8:10], ts[10:12], ts[12:14]
    )
    rec['@version'] = 1
    rec['channel'] = '#wikimedia-operations'
    rec['command'] = 'PRIVMSG'
    rec['host'] = '127.0.0.1'
    rec['server'] = 'wikitech.wikimedia.org:443'
    rec['project'] = 'production'
    rec['type'] = 'sal'
    rec['tags'] = ['imported']
    rec['user'] = 'import!~statshbot@127.0.0.1'

    if rec['nick'] == 'logmsgbot':
        (rec['nick'], rec['message']) = rec['message'].split(' ', 1)
    print json.dumps(rec)
