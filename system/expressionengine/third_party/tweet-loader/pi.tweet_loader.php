<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
    'pi_name'         => 'Tweet Loader',
    'pi_version'      => '1.0',
    'pi_author'       => 'Boxhead',
    'pi_description'  => 'Appends the most recent tweets to the database'
);

class Tweet_loader {
	private $config = array(
		'user_id' 				=> '',
		'user_handle' 			=> '',
		'channel_id' 			=> '',
		'consumer_key' 			=> '',
		'consumer_secret'		=> '',
		'oauth_token' 			=> '',
		'oauth_token_secret' 	=> '',

		'fieldIds' 		=> array(
			'tweet_text'	=> 	'',
			'id' 			=>	'',
			'tweet_link' 	=>	'',
		),
	);

	public $group_id;
	public $member_id;

	public function __construct() {
		if (!$this->configValid()) {
			return false;
		}

		// If we have new content returned from the API call...
		if ($content = $this->returnTweets())
		{
			$this->enableWritePermissions();
			$this->writeTweets($content);
			$this->resetPermissions();
		}
	}

	function enableWritePermissions() {
		// Get current user data
		$user_data = ee()->session->userdata;

		// Store permissions
		$this->group_id = $user_data['group_id'];
		$this->member_id = $user_data['member_id'];

		// Set to temporary super admin
		ee()->session->userdata['group_id'] = 1;
		ee()->session->userdata['member_id'] = 1;
	}

	function resetPermissions() {
		// Reset permissions
		ee()->session->userdata['group_id'] = $this->group_id;
		ee()->session->userdata['member_id'] = $this->member_id;
	}

	function isEmpty($item) {
		return empty($item);
	}

	function configValid() {
		// Check everything we need is set
		foreach ($this->config as $item) {
			// If this is an array
			if (is_array($item)) {
				// Check each sub item
				foreach ($item as $subItem) {
					if ($this->isEmpty($subItem)) {
						return false;
					}
				}
			// Otherwise
			} else {
				// Check the item
				if ($this->isEmpty($item)) {
					return false;
				}
			}
		}

		return true;
	}

	function getConfig($item) {
		if (is_array($item)) {
			return $this->config[$item[0]][$item[1]];
		}

		return $this->config[$item];
	}

	function returnTweets() {
		// Instantiate the Twitter wrapper object
		require_once dirname(__FILE__) . '/twitter/twitteroauth.php';

		$connection = new TwitterOAuth($this->getConfig('consumer_key'), $this->getConfig('consumer_secret'), $this->getConfig('oauth_token'), $this->getConfig('oauth_token_secret'));;

		$params = array(
			// User Id
			'user_id'		=> 	$this->getConfig('user_id'),
			// Don't include retweets
			'include_rts'	=>	false,
			// Twitter by default returns tweets from last week. Return max possible
			'count'			=>	'200',
		);

		// Get most recent tweet in EE db's id number
		$query = 'SELECT field_id_' . $this->getConfig(['fieldIds', 'id']) . ' FROM exp_channel_titles AS ct JOIN exp_channel_data AS cd ON ct.entry_id = cd.entry_id WHERE ct.channel_id = ' . $this->getConfig('channel_id') . ' ORDER BY ct.entry_date DESC LIMIT 1';

		$result = ee()->db->query($query);

		// If a tweet was found, use the id as the starting point
		if ($result->num_rows > 0) {
			$params['since_id'] = $result->row()->{'field_id_' . $this->getConfig(['fieldIds', 'id'])};
		}

		// Fire call for tweets more recent than our most recent one
		$content = $connection->get("statuses/user_timeline", $params);

	    // If we have 0 results, exit
	    $count = count($content);

	    if (!$count > 0) {
	    	return false;
	    }

		return $content;
	}

	function writeTweets($content) {
		// Instantiate EE Channel Entries API
		ee()->load->library('api');
		ee()->api->instantiate('channel_entries');

		foreach($content as $tweet)
		{
			// If either the tweet, entry date or id (in either form) are missing, move on to the next item
			if (!isset($tweet->text) || !isset($tweet->created_at) || !isset($tweet->id) || !isset($tweet->id_str)) 
			{
				continue;
			}

			// Set variables from tweet object
			$unformatted_text = $text = $tweet->text;
			$id   = $tweet->id;
			$id_str   = $tweet->id_str;
			$date = strtotime($tweet->created_at);

			// Escape any quotes
			$text = htmlspecialchars($text, ENT_QUOTES);

			// Add the font-reset class to any hash tags (check for beginning of string or part of escaped special character) as our font doens't have a hash tag symbol
			$text = preg_replace('/^#|[^&+]#/', ' <span class="font-reset">#</span>', $text);
			
			// If there are any user mentions in the tweet, insert the relevant html here
			if (!empty($tweet->entities->user_mentions))
			{
				foreach ($tweet->entities->user_mentions as $user_mention)
				{
					$screen_name = $user_mention->screen_name;
					$text = str_replace('@' . $screen_name, '<a href="http://twitter.com/' . $screen_name . '" target="_blank">' . '@' . $screen_name . '</a>', $text);
				}
			}

			// If there are any links in the tweet, insert the relevant html here
			if (!empty($tweet->entities->urls))
			{
				foreach ($tweet->entities->urls as $link) {
					$url = $link->url;
					$expanded_url = $link->expanded_url;
					$text = str_replace($url, '<a href="' . $expanded_url . '" target="_blank">' . $url . '</a>', $text);
				}
			}

			// If there are any media contents, these won't show here and will just output a messy link - remove them
			if (!empty($tweet->entities->media))
			{
				foreach($tweet->entities->media as $media)
				{
					$url = $media->url;
					$text = str_replace($url, '', $text);
				}
			}

			$tweet_link = 'http://twitter.com/' . $this->getConfig('user_handle') . '/status/' . $id_str;

			// Create array of data to write to db
			$data = array(
				'title'														=> 	substr($unformatted_text, 0, 30),
				'entry_date' 												=> 	$date,
				'field_id_' . $this->getConfig(['fieldIds', 'tweet_text']) 	=> 	$text,
				'field_id_' . $this->getConfig(['fieldIds', 'id']) 			=>	$id,
				'field_id_' . $this->getConfig(['fieldIds', 'tweet_link']) 	=>	$tweet_link,
			);

			// Write the entry to the database
			ee()->api_channel_entries->save_entry($data, intval($this->getConfig('channel_id')));
		}
	}
}
