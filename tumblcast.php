<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// the ID of the Tumblr account to use
$tumblr_id = 'eskimobros';

// Podcast settings. Most of these can be inferred from the API response.
$podcast = array(
	'author' => 'The Eskimo Brothers',
	'title' => 'The Eskimo Brothers Podcast',
	'link' => '',
	'description' => '',
	'subtitle' => '',
	'summary' => '',
	'email' => 'podcast@eskimobros.net',
	'image' => 'http://dl.dropbox.com/u/1599823/eskimo_bros_itunes.jpg',
	'category' => 'Comedy',
	'keywords' => 'eskimo brothers',
	'explicit' => 'yes',
	'language' => 'en-us',
);

// fetch and ectract data from the Tumblr API
$url = 'http://'.$tumblr_id.'.tumblr.com/api/read/json';
$response = trim(file_get_contents($url));
$data = json_decode(str_replace('var tumblr_api_read = ', '', trim($response, ';')), 1);

// fail if we got an unreadable response
// this happens more often than it should: http://tumblruptime.icodeforlove.com/
if ( !$data ) {
	die('Invalid response from Tumblr API!');
}

/**
 * First set the Podcast data
 *
 */

// if no author is set, use the Tumblr name
if ( !$podcast['author'] ) {
	$podcast['author'] = $data['tumblelog']['title'];
}

// if no title is set, use the Tumblr name for tha too
if ( !$podcast['title'] ) {
	$podcast['title'] = $data['tumblelog']['title'];
}

// if no description is set, use the Tumblr description
if ( !$podcast['description'] ) {
	$podcast['description'] = $data['tumblelog']['description'];
}

// if no summary is set, use the long description
if ( !$podcast['summary'] ) {
	$podcast['summary'] = $podcast['description'];
}

// if we don't have a link, use the Tumblr URL
if ( !$podcast['link'] ) {
	if ( $data['tumblelog']['cname'] ) {
		$podcast['link'] = 'http://'.$data['tumblelog']['cname'];
	}
	else {
		$podcast['link'] = 'http://'.$tumblr_id.'.tumblr.com';
	}
}


/**
 * Now build the episode list
 *
 */
$items = array();

foreach ( $data['posts'] as $post ) {
	// continue if this is an audio post and we can parse out the audio file URL
	if ( $post['type'] == 'audio' && preg_match('/\?audio_file=(.*)&color=/', $post['audio-player'], $matches) ) {
		
		// follow the Tumblr redirect to fetch the actual URL and file size for this mp3
		// note that this only seems to work with external audio files
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $matches[1]);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		$result = curl_exec($ch);
		$enclosure = curl_getinfo($ch);
		curl_close($ch);
		
		// build item data
		$items[] = array(
			'title' => (isset($post['id3-title']) ? $post['id3-title'] : 'Untitled'),
			'link' => $post['url'],
			'description' => $post['audio-caption'],
			'date' => $post['date'],
			'enclosure_url' => $enclosure['url'],
			'enclosure_length' => $enclosure['download_content_length'],
			'enclosure_content_type' => $enclosure['content_type'],
			// if Tumblr exposed the AlbumArtURL to the API, we'd use that instead
			'image' => $podcast['image'],
			'author' => (isset($post['id3-artist']) ? $post['id3-artist'] : $podcast['author']),
			'subtitle' => $post['audio-caption'],
			'summary' => $post['audio-caption'],
			// no good way to get this without download the audio file, so we'll just do without
			'duration' => '',
			// combine the post tags with the podcast keywords
			'keywords' => $podcast['keywords'].' '.(isset($post['tags']) ? implode(' ', $post['tags']) : ''),
		);
	}
}

/**
 * Sanitize a string for XML output
 *
 * @param string $str 
 * @return string
 */
function he($str) {
	return str_replace('&nbsp;', ' ', htmlentities(strip_tags($str), ENT_COMPAT, 'UTF-8', FALSE));
}

/**
 * Ok, now let's print the RSS feed...
 *
 */
header('content-type: application/rss+xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';

?>

<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0">
<channel>
	<title><?php echo he($podcast['title']); ?></title>
	<link><?php echo he($podcast['link']); ?></link>
	<description><?php echo he($podcast['description']); ?></description>
	<language><?php echo he($podcast['language']); ?></language>
	<copyright>&#xA9; <?php echo date('Y').' '.he($podcast['author']); ?></copyright>
	
	<itunes:subtitle><?php echo he($podcast['subtitle']); ?></itunes:subtitle>
	<itunes:author><?php echo he($podcast['author']); ?></itunes:author>
	<itunes:owner>
		<itunes:email><?php echo he($podcast['email']); ?></itunes:email>
		<itunes:name><?php echo he($podcast['author']); ?></itunes:name>
	</itunes:owner>	
	<itunes:image href="<?php echo he($podcast['image']); ?>" />
	<itunes:category text="<?php echo he($podcast['category']); ?>" />
	<itunes:explicit><?php echo he($podcast['explicit']); ?></itunes:explicit>
	<itunes:keywords><?php echo he($podcast['keywords']); ?></itunes:keywords>
	<itunes:summary><?php echo he($podcast['summary']); ?></itunes:summary>
			
<?php foreach ( $items as $item ): ?>
	<item>
		<title><?php echo he($item['title']); ?></title>
		<link><?php echo he($item['link']); ?></link>
		<description><?php echo he($item['description']); ?></description>
		<guid><?php echo he($item['link']) ?></guid>
		<pubDate><?php echo date('D, d M Y g:i:s O', strtotime($item['date'])); ?></pubDate>
		<enclosure url="<?php echo he($item['enclosure_url']); ?>" length="<?php echo he($item['enclosure_length']); ?>" type="<?php echo he($item['enclosure_content_type']) ?>" />
		<image>
			<url><?php echo he($item['image']); ?></url>
		</image>
		<itunes:author><?php echo he($item['author']); ?></itunes:author>
		<itunes:subtitle><?php echo he($item['subtitle']); ?></itunes:subtitle> 
		<itunes:summary><?php echo he($item['summary']); ?></itunes:summary>
		<itunes:duration><?php echo he($item['duration']); ?></itunes:duration>
		<itunes:keywords><?php echo he($item['keywords']); ?></itunes:keywords>
	</item>
<?php endforeach; ?>

</channel>
</rss>
