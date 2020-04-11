About
-----

The Search API Solr Legacy module allows to connect to older/unsupported Solr
versions.
It is not guaranteed to be supported for a long time but should enabled an
easier migration to recent Solr versions throughout 2020.


Compatibility
-------------

Search API Solr 3.x was designed for Solr 6 and newer. Nevertheless Search API
Solr Legacy manages to provide most of the features for Solr 4 and 5, too.

There're only a few incompatible and therefore not supported features you need
to be aware of. If possible the UI has been adjusted accordingly. But some
features could still be configured and will then lead to runtime errors or
unexpected results.
In a multilingual setup, all languages are supported but some aren't working as
good compared to Solr 6 and above.
But over all you get more features compared to Search API Solr 1.x and Seach API
Solr Multilingual 1.x.

Solr 5 doesn't support ...
* suggesters
* suggesters based autocomplete

Solr 4 doesn't support ...
* suggesters
* suggesters based autocomplete
* spellcheck based autocomplete
* Search API Location
* Date Range field type and processor
