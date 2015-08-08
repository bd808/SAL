SAL server
==========

Display log messages from IRC.

Wikimedia Tool Labs
-------------------

This service is currently running in [Wikimedia Tool Labs][] as the [sal][]
tool. It uses an Elasticsearch server hosted in the [stashbot][] project of
[Wikimedia Labs][]. The stashbot project uses a [Logstash][] server and its
[irc input plugin][] to collect messages from various IRC channels. The
Logstash instance uses some custom rules to look for messages in the IRC
channels that start with `!log` and adds them to a special Elasticsearch
index.


Credits
-------
Favicon from http://glyphicons.com/ (CC-BY 3.0)


License
-------
[GNU GPLv3+](//www.gnu.org/copyleft/gpl.html "GNU GPLv3+")


---
[Wikimedia]: https://wikimediafoundation.org/wiki/Home
[Elasticsearch]: https://www.elastic.co/products/elasticsearch
[Wikimedia Tool Labs]: https://wikitech.wikimedia.org/wiki/Help:Tool_Labs
[sal]: https://tools.wmflabs.org/sal
[stashbot]: https://wikitech.wikimedia.org/wiki/Nova_Resource:Stashbot
[Wikimedia Labs]: https://wikitech.wikimedia.org/wiki/Help:FAQ
[Logstash]: https://www.elastic.co/products/logstash
[irc input plugin]: https://www.elastic.co/guide/en/logstash/current/plugins-inputs-irc.html
