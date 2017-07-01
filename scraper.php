<?php
// -------------------------------------------------------------
// Cork County Council planning applications
// John Handelaar 2017-07-01
// -------------------------------------------------------------


require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';
date_default_timezone_set('Europe/Dublin');
$date_format = 'Y-m-d';

$council_comment_url = 'https://onlinesubmissions.corkcoco.ie/';

/*
 Method here is unusual because Cork CoCo planning site is a
 SPECTACULAR PILE OF CRAP which doesn't even work in most web
 browsers.  However the council operates an alerts-by-email
 service.  Some of those alerts contain planning apps, and the
 alert web pages which do, contain an embedded google map 
 containing KML generated for them on an ad-hoc basis. 
 Therefore we're targetting those KML files...
 */

// Read in page listing all current alerts
$html = scraperwiki::scrape("https://mapalerts.corkcoco.ie/en/alerts");

// Collect alert mailer URLs to $targets
$dom = new simple_html_dom();
$dom->load($html);
$targets = array();

// Collect planning-related alerts and ignore everything else
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

        $point = explode(',',$item->Point->coordinates);
        $lat = $point[1];
        $lng = $point[0];
        
	// Almost everything we need is contained in a HTML list buried in a CDATA
	// in the 'description' object. Needs explicit casting to string else PHP
	// is prone to a fart with it
        $blob = (string)$item->description;
        $blobparser = new simple_html_dom();
        $blobparser->load($blob);
       
        $statuspath = $blobparser->find('ul',0)->find('li',5);
        $status = trim(html_entity_decode(str_replace('Status: ','',$statuspath->plaintext)),ENT_QUOTES);
        
        // Not interested in the majority of things in this KML dump.
	// If it's closed, ancient or rejected for invalidity, do nothing
        if (!(stristr('Decision Made Invalid Application Closed',$status))) {
		        
			// Otherwise...
		        echo "Found $council_reference\n";
			$receivedpath = $blobparser->find('ul',0)->find('li',6);
			
			// Convert stringdate to date()
			$date_received = date($date_format,strtotime(trim(html_entity_decode(str_replace('Application Received: ','',$receivedpath->plaintext)),ENT_QUOTES)));
			
			// "Today"
			$date_scraped = date($date_format);
			$on_notice_from = $date_received;
		
			// Add 35 days by casting to timestamp, adding seconds, and casting back again 
		        $on_notice_to = date($date_format,(strtotime($on_notice_from) + 3024000)); # 35 days
			$addresspath = $blobparser->find('ul',0)->find('li',2);
			$address = trim(html_entity_decode(str_replace('Development Address: ','',$addresspath->plaintext)),ENT_QUOTES);
			$descriptionpath = $blobparser->find('ul',0)->find('li',3);
			$description = trim(html_entity_decode(str_replace('Development Description: ','',$descriptionpath->plaintext)),ENT_QUOTES);
			
			# Example URI http://maps.corkcoco.ie/planningenquirylitev3/Default.aspx?FullFileNumber=18a-175461&FromList=true
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

			$existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
			if (sizeof($existingRecords) == 0) {
				# print_r ($application);
				scraperwiki::save(array('council_reference'), $application);
			} else {
				print ("Skipping already saved record " . $application['council_reference'] . "\n");
			}
		}
    }    
}

echo "....done.\n";



function getKML($html) {
    $tempA = explode("mapalerter.ie\/maie\/kml\/",$html);
    $tempB = explode('"]',$tempA[1]);
    return 'http://www.mapalerter.ie/maie/kml/' . $tempB[0];
}

?>
