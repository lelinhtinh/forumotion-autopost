<?php

include('inc/bbcode.php');

// Tùy chỉnh
$domain = 'http://ctrlv.123.st';
$forumid = 26;
$username = 'bot';
$password = 'baivong';
$useragent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:36.0) Gecko/20100101 Firefox/36.0 Waterfox/36.0';
$cookiepath = 'cookie.txt';
$urlpath = 'url.txt';
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
function getElementByClass($doc, $classname)
{
    $xpath = new DomXPath($doc);
    return $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
}

/**
 * Thay thế cho curl_setopt CURLOPT_FOLLOWLOCATION
 * @param $ch
 * @return mixed
 */
function curl_follow_exec($ch)
{
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code == 301 || $http_code == 302) {
        preg_match('/(Location:|URI:)(.*?)\n/', $data, $matches);
        if (isset($matches[2])) {
            $redirect_url = trim($matches[2]);
            if ($redirect_url !== '') {
                curl_setopt($ch, CURLOPT_URL, $redirect_url);
                return curl_follow_exec($ch);
            }
        }
    }
    return $data;
}

/**
 * Đăng nhập
 * @return bool
 */
function login()
{
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
    curl_follow_exec($ch);
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
function post($title, $bbcode)
{
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
    curl_setopt($po, CURLOPT_RETURNTRANSFER, 1);
    $posting = curl_exec($po);
    curl_close($po);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($posting);

    // URL trả về sau khi đăng bài thành công
    $newtopic_url = getElementByClass($dom, 'message');
    $newtopic_url = $newtopic_url->item(1)->getElementsByTagName('a');
    $newtopic_url = $newtopic_url->item(0)->getAttribute('href');

    if (substr($newtopic_url, 0, 13) === '/viewtopic?t=') {
        if ($showlog) echo '<p style="color: green">Post success ' . date('h:i:s') . ' <a target="_blank" href="' . $domain . $newtopic_url . '">' . $newtopic_url . '</a></p>';
    } else {
        if ($showlog) echo '<p style="color: red">Post error ' . date('h:i:s') . '</p>';
    }
}

/**
 * Xử lý khi lấy tin thành công
 */
function complete()
{
    global $showlog;
    if ($showlog) echo '<p style="color: blue">Complete ' . date('h:i:s') . '</p>';
}

/**
 * Lấy nội dung tin để đăng bài
 */
function get_post_entry()
{
    global $links, $links_used, $count_get, $urlpath, $showlog;

    $link = $links[$count_get];

    if (file_exists($urlpath)) {
        $links_used = explode("\n", file_get_contents($urlpath));
    } else {
        file_put_contents($urlpath, '');
    }

    if (trim(file_get_contents('url.txt')) === '' || !is_numeric(array_search($link, $links_used))) {

        if ($showlog) echo '<p style="color: green">' . $count_get . ' | ' . date('h:i:s') . ' | ' . $link . '</p>';

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

        $title = $title->item(0)->textContent;
        $excerpt = $excerpt->item(0)->textContent;
        $content = $dom->saveHTML($content->item(0));

        $bbcode = '[b]' . $excerpt . '[/b]' . "\n\n" . html2bbcode($content);

        if ($showlog) echo '<h2>' . $title . '</h2>';
        if ($showlog) echo '<textarea style="width: 98%; height: 200px;">' . $bbcode . '</textarea>';

        post($title, $bbcode);
        sleep(10);

        $count_get += 1;
        array_push($links_used, $link);

        file_put_contents($urlpath, implode("\n", $links_used));

        if ($count_get < count($links)) {

            if ($showlog) echo '<p>Waiting...</p>';

            get_post_entry();

        } else {
            complete();
        }

    } else {

        if ($showlog) echo '<p style="color: red">' . $count_get . ' | ' . date('h:i:s') . ' | ' . $link . '</p>';

        $count_get += 1;

        if ($count_get < count($links)) {
            get_post_entry();
        } else {
            complete();
        }

    }
}

/**
 * Tải RSS và lấy link của tin
 */
if (login()) {
    if ($showlog) echo '<p style="color: green">Login success ' . date('h:i:s') . '</p>';

    $docs = new DOMDocument();
    $docs->load('http://tintuc.vn/rss/suc-khoe.rss');

    $items = $docs->getElementsByTagName('item');

    foreach ($items as $item) {
        array_push($links, trim($item->childNodes->item(1)->textContent));
    }

    if ($showlog) echo '<p>RSS loaded ' . date('h:i:s') . '</p>';

    get_post_entry();
} else {
    if ($showlog) echo '<p style="color: red">Login error ' . date('h:i:s') . '</p>';
}