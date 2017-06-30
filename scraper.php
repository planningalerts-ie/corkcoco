<?
// This is a template for a PHP scraper on morph.io (https://morph.io)
// including some code snippets below that you should find helpful

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';

// Read in a page
$html = scraperwiki::scrape("https://mapalerts.corkcoco.ie/en/alerts");

// Collect alert mailer URLs to $targets
$dom = new simple_html_dom();
$dom->load($html);
$targets = array();

foreach ($dom->find("table th a[style='color: #590f56 !important;']") as $item)) {
  if (stristr($item->href,'planning-alert') {
    $targets[] .= $item->href;
  }
}
unset($dom);

print_r($targets);



// // Write out to the sqlite database using scraperwiki library
// scraperwiki::save_sqlite(array('name'), array('name' => 'susan', 'occupation' => 'software developer'));
//
// // An arbitrary query against the database
// scraperwiki::select("* from data where 'name'='peter'")

// You don't have to do things with the ScraperWiki library.
// You can use whatever libraries you want: https://morph.io/documentation/php
// All that matters is that your final data is written to an SQLite database
// called "data.sqlite" in the current working directory which has at least a table
// called "data".
?>
