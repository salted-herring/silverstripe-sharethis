<?php

/**
 * @author romain [at] sunnys side up .co.nz + nicolaas [at] sunny side up . co .nz
 * @inspiration: https://github.com/tylerkidd/silverstripe-twitter-feed/
 * @funding: MSO Design (www.msodesign.com)
 *
 **/

class MyTwitter extends Object {

	private static $singleton = null;

	/**
	 * returns a DataObjetSet of the last $count tweets.
	 * - saves twitter feed to dataobject
	 *
	 * @param String $username (e.g. mytwitterhandle)
	 * @param Int $count - number of tweets to retrieve at any one time
	 * @return DataObjectSet | Null
	 */
	public static function last_statuses($username, $count = 1) {
		$sessionName = "MyTwitterFeeds$username".date("Ymdh");
		if(Session::get($sessionName)){
			//do nothing
		}
		else {
			Session::set($sessionName, 1);
			if( ! self::$singleton) {
				self::$singleton = new MyTwitter($username, $count);
			}
			$dataObjectSet = self::$singleton->TwitterFeed($username, $count);
			if($dataObjectSet && $dataObjectSet->count()) {
				foreach($dataObjectSet as $tweet) {
					if(!DataObject::get_one("MyTwitterData", "\"TwitterID\" = '".$tweet->ID."'")) {
						$myTwitterData = new MyTwitterData();
						$myTwitterData->TwitterID = $tweet->ID;
						$myTwitterData->Title = $tweet->Title;
						$myTwitterData->Date = $tweet->Date;
						$myTwitterData->write();
					}
				}
			}
		}
		return DataObject::get("MyTwitterData", "\"Hide\" = 0", null, null, "0, $count");
	}

	private static $twitter_consumer_key = "";
		public static function set_twitter_consumer_key($s) {self::$twitter_consumer_key = $s;}
		protected static function get_twitter_consumer_key() {return self::$twitter_consumer_key;}

	private static $twitter_consumer_secret = "";
		public static function set_twitter_consumer_secret($s) {self::$twitter_consumer_secret = $s;}
		protected static function get_twitter_consumer_secret() {return self::$twitter_consumer_secret;}

	private static $titter_oauth_token = "";
		public static function set_titter_oauth_token($s) {self::$titter_oauth_token = $s;}
		protected static function get_titter_oauth_token() {return self::$titter_oauth_token;}

	private static $titter_oauth_token_secret = "";
		public static function set_titter_oauth_token_secret($s) {self::$titter_oauth_token_secret = $s;}
		protected static function get_titter_oauth_token_secret() {return self::$titter_oauth_token_secret;}

	private static $twitter_config = array(
		'include_entities' => 'true',
		'include_rts' => 'true'
	);
		public static function set_twitter_config($a) {self::$twitter_config = $a;}
		protected static function get_twitter_config() {return self::$twitter_config;}

	/**
	 * retries latest tweets from Twitter
	 *
	 * @param String $username (e.g. mytwitterhandle)
	 * @param Int $count - number of tweets to retrieve at any one time
	 * @return DataObjectSet | Null
	 */
	public function TwitterFeed($username, $count = 5){
		//check settings are available
		$requiredSettings = array("twitter_consumer_key", "twitter_consumer_secret", "titter_oauth_token", "titter_oauth_token");
		foreach($requiredSettings as $setting) {
			if(empty(self::$setting)) {
				user_error(" you must set MyTwitter::$setting", E_USER_NOTICE);
				return null;
			}
		}
		require_once(Director::baseFolder().'/'.SS_SHARETHIS_DIR.'/third_party/twitter_oauth/TwitterOAuthConsumer.php');
		$connection = new TwitterOAuth(
			self::$twitter_consumer_key,
			self::$twitter_consumer_secret,
			self::$titter_oauth_token,
			self::$titter_oauth_token_secret
		);
		$config = self::$twitter_config;
		$config['screen_name'] = $username;
		$tweets = $connection->get('statuses/user_timeline', $config);

		$tweetList = new DataObjectSet();

		if(count($tweets) > 0 && !isset($tweets->error)){
			$i = 0;
			foreach($tweets as $tweet){
				if(++$i > $count) break;
				$date = new SS_Datetime();
				$date->setValue(strtotime($tweet->created_at));
				$text = htmlentities($tweet->text, ENT_NOQUOTES, $encoding = null, $doubleEncode = false);
				if($tweet->entities && $tweet->entities->urls){
					foreach($tweet->entities->urls as $url){
						$text = str_replace($url->url, '<a href="'.$url->url.'" class="external">'.$url->url.'</a>',$text);
					}
				}
				$tweetList->push(
					new ArrayData(array(
						'ID' => $tweet->id_str,
						'Title' => $text,
						'Date' => $date
					))
				);
			}
		}
		return $tweetList;
	}
}

class MyTwitterData extends DataObject {

	static $db = array(
		"Date" => "SS_Datetime",
		"TwitterID" => "Varchar(64)",
		"Title" => "Varchar",
		"Hide" => "Boolean"
	);

	static $indexes = array(
		"TwitterID" => true
	);

	static $default_sort = "\"Date\" DESC";

}
