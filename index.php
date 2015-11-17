<?php 

include('inc/bbcode.php');
 
// Tùy chỉnh
$domain = 'http://ctrlv.123.st';
$forumid = 26;
$username = 'bot';
$password = 'baivong';
$useragent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:36.0) Gecko/20100101 Firefox/36.0 Waterfox/36.0';
$cookiepath = __DIR__ . DIRECTORY_SEPARATOR . 'cookie.txt';
$urlpath = __DIR__ . DIRECTORY_SEPARATOR . 'url.txt';
$showlog = true;

// Thông số mặc định
$count_get = 0;
$links = array();
$links_used = array();

/**
 * Lấy phần tử bằng Class
 * @param $doc
 * @param $classname
 * @return DOMNodeList
 */
function getElementByClass($doc, $classname) {
	$xpath = new DomXPath($doc);
	return $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
}

/**
 * Đăng nhập
 * @return bool
 */
function login() {
	global $username, $password, $cookiepath, $domain, $useragent;

	$login = array(
		'username' => $username,
		'password' => $password,
		'login' => 'ok'
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $domain . '/login');
	curl_setopt($ch, CURLOPT_POST, count($login));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $login);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$content = curl_exec($ch);
	curl_close($ch);

	libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHTML($content);

	// Lấy tên thành viên đăng nhập từ link #logout
	$user = $dom->getElementById('logout')->textContent;
	preg_match('/[^\[]+\[([^\]]+)\]/', $user, $matches);
	$user = trim($matches[1]);
	
	return ($user === $username);
}

/**
 * Gửi bài viết vào forumotion
 * @param $title
 * @param $bbcode
 */
function post($title, $bbcode) {
	global $cookiepath, $domain, $useragent, $forumid, $showlog;
	
	$topic = array(
		'mode' => 'newtopic',
		'f' => $forumid,
		'subject' => $title,
		'message' => $bbcode,
		'post' => 'ok'
	);

	$po = curl_init();
	curl_setopt($po, CURLOPT_URL, $domain . '/post');
	curl_setopt($po, CURLOPT_POST, count($topic));
	curl_setopt($po, CURLOPT_POSTFIELDS, $topic);
	curl_setopt($po, CURLOPT_USERAGENT, $useragent);
	curl_setopt($po, CURLOPT_COOKIEJAR, $cookiepath);
	curl_setopt($po, CURLOPT_COOKIEFILE, $cookiepath);
	curl_setopt($po, CURLOPT_HEADER, 1);
	curl_setopt($po, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($po, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($po, CURLOPT_SSL_VERIFYPEER, 0);
	$posting = curl_exec($po);
	curl_close($po);

	libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHTML($posting);

	// URL trả về sau khi đăng bài thành công
	$newtopic_url = getElementByClass($dom, 'message');
	$newtopic_url = $newtopic_url[1] -> getElementsByTagName('a');
	$newtopic_url = $newtopic_url[0] -> getAttribute('href');

	if(substr($newtopic_url, 0, 13) === '/viewtopic?t=') {
		if($showlog) echo '<p style="color: green">Post success <a target="_blank" href="' . $domain . $newtopic_url . '">' . $newtopic_url . '</a></p>';
	} else {
		if($showlog) echo '<p style="color: red">Post error</p>';
	}
}

/**
 * Xử lý khi lấy tin thành công
 */
function complete() {
	global $showlog;
	if($showlog) echo '<p style="color: blue">Complete ' . date('h:i:s') . '</p>';
}

/**
 * Lấy nội dung tin để đăng bài
 */
function get_post_entry() {
	global $links, $links_used, $count_get, $urlpath, $showlog;
	
	$link = $links[$count_get];
	$links_used = explode("\n", file_get_contents($urlpath));
	
	if(trim(file_get_contents('url.txt')) === '' || !is_numeric(array_search($link, $links_used))) {	
	
		if($showlog) echo '<p style="color: green">' . $count_get . ' | ' . date('h:i:s') . ' | ' . $link . '</p>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $link);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
	
		libxml_use_internal_errors(true);
		$dom = new DOMDocument();	
		$dom->loadHTML($result);
			
		$title = $dom->getElementsByTagName('h1');
		$excerpt = getElementByClass($dom, 'news-content-excerpt');
		$content = getElementByClass($dom, 'content-post');
		
		$title = $title[0]->textContent;
		$excerpt = $excerpt[0]->textContent;
		$content = $dom->saveHTML($content[0]);
		
		$bbcode = '[b]' . $excerpt . '[/b]' . "\n\n" . html2bbcode($content);

		if($showlog) echo '<h2>'.$title.'</h2>';
		if($showlog) echo '<textarea style="width: 98%; height: 200px;">' . $bbcode . '</textarea>';

		post($title, $bbcode);
		
		$count_get += 1;
		array_push($links_used, $link);
		
		file_put_contents($urlpath, implode("\n", $links_used));
		
		if($count_get < count($links)) {

			if($showlog) echo '<p>Waiting...</p>';
			
			sleep(10);
			get_post_entry();
			
		} else {
			complete();
		}
	
	} else {

		if($showlog) echo '<p style="color: red">' . $count_get . ' | ' . date('h:i:s') . ' | ' . $link . '</p>';
		
		$count_get += 1;
		
		if($count_get < count($links)) {			
			get_post_entry();
		} else {
			complete();
		}
		
	}
}

/**
 * Tải RSS và lấy link của tin
 */


if(login()) {
	if($showlog) echo '<p style="color: green">Login success</p>';

	$docs = new DOMDocument();
	$docs->load('http://tintuc.vn/rss/suc-khoe.rss');

	$items = $docs->getElementsByTagName('item');

	foreach($items as $item) {
		$link = $item->childNodes[1];
		array_push($links, trim($link->textContent));
	}

	if($showlog) echo '<p>RSS loaded</p>';

	get_post_entry();
} else {
	if($showlog) echo '<p style="color: red">Login error</p>';
}