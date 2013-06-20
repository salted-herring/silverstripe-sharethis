<?php
/**
 * FROM: http://www.acornartwork.com/blog/2010/04/19/tutorial-facebook-rss-feed-parser-in-pure-php/
 * EXAMPLE:
 *		//Run the function with the url and a number as arguments
 *		$fb = new TheFaceBook_communicator();
 *		$dos = $fb->fetchFBFeed('http://facebook.com/feeds/status.php?id=xxxxxx&viewer=xxxxxx&key=xxxxx&format=rss20', 3);
 *		//Print Facebook status updates
 *		echo '<ul class="fb-updates">';
 *			 foreach ($dos as $do) {
 *					echo '<li>';
 *					echo '<span class="update">' .$do->Description. '</span>';
 *					echo '<span class="date">' .$do->Date. '</span>';
 *					echo '<span class="link"><a href="' .$do->Link. '">more</a></span>';
 *					echo '</li>';
 *			 }
 *		echo '</ul>';
 *
 *  SEE README on getting facebook URL for RSS Feed.
 *
 *
 **/


class FacebookFeed_Page extends DataObject {

	static $db = array(
		'Title' => 'Varchar(244)',
		'RSSURL' => 'Varchar(255)'
	);

	static $has_many = array(
		'Items' => 'FacebookFeed_Item'
	);

	static $many_many = array(
		'Pages' => 'SiteTree'
	);

	/**
	 * Standard SS Variable
	 * @var String
	 **/
	public static $singular_name = "Facebook Feed";
		function i18n_singular_name() { return _t("FacebookFeed.SINGULAR", "Facebook Feed");}

	/**
	 * Standard SS Variable
	 * @var String
	 **/
	public static $plural_name = "Facebook Feeds";
		function i18n_plural_name() { return _t("FacebookFeed.PLURAL", "Facebook Feeds");}

	public function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->addFieldToTab(
			"Root.Main",
			new LiteralField(
				"HowToFindRSS",
				"<p>
				The facebook RSS link format is like this https://www.facebook.com/feeds/page.php?format=rss20&id=XXX
				To find the id value (denoted here as XXX), you can follow these steps :</p>
				<ol>
					<li>Open a new tab and open <a href=\"http://www.facebook.com\" target=\"_blank\">facebook</a></li>
					<li>Find your page (e.g. https://www.facebook.com/EOSAsia)</li>
					<li>Note the name (e.g. EOSAsia)</li>
					<li>Go to <a href=\"http://findmyfacebookid.com\" target=\"_blank\">http://findmyfacebookid.com</a></li>
					<li>Enter http://www.facebook.com/EOSAsia</li>
					<li>You'll get the answer (e.g. 357864420974239)</li>
					<li>Add this ID to the link - like this: https://www.facebook.com/feeds/page.php?format=rss20&id=357864420974239</li>
					<li>This is the link you need to add in the field above</li>
				</ol>"
			)
		);
		$fields->addFieldToTab(
			"Root.Pages",
			new TreeMultiSelectField("Pages", "Show on", "SiteTree")
		);
		$pages = $this->Pages();
		if($pages && $pages->count()) {
			$links = array();
			foreach($pages as $page) {
				$links[] = "<li><a href=\"".$page->Link()."\">".$page->Title."</a></li>";
			}
			if(count($links)) {
				$fields->addFieldToTab(
					"Root.Pages",
					new LiteralField(
						"LinksToCheck",
						"<p>
							Choose the links below to view your facebook feed:
						<ol>
							".implode("", $links)."
						</ol>"
					)
				);
			}
		}
		return $fields;
	}

	public static function all_for_one_page($page, $limit = 10){
		if($page instanceOf SiteTree) {
			$pageID = $page->ID;
		}
		else {
			$pageID = $page;
		}
		$feedIDs = array();
		$sql = "
			SELECT \"FacebookFeed_Page_Pages\".\"FacebookFeed_PageID\"
			FROM \"FacebookFeed_Page_Pages\"
			WHERE \"FacebookFeed_Page_Pages\".\"SiteTreeID\" = $pageID";
		$rows = DB::query($sql);
		if($rows) {
			foreach($rows as $row) {
				$feedIDs[$row["FacebookFeed_PageID"]] = $row["FacebookFeed_PageID"];
			}
		}
		if(count($feedIDs)) {
			return DataObject::get(
				"FacebookFeed_Item",
				"\"FacebookFeed_PageID\" IN (".implode(",", $feedIDs).") AND \"Hide\" = 0",
				null,
				"",
				$limit
			);
		}
	}

	public function ShowableItems($limit = 10){
		return $this->getComponents('Items', 'Hide = 0', null, '', $limit);
	}

	public function Fetch($limit = 10){
		if($this->RSSURL) {
			$fb = new FacebookFeed_Item_Communicator();
			$fb->fetchFBFeed($this->RSSURL, $limit, $this->ID);
		}
	}

	function onAfterWrite(){
		parent::onAfterWrite();
		$this->Fetch();
	}

}

class FacebookFeed_Item extends DataObject {

	static $db = array(
		"KeepOnTop" => "Boolean",
		"Hide" => "Boolean",
		"UID" => "varchar(32)",
		"Title" => "varchar(100)",
		"Author" => "Varchar(244)",
		"Description" => "HTMLText",
		"DescriptionWithShortLinks" => "HTMLText",
		"Link" => "Varchar(244)",
		"Date" => "Date"
	);


	static $has_one = array(
		"FacebookFeed_Page" => "FacebookFeed_Page"
	);

	static $summary_fields = array(
		"TitleOrDescription" => "Title",
		"KeepOnTopNice" => "Keep On Top",
		"HideNice" => "Hide",
	);


	static $field_labels = array(
		"FacebookFeed_Page" => "Facebook Feed",
		"DescriptionWithShortLinks" => "Description with short links"
	);


	static $indexes = array(
		"UID" => true
	);

	static $casting = array(
		'TitleOrDescription' => 'Varchar',
		'KeepOnTopNice' => 'Varchar',
		'HideNice' => 'Varchar'
	);

	function canDelete($member = null) {
		return false;
	}

	function canCreate($member = null) {
		return false;
	}

	static $default_sort = "\"Hide\" ASC, \"KeepOnTop\" DESC, \"Date\" DESC";

	function getTitleOrDescription(){
		return strlen($this->Title) > 2 ? $this->Title ."...": substr(strip_tags($this->Description), 0, 100)." ... ";
	}

	function getKeepOnTopNice(){
		if($this->Hide) { return "N/A";}
		return $this->KeepOnTop ? "YES" : "NO";
	}

	function getHideNice(){
		return $this->Hide ? "YES" : "NO";
	}

	protected function myDescriptionWithShortLinks() {
		require_once('simple_html_dom.php');
		$html = str_get_html($this->Description);
		foreach($html->find('text') as $element) {
			if(! in_array($element->parent()->tag, array('a', 'img'))) {
				$element->innertext = preg_replace("#(www(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#ie", "'http://$1$4'", $element->innertext);
				$element->innertext = preg_replace("#((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#ie", "'<a href=\"$1\" target=\"_blank\">click here</a>$4'", $element->innertext);
			}
		}
		return $html;
	}

	function onBeforeWrite(){
		parent::onBeforeWrite();
		if(!$this->DescriptionWithShortLinks) {
			$this->DescriptionWithShortLinks = (string) $this->myDescriptionWithShortLinks();
		}
	}



}


class FacebookFeed_Item_Communicator extends RestfulServer {

	/**
	 *
	 */
	function fetchFBFeed($url, $maxnumber = 1, $facebookFeed_PageID = 0, $timeFormat = 'Y-m-d') {
		require_once('simple_html_dom.php');
	/* The following line is absolutely necessary to read Facebook feeds.
	 * Facebook will not recognize PHP as a browser and therefore won't fetch anything.
	 * So we define a browser here */
		ini_set('user_agent', 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3');
		$updates = @simplexml_load_file($url);  //Load feed with simplexml
		if($updates){
			foreach ( $updates->channel->item as $fbUpdate ) {
				if ($maxnumber == 0) {
					break;
				}
				else {
					$guid = $fbUpdate->guid;
					$uid = substr($guid, -32);
					if(DB::query("SELECT COUNT(\"ID\") FROM \"FacebookFeed_Item\" WHERE \"UID\" = '$uid';")->value() == 0) {
						$desc = $fbUpdate->description;
						//Add www.facebook.com to hyperlinks
						//$desc = urldecode(urldecode(str_replace('href="http://www.facebook.com/l.php?u=', 'href="', $desc)));
						//Converts UTF-8 into ISO-8859-1 to solve special symbols issues
						$desc = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $desc);
						$desc = $this->stripUnsafe($desc);
						//Get status update time
						$pubDate = strtotime($fbUpdate->pubDate);
						$convertedDate = gmdate($timeFormat, $pubDate);  //Customize this to your liking
						//Get link to update
						//Store values in array
						$facebookFeed_Item = new FacebookFeed_Item();
						$facebookFeed_Item->UID = (string) $uid;
						$facebookFeed_Item->Title = (string) $fbUpdate->title;
						$facebookFeed_Item->Date = $convertedDate;
						$facebookFeed_Item->Author = (string) $fbUpdate->author;
						$facebookFeed_Item->Link = (string) $fbUpdate->link;
						$facebookFeed_Item->Description = $this->stripUnsafe((string) $desc);
						$facebookFeed_Item->FacebookFeed_PageID = $facebookFeed_PageID;
						$facebookFeed_Item->write();
						$maxnumber--;
					}
				}
			}
		}
	}

	function stripUnsafe($string) {
    // Unsafe HTML tags that members may abuse
		$unsafe=array(
			'/onmouseover="(.*?)"/is',
			'/onclick="(.*?)"/is',
			'/style="(.*?)"/is',
			'/target="(.*?)"/is',
			'/onunload="(.*?)"/is',
			'/rel="(.*?)"/is',
			'/<a(.*?)>/is',
			'/<\/a>/is'
		);
		$string= preg_replace($unsafe, " ", $string);
		return $string;
	}
}

class FacebookFeed_UpdateTask extends HourlyTask {

	function process() {
		$facebookPages = DataObject::get('FacebookFeed_Page');
		if($facebookPages && $facebookPages->Count()) {
			foreach($facebookPages as $facebookPage) {
				$facebookPage->Fetch();
				DB::alteration_message("Facebook page #{$facebookPage->ID} '$facebookPage->Title' updated", 'changed');
			}
		}
	}
}
