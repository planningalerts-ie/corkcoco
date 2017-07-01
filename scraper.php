<?php
// This is a template for a PHP scraper on morph.io (https://morph.io)
// including some code snippets below that you should find helpful

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';
date_default_timezone_set('Europe/Dublin');
$date_format = 'Y-m-d';

$council_comment_url = 'https://onlinesubmissions.corkcoco.ie/';


// Read in a page
$html = scraperwiki::scrape("https://mapalerts.corkcoco.ie/en/alerts");

// Collect alert mailer URLs to $targets
$dom = new simple_html_dom();
$dom->load($html);
$targets = array();

foreach ($dom->find("table th a[style='color: #590f56 !important;']") as $item) {
  if (stristr($item->href,'planning-alert')) {
    $targets[] .= 'https://mapalerts.corkcoco.ie' . $item->href;
  }
}
unset($dom,$html);

// Collect KML embedded in those URLs
$kmls = array();
foreach ($targets as $target) {
    $fetch = file_get_contents($target);
    $kml =  simplexml_load_file(getKML($fetch));

    foreach ($kml->Document->Folder->Placemark as $item) {
        $council_reference = str_replace("File Ref: ","",$item->name);
        echo "Found $council_reference\n";

        $point = explode(',',$item->Point->coordinates);
        $lat = $point[1];
        $lng = $point[0];
        
        $blob = (string)$item->description;
        $blobparser = new simple_html_dom();
        $blobparser->load($blob);
       
        $statuspath = $blobparser->find('ul',0)->find('li',5);
        $status = trim(html_entity_decode(str_replace('<B>Status: </B>','',$statuspath->plaintext)),ENT_QUOTES);
        if (stristr('Decision Made Invalid Application Closed',$status)) {
            # exit this loop if old/unwanted application
            break; 
        }
        $receivedpath = $blobparser->find('ul',0)->find('li',6);
        echo $receivedpath->plaintext . "\n";
        $date_received = date($date_format,strtotime(trim(html_entity_decode(str_replace('<B>Application Received: </B>','',$receivedpath->plaintext)),ENT_QUOTES)));
        $date_scraped = date($date_format);
        $on_notice_from = $date_received;
        $on_notice_to = date($date_format,(strtotime($on_notice_from) + 3024000)); # 35 days
        $addresspath = $blobparser->find('ul',0)->find('li',2);
        $address = trim(html_entity_decode(str_replace('<B>Development Address: </B>','',$addresspath->plaintext)),ENT_QUOTES);
        $descriptionpath = $blobparser->find('ul',0)->find('li',3);
        $description = trim(html_entity_decode(str_replace('<B>Development Description: </B>','',$descriptionpath->plaintext)),ENT_QUOTES);
        
        # http://maps.corkcoco.ie/planningenquirylitev3/Default.aspx?FullFileNumber=18a-175461&FromList=true
        $info_url = 'http://maps.corkcoco.ie/planningenquirylitev3/Default.aspx?FullFileNumber=18a-' . str_replace('/','',str_replace('/0','/',$council_reference)) . '&FromList=true';
        
        $application = array(
                'council_reference' => $council_reference,
                'address' => $address,
                'lat' => $lat,
                'lng' => $lng,
                'description' => $description,
                'info_url' => $info_url,
                'comment_url' => $council_comment_url,
                'date_scraped' => $date_scraped,
                'date_received' => $date_received,
                'on_notice_from' => $on_notice_from,
                'on_notice_to' => $on_notice_to
            );
        print_r($application);
        die();
        $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
        if (sizeof($existingRecords) == 0) {
            # print_r ($application);
            scraperwiki::save(array('council_reference'), $application);
        } else {
            print ("Skipping already saved record " . $application['council_reference'] . "\n");
        }
    }    
}

echo "....done.\n";



function getKML($html) {
    $tempA = explode("mapalerter.ie\/maie\/kml\/",$html);
    $tempB = explode('"]',$tempA[1]);
    #print_r($tempA);
    return 'http://www.mapalerter.ie/maie/kml/' . $tempB[0];
}

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
