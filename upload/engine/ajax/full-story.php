<?php
/*
=============================================================================
Подгрузка полного содержания новости
=============================================================================
Автор:   ПафНутиЙ
URL:     http://pafnuty.name/
twitter: https://twitter.com/pafnuty_name
google+: http://gplus.to/pafnuty
email:   pafnuty10@gmail.com
=============================================================================
 */

// Всякие обязательные штуки для ajax DLE
@error_reporting(E_ALL^E_WARNING^E_NOTICE);
@ini_set('display_errors', true);
@ini_set('html_errors', false);
@ini_set('error_reporting', E_ALL^E_WARNING^E_NOTICE);

define('DATALIFEENGINE', true);
define('ROOT_DIR', substr(dirname(__FILE__), 0, -12));

define('ENGINE_DIR', ROOT_DIR . '/engine');

include ENGINE_DIR . '/data/config.php';

date_default_timezone_set($config['date_adjust']);

if ($config['http_home_url'] == "") {

	$config['http_home_url'] = explode("engine/ajax/full-story.php", $_SERVER['PHP_SELF']);
	$config['http_home_url'] = reset($config['http_home_url']);
	$config['http_home_url'] = "http://" . $_SERVER['HTTP_HOST'] . $config['http_home_url'];

}

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';
require_once ENGINE_DIR . '/classes/templates.class.php';

dle_session();
$member_id = array();

require_once ENGINE_DIR . '/modules/sitelogin.php';

$user_group = get_vars("usergroup");
if (!$user_group) {
	$user_group = array();
	$db->query("SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC");
	while ($row = $db->get_row()) {
		$user_group[$row['id']] = array();
		foreach ($row as $key => $value) {
			$user_group[$row['id']][$key] = stripslashes($value);
		}
	}

	set_vars("usergroup", $user_group);
	$db->free();
}

$cat_info = get_vars("category");

if (!is_array($cat_info)) {
	$cat_info = array();

	$db->query("SELECT * FROM " . PREFIX . "_category ORDER BY posi ASC");
	while ($row = $db->get_row()) {

		$cat_info[$row['id']] = array();

		foreach ($row as $key => $value) {
			$cat_info[$row['id']][$key] = stripslashes($value);
		}

	}
	set_vars("category", $cat_info);
	$db->free();
}
$template_dir = ROOT_DIR . '/templates/' . $config['skin'];

// Пытаемся получить даные из шаблона с настройками
$presetFile = (!empty($_REQUEST['preset'])) ? $_REQUEST['preset'] : false;
if($presetFile) {
	// Если название шаблона передано, то получаем из него конфиг
	if (file_exists($template_dir . '/' . $presetFile . '.tpl')) {
		// Если файл существует - берём из него контент с настройками
		$preset = file_get_contents($template_dir . '/' . $presetFile . '.tpl');
		$arConf = array();
	} else {
		die('error');
	}

	// Разбиваем полученные из файла настройки по строкам
	$preset = explode("\n", $preset);

	// Пробегаем по массиву и формируем список настроек
	foreach ($preset as $v) {
		$_v = explode('=', $v);
		if (isset($_v[1])) {
			$arConf[trim($_v[0])] = trim($_v[1]);
		}
	}

	// Список разрешенных полей, отбираемых из БД.
	$existFields = array(
		'short_story',
		'full_story',
		'xfields',
		'comm_num',
		'fixed',
		'tags',
	);

	// Поля, которые отбираются из БД в любом случаи
	$_queryFields = array(
		'id',
		'title',
		'date',
		'category',
		'alt_name',
		'approve',
		'autor',
	);

	// Убираем пробелы, на всякий случай
	$arConf['fields'] = str_replace(' ', '', $arConf['fields']);
} else {
	// Если ничего не передано - заберём все поля
	$arConf['fields'] = 'all';
}

if ($arConf['fields'] == 'all') {
	// Если передано all - значит мы хотим получить все поля из таблицы
	$queryFields = '*';
} else {
	// Разбиваем поля на массив
	$_fields = explode(',', $arConf['fields']);

	// Сравниваем со списком разрешенных полей
	foreach ($_fields as $key => $field) {
		if (!in_array($field, $existFields)) {
			// Удаляем лишние поля из массива
			unset($_fields[$key]);
		}
	}

	// Объединяем массивы
	$arQueryFields = array_merge($_queryFields, $_fields);

	// И опять разбиваем, для вставки в запрос.
	$queryFields = implode(', ', $arQueryFields);
}

// Конфиг модуля
$fsCfg = array(
	'template' => !empty($_REQUEST['template']) ? $_REQUEST['template'] : 'ajax/fullstory',
	'cachePrefix' => !empty($arConf['cachePrefix']) ? $arConf['cachePrefix'] : 'full_' . (int) $_REQUEST['newsId'],
	'newsId' => ((int) $_REQUEST['newsId'] > 0) ? (int) $_REQUEST['newsId'] : '0',
	'fields' => $queryFields,
);

// Формируем имя кеша
$cacheName = md5(implode('_', $fsCfg)) . $config['skin'];
$fs = false;
// Пытаемся получить даные из кеша
$fs  = ($config['allow_cache'] && $config['allow_cache'] != 'no') ? dle_cache($fsCfg['cachePrefix'], $cacheName, true) : false;
if (!$fs) {

	// Если ничего нет - работаем
	if (file_exists($template_dir . '/' . $fsCfg['template'] . '.tpl')) {
		// Если файл шаблона есть - работаем

		$tpl = new dle_template();
		$tpl->dir = $template_dir;
		define('TEMPLATE_DIR', $tpl->dir);

		// Подгружаем шаблон
		$tpl->load_template($fsCfg['template'] . '.tpl');

		// Если ID новости больше нуля - работаем
		if ($fsCfg['newsId'] > 0) {
			$perm = 1;
			$allow_list = explode(',', $user_group[$member_id['user_group']]['allow_cats']);

			// Получаем новость
			$row = $db->super_query("SELECT " . $fsCfg['fields'] . " FROM " . PREFIX . "_post LEFT JOIN " . PREFIX . "_post_extras ON (" . PREFIX . "_post.id=" . PREFIX . "_post_extras.news_id) WHERE id = " . $fsCfg['newsId']);

			// Опеределяем возможность доступа к новости
			$options = news_permission($row['access']);
			if ($options[$member_id['user_group']] AND $options[$member_id['user_group']] != 3) {
				$perm = 1;
			}

			if ($options[$member_id['user_group']] == 3) {
				$perm = 0;
			}

			if ($options[$member_id['user_group']] == 1) {
				$user_group[$member_id['user_group']]['allow_addc'] = 0;
			}

			if ($options[$member_id['user_group']] == 2) {
				$user_group[$member_id['user_group']]['allow_addc'] = 1;
			}

			if ($row['id'] AND !$row['approve'] AND $member_id['name'] != $row['autor'] AND !$user_group[$member_id['user_group']]['allow_all_edit']) {
				$perm = 0;
			}

			if ($row['id'] AND $config['no_date'] AND !$config['news_future'] AND !$user_group[$member_id['user_group']]['allow_all_edit']) {

				if (strtotime($row['date']) > $_TIME) {
					$perm = 0;
				}

			}

			// Разбираемся со ссылками на категорию
			if ($config['category_separator'] != ',') {
				$config['category_separator'] = ' ' . $config['category_separator'];
			}

			if (!$row['category']) {
				$my_cat = "---";
				$my_cat_link = "---";
			} else {

				$my_cat = array();
				$my_cat_link = array();
				$cat_list = explode(',', $row['category']);

				if (count($cat_list) == 1) {

					if ($allow_list[0] != "all" and !in_array($cat_list[0], $allow_list)) {
						$perm = 0;
					}

					$my_cat[] = $cat_info[$cat_list[0]]['name'];

					$my_cat_link = get_categories($cat_list[0], $config['category_separator']);

				} else {

					foreach ($cat_list as $element) {

						if ($allow_list[0] != "all" and !in_array($element, $allow_list)) {
							$perm = 0;
						}

						if ($element) {
							$my_cat[] = $cat_info[$element]['name'];
							if ($config['allow_alt_url']) {
								$my_cat_link[] = "<a href=\"" . $config['http_home_url'] . get_url($element) . "/\">{$cat_info[$element]['name']}</a>";
							} else {
								$my_cat_link[] = "<a href=\"$PHP_SELF?do=cat&amp;category={$cat_info[$element]['alt_name']}\">{$cat_info[$element]['name']}</a>";
							}
						}
					}

					$my_cat_link = implode("{$config['category_separator']} ", $my_cat_link);
				}

				$my_cat = implode("{$config['category_separator']} ", $my_cat);
			}
			// Если доступ к новости разрешен и новость есть- работаем
			if ($row['id'] AND $perm) {
				// Определяем IP
				$_IP = get_ip();
				// Определяем категорию
				$category_id = intval($row['category']);
				// Работам с датой новости
				$row['date'] = strtotime($row['date']);
				// Если текста мало
				if ((strlen($row['full_story']) < 13) and (strpos($tpl->copy_template, "{short-story}") === false)) {
					$row['full_story'] = $row['short_story'];
				}

				// Если разрешен счётчик просмотров
				if ($config['allow_read_count']) {
					if ($config['allow_read_count'] == 2) {

						$readcount = $db->super_query("SELECT count(*) as count FROM " . PREFIX . "_read_log WHERE news_id='{$row['id']}' AND ip='{$_IP}'");

						if (!$readcount['count']) {

							if ($config['cache_count']) {
								$db->query("INSERT INTO " . PREFIX . "_views (news_id) VALUES ('{$row['id']}')");
							} else {
								$db->query("UPDATE " . PREFIX . "_post_extras SET news_read=news_read+1 WHERE news_id='{$row['id']}'");
							}

							$db->query("INSERT INTO " . PREFIX . "_read_log (news_id, ip) VALUES ('{$row['id']}', '{$_IP}')");
						}

					} else {

						if ($config['cache_count']) {
							$db->query("INSERT INTO " . PREFIX . "_views (news_id) VALUES ('{$row['id']}')");
						} else {
							$db->query("UPDATE " . PREFIX . "_post_extras SET news_read=news_read+1 WHERE news_id='{$row['id']}'");
						}
					}

				}

				// Получаем ссылку на новость
				if ($config['allow_alt_url'] && $config['allow_alt_url'] != 'no') {
					if (
						($config['version_id'] < 9.6 && $config['seo_type'])
						||
						($config['version_id'] >= 9.6 && ($config['seo_type'] == 1 || $config['seo_type'] == 2))
					) {
						if (intval($data['category']) && $config['seo_type'] == 2) {
							$full_link = $config['http_home_url'] . get_url(intval($data['category'])) . '/' . $data['id'] . '-' . $data['alt_name'] . '.html';
						} else {
							$full_link = $config['http_home_url'] . $data['id'] . '-' . $data['alt_name'] . '.html';
						}
					} else {
						$full_link = $config['http_home_url'] . date('Y/m/d/', $data['date']) . $data['alt_name'] . '.html';
					}
				} else {
					$full_link = $config['http_home_url'] . 'index.php?newsid=' . $data['id'];
				}

				$row['title'] = stripslashes($row['title']);
				$comments_num = $row['comm_num'];

				// Разбираемся с тегами шаблона

				$arTags = array(
					'{comments-num}' => $comments_num,
					'{views}' => $row['news_read'],
					'{category}' => $my_cat,
					'{link-category}' => $my_cat_link,
					'{news-id}' => $row['id'],
					'{full-link}' => $full_link,
				);

				$tpl->set('', $arTags);

				$tpl->set('[full-link]', "<a href=\"" . $full_link . "\">");
				$tpl->set('[/full-link]', "</a>");

				if (date('Ymd', $row['date']) == date('Ymd', $_TIME)) {

					$tpl->set('{date}', $lang['time_heute'] . langdate(", H:i", $row['date']));

				} elseif (date('Ymd', $row['date']) == date('Ymd', ($_TIME - 86400))) {

					$tpl->set('{date}', $lang['time_gestern'] . langdate(", H:i", $row['date']));

				} else {

					$tpl->set('{date}', langdate($config['timestamp_active'], $row['date']));

				}
				$news_date = $row['date'];
				$tpl->copy_template = preg_replace_callback("#\{date=(.+?)\}#i", "formdate", $tpl->copy_template);

				if ($row['fixed']) {

					$tpl->set('[fixed]', "");
					$tpl->set('[/fixed]', "");
					$tpl->set_block("'\\[not-fixed\\](.*?)\\[/not-fixed\\]'si", "");

				} else {

					$tpl->set('[not-fixed]', "");
					$tpl->set('[/not-fixed]', "");
					$tpl->set_block("'\\[fixed\\](.*?)\\[/fixed\\]'si", "");
				}

				if ($comments_num) {

					$tpl->set('[comments]', "");
					$tpl->set('[/comments]', "");
					$tpl->set_block("'\\[not-comments\\](.*?)\\[/not-comments\\]'si", "");

				} else {

					$tpl->set('[not-comments]', "");
					$tpl->set('[/not-comments]', "");
					$tpl->set_block("'\\[comments\\](.*?)\\[/comments\\]'si", "");
				}

				if ($row['votes']) {

					$tpl->set('[poll]', "");
					$tpl->set('[/poll]', "");
					$tpl->set_block("'\\[not-poll\\](.*?)\\[/not-poll\\]'si", "");

				} else {

					$tpl->set('[not-poll]', "");
					$tpl->set('[/not-poll]', "");
					$tpl->set_block("'\\[poll\\](.*?)\\[/poll\\]'si", "");
				}

				if ($row['editdate']) {
					$_DOCUMENT_DATE = $row['editdate'];
				} else {
					$_DOCUMENT_DATE = $row['date'];
				}

				if ($row['view_edit'] and $row['editdate']) {

					if (date(Ymd, $row['editdate']) == date(Ymd, $_TIME)) {

						$tpl->set('{edit-date}', $lang['time_heute'] . langdate(", H:i", $row['editdate']));

					} elseif (date(Ymd, $row['editdate']) == date(Ymd, ($_TIME - 86400))) {

						$tpl->set('{edit-date}', $lang['time_gestern'] . langdate(", H:i", $row['editdate']));

					} else {

						$tpl->set('{edit-date}', langdate($config['timestamp_active'], $row['editdate']));

					}

					$tpl->set('{editor}', $row['editor']);
					$tpl->set('{edit-reason}', $row['reason']);

					if ($row['reason']) {

						$tpl->set('[edit-reason]', "");
						$tpl->set('[/edit-reason]', "");

					} else {
						$tpl->set_block("'\\[edit-reason\\](.*?)\\[/edit-reason\\]'si", "");
					}

					$tpl->set('[edit-date]', "");
					$tpl->set('[/edit-date]', "");

				} else {

					$tpl->set('{edit-date}', "");
					$tpl->set('{editor}', "");
					$tpl->set('{edit-reason}', "");
					$tpl->set_block("'\\[edit-date\\](.*?)\\[/edit-date\\]'si", "");
					$tpl->set_block("'\\[edit-reason\\](.*?)\\[/edit-reason\\]'si", "");
				}

				if ($config['allow_tags'] and $row['tags']) {

					$tpl->set('[tags]', "");
					$tpl->set('[/tags]', "");

					$tags = array();

					$row['tags'] = explode(",", $row['tags']);

					foreach ($row['tags'] as $value) {

						$value = trim($value);

						if ($config['allow_alt_url']) {
							$tags[] = "<a href=\"" . $config['http_home_url'] . "tags/" . urlencode($value) . "/\">" . $value . "</a>";
						} else {
							$tags[] = "<a href=\"$PHP_SELF?do=tags&amp;tag=" . urlencode($value) . "\">" . $value . "</a>";

						}
					}

					$tpl->set('{tags}', implode(", ", $tags));

				} else {

					$tpl->set_block("'\\[tags\\](.*?)\\[/tags\\]'si", "");
					$tpl->set('{tags}', "");

				}

				$url_cat = $category_id;
				$category_id = $row['category'];

				if (strpos($tpl->copy_template, "[catlist=") !== false) {
					$tpl->copy_template = preg_replace_callback("#\\[(catlist)=(.+?)\\](.*?)\\[/catlist\\]#is", "check_category", $tpl->copy_template);
				}

				if (strpos($tpl->copy_template, "[not-catlist=") !== false) {
					$tpl->copy_template = preg_replace_callback("#\\[(not-catlist)=(.+?)\\](.*?)\\[/not-catlist\\]#is", "check_category", $tpl->copy_template);
				}

				$category_id = $url_cat;

				if ($category_id AND $cat_info[$category_id]['icon']) {

					$tpl->set('{category-icon}', $cat_info[$category_id]['icon']);

				} else {

					$tpl->set('{category-icon}', "{THEME}/dleimages/no_icon.gif");

				}

				if ($category_id) {
					$tpl->set('{category-url}', $config['http_home_url'] . get_url($category_id) . "/");
				} else {
					$tpl->set('{category-url}', "#");
				}

				if ($row['allow_rate']) {

					$tpl->set('{rating}', ShowRating($row['id'], $row['rating'], $row['vote_num'], $user_group[$member_id['user_group']]['allow_rating']));
					$tpl->set('{vote-num}', "<span id=\"vote-num-id-" . $row['id'] . "\">" . $row['vote_num'] . "</span>");
					$tpl->set('[rating]', "");
					$tpl->set('[/rating]', "");

				} else {

					$tpl->set('{rating}', "");
					$tpl->set('{vote-num}', "");
					$tpl->set_block("'\\[rating\\](.*?)\\[/rating\\]'si", "");
				}

				if ($config['allow_alt_url']) {

					$go_page = $config['http_home_url'] . "user/" . urlencode($row['autor']) . "/";
					$tpl->set('[day-news]', "<a href=\"" . $config['http_home_url'] . date('Y/m/d/', $row['date']) . "\" >");

				} else {

					$go_page = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode($row['autor']);
					$tpl->set('[day-news]', "<a href=\"$PHP_SELF?year=" . date('Y', $row['date']) . "&amp;month=" . date('m', $row['date']) . "&amp;day=" . date('d', $row['date']) . "\" >");

				}

				$tpl->set('[/day-news]', "</a>");
				$tpl->set('[profile]', "<a href=\"" . $go_page . "\">");
				$tpl->set('[/profile]', "</a>");

				$tpl->set('{login}', $row['autor']);

				$tpl->set('{author}', "<a onclick=\"ShowProfile('" . urlencode($row['autor']) . "', '" . $go_page . "', '" . $user_group[$member_id['user_group']]['admin_editusers'] . "'); return false;\" href=\"" . $go_page . "\">" . $row['autor'] . "</a>");

				if ($row['allow_comm']) {

					$tpl->set('[com-link]', "<a id=\"dle-comm-link\" href=\"" . $full_link . "#comment\">");
					$tpl->set('[/com-link]', "</a>");

				} else {
					$tpl->set_block("'\\[com-link\\](.*?)\\[/com-link\\]'si", "");
				}

				if (!$row['approve'] and ($member_id['name'] == $row['autor'] and !$user_group[$member_id['user_group']]['allow_all_edit'])) {
					$tpl->set('[edit]', "<a href=\"" . $config['http_home_url'] . "index.php?do=addnews&amp;id=" . $row['id'] . "\" >");
					$tpl->set('[/edit]', "</a>");
					if ($config['allow_quick_wysiwyg']) {
						$allow_comments_ajax = true;
					}
				} elseif ($is_logged and (($member_id['name'] == $row['autor'] and $user_group[$member_id['user_group']]['allow_edit']) or $user_group[$member_id['user_group']]['allow_all_edit'])) {
					$tpl->set('[edit]', "<a onclick=\"return dropdownmenu(this, event, MenuNewsBuild('" . $row['id'] . "', 'full'), '170px')\" href=\"#\">");
					$tpl->set('[/edit]', "</a>");
					if ($config['allow_quick_wysiwyg']) {
						$allow_comments_ajax = true;
					}
				} else {
					$tpl->set_block("'\\[edit\\](.*?)\\[/edit\\]'si", "");
				}

				$tpl->set('{favorites}', "");
				$tpl->set_block("'\\[complaint\\](.*?)\\[/complaint\\]'si", "");
				$tpl->set_block("'\\[add-favorites\\](.*?)\\[/add-favorites\\]'si", "");
				$tpl->set_block("'\\[del-favorites\\](.*?)\\[/del-favorites\\]'si", "");
				$tpl->set('{poll}', '');
				$tpl->set('{title}', $row['title']);

				if (preg_match("#\\{title limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches)) {
					$count = intval($matches[1]);
					$row['title'] = strip_tags($row['title']);

					if ($count AND dle_strlen($row['title'], $config['charset']) > $count) {

						$row['title'] = dle_substr($row['title'], 0, $count, $config['charset']);

						if (($temp_dmax = dle_strrpos($row['title'], ' ', $config['charset']))) {
							$row['title'] = dle_substr($row['title'], 0, $temp_dmax, $config['charset']);

						}
					}

					$tpl->set($matches[0], $row['title']);

				}

				$row['short_story'] = stripslashes($row['short_story']);
				$row['full_story'] = stripslashes($row['full_story']);

				if ($config['allow_links'] AND function_exists('replace_links') AND isset($replace_links['news'])) {
					$row['short_story'] = replace_links($row['short_story'], $replace_links['news']);
					$row['full_story'] = replace_links($row['full_story'], $replace_links['news']);
				}

				if (stripos($tpl->copy_template, "{image-") !== false) {

					$images = array();
					preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['short_story'], $media);
					$data = preg_replace('/(img|src)("|\'|="|=\')(.*)/i', "$3", $media[0]);

					foreach ($data as $url) {
						$info = pathinfo($url);
						if (isset($info['extension'])) {
							if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-plus") {
								continue;
							}

							$info['extension'] = strtolower($info['extension']);
							if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif') || ($info['extension'] == 'png')) {
								array_push($images, $url);
							}
						}
					}

					if (count($images)) {
						$i = 0;
						foreach ($images as $url) {
							$i++;
							$tpl->copy_template = str_replace('{image-' . $i . '}', $url, $tpl->copy_template);
							$tpl->copy_template = str_replace('[image-' . $i . ']', "", $tpl->copy_template);
							$tpl->copy_template = str_replace('[/image-' . $i . ']', "", $tpl->copy_template);
						}

					}

					$tpl->copy_template = preg_replace("#\[image-(.+?)\](.+?)\[/image-(.+?)\]#is", "", $tpl->copy_template);
					$tpl->copy_template = preg_replace("#\\{image-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg", $tpl->copy_template);

				}

				if (stripos($tpl->copy_template, "{fullimage-") !== false) {

					$images = array();
					preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['full_story'], $media);
					$data = preg_replace('/(img|src)("|\'|="|=\')(.*)/i', "$3", $media[0]);

					foreach ($data as $url) {
						$info = pathinfo($url);
						if (isset($info['extension'])) {
							if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-plus") {
								continue;
							}

							$info['extension'] = strtolower($info['extension']);
							if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif') || ($info['extension'] == 'png')) {
								array_push($images, $url);
							}
						}
					}

					if (count($images)) {
						$i = 0;
						foreach ($images as $url) {
							$i++;
							$tpl->copy_template = str_replace('{fullimage-' . $i . '}', $url, $tpl->copy_template);
							$tpl->copy_template = str_replace('[fullimage-' . $i . ']', "", $tpl->copy_template);
							$tpl->copy_template = str_replace('[/fullimage-' . $i . ']', "", $tpl->copy_template);
						}

					}

					$tpl->copy_template = preg_replace("#\[fullimage-(.+?)\](.+?)\[/fullimage-(.+?)\]#is", "", $tpl->copy_template);
					$tpl->copy_template = preg_replace("#\\{fullimage-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg", $tpl->copy_template);

				}

				$tpl->set('{comments}', '');
				$tpl->set('{addcomments}', '');
				$tpl->set('{navigation}', '');
				$tpl->set('{related-news}', '');
				$tpl->set_block( "'\\[related-news\\](.*?)\\[/related-news\\]'si", "");
				$tpl->set( '{pages}', '' );
				$row['full_story'] = preg_replace( "'\[page=(.*?)\](.*?)\[/page\]'si", "", $row['full_story'] );
				$tpl->set_block( "'\\[pages\\](.*?)\\[/pages\\]'si", "" );

				$tpl->set('{short-story}', $row['short_story']);

				$tpl->set('{full-story}', $row['full_story']);

				if (preg_match("#\\{full-story limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches)) {
					$count = intval($matches[1]);

					$row['full_story'] = str_replace("</p><p>", " ", $row['full_story']);
					$row['full_story'] = strip_tags($row['full_story'], "<br>");
					$row['full_story'] = trim(str_replace("<br>", " ", str_replace("<br />", " ", str_replace("\n", " ", str_replace("\r", "", $row['full_story'])))));

					if ($count AND dle_strlen($row['full_story'], $config['charset']) > $count) {

						$row['full_story'] = dle_substr($row['full_story'], 0, $count, $config['charset']);

						if (($temp_dmax = dle_strrpos($row['full_story'], ' ', $config['charset']))) {
							$row['full_story'] = dle_substr($row['full_story'], 0, $temp_dmax, $config['charset']);

						}
					}

					$tpl->set($matches[0], $row['full_story']);

				}

				if (strpos($tpl->copy_template, "[xfvalue_") !== false OR strpos($tpl->copy_template, "[xfgiven_") !== false) {

					$xfieldsdata = xfieldsdataload($row['xfields']);

					foreach ($xfields as $value) {
						$preg_safe_name = preg_quote($value[0], "'");

						if ($value[6] AND !empty($xfieldsdata[$value[0]])) {
							$temp_array = explode(",", $xfieldsdata[$value[0]]);
							$value3 = array();

							foreach ($temp_array as $value2) {

								$value2 = trim($value2);
								$value2 = str_replace("&#039;", "'", $value2);

								if ($config['allow_alt_url']) {
									$value3[] = "<a href=\"" . $config['http_home_url'] . "xfsearch/" . urlencode($value2) . "/\">" . $value2 . "</a>";
								} else {
									$value3[] = "<a href=\"$PHP_SELF?do=xfsearch&amp;xf=" . urlencode($value2) . "\">" . $value2 . "</a>";
								}
							}

							$xfieldsdata[$value[0]] = implode(", ", $value3);

							unset($temp_array);
							unset($value2);
							unset($value3);

						}

						if (empty($xfieldsdata[$value[0]])) {
							$tpl->copy_template = preg_replace("'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);
							$tpl->copy_template = str_replace("[xfnotgiven_{$value[0]}]", "", $tpl->copy_template);
							$tpl->copy_template = str_replace("[/xfnotgiven_{$value[0]}]", "", $tpl->copy_template);
						} else {
							$tpl->copy_template = preg_replace("'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template);
							$tpl->copy_template = str_replace("[xfgiven_{$value[0]}]", "", $tpl->copy_template);
							$tpl->copy_template = str_replace("[/xfgiven_{$value[0]}]", "", $tpl->copy_template);
						}

						$xfieldsdata[$value[0]] = stripslashes($xfieldsdata[$value[0]]);

						if ($config['allow_links'] AND $value[3] == "textarea" AND function_exists('replace_links')) {
							$xfieldsdata[$value[0]] = replace_links($xfieldsdata[$value[0]], $replace_links['news']);
						}

						$tpl->copy_template = str_replace("[xfvalue_{$value[0]}]", $xfieldsdata[$value[0]], $tpl->copy_template);

						if (preg_match("#\\[xfvalue_{$preg_safe_name} limit=['\"](.+?)['\"]\\]#i", $tpl->copy_template, $matches)) {
							$count = intval($matches[1]);

							$xfieldsdata[$value[0]] = str_replace("</p><p>", " ", $xfieldsdata[$value[0]]);
							$xfieldsdata[$value[0]] = strip_tags($xfieldsdata[$value[0]], "<br>");
							$xfieldsdata[$value[0]] = trim(str_replace("<br>", " ", str_replace("<br />", " ", str_replace("\n", " ", str_replace("\r", "", $xfieldsdata[$value[0]])))));

							if ($count AND dle_strlen($xfieldsdata[$value[0]], $config['charset']) > $count) {

								$xfieldsdata[$value[0]] = dle_substr($xfieldsdata[$value[0]], 0, $count, $config['charset']);

								if (($temp_dmax = dle_strrpos($xfieldsdata[$value[0]], ' ', $config['charset']))) {
									$xfieldsdata[$value[0]] = dle_substr($xfieldsdata[$value[0]], 0, $temp_dmax, $config['charset']);

								}
							}

							$tpl->set($matches[0], $xfieldsdata[$value[0]]);

						}
					}
				}

				$category_id = $row['category'];

				$category_id = $row['category'];

				$tpl->compile('fs');

				$tpl->result['fs'] = preg_replace_callback("#\\[declination=(\d+)\\](.+?)\\[/declination\\]#is", "declination", $tpl->result['fs']);

				if ($user_group[$member_id['user_group']]['allow_hide']) {
					$tpl->result['fs'] = str_ireplace("[hide]", "", str_ireplace("[/hide]", "", $tpl->result['fs']));
				} else {
					$tpl->result['fs'] = preg_replace("#\[hide\](.+?)\[/hide\]#ims", "<div class=\"quote\">" . $lang['news_regus'] . "</div>", $tpl->result['fs']);
				}

				$tpl->result['fs'] = preg_replace("'\\[banner_(.*?)\\](.*?)\\[/banner_(.*?)\\]'si", '', $tpl->result['fs']);

				$tpl->result['fs'] = str_ireplace('{THEME}', $config['http_home_url'] . 'templates/' . $config['skin'], $tpl->result['fs']);

				$news_id = $row['id'];

			}// $row['id'] AND $perm

			$tpl->clear();
			unset($row);

			if ($config['files_allow']) {
				if (strpos($tpl->result['fs'], "[attachment=") !== false) {
					$tpl->result['fs'] = show_attach($tpl->result['fs'], $news_id);
				}
			}

			$fs = $tpl->result['fs'];

			if (!$perm) {
				$fs = '<b>' . $user_group[$member_id['user_group']]['group_name'] . '</b> не имеют доступа для просмотра статей из данного раздела.';
			}

		}// $fsCfg['newsId'] > 0

	} else {
		// Если файла шаблона нет - скажем об этом на понятном языке.
		$fs = '<b style="color:red">Отсутствует файл шаблона: /' . $fsCfg['template'] . '.tpl</b>';
	}
	if ($config['allow_cache'] && $config['allow_cache'] != 'no') {
		create_cache($fsCfg['cachePrefix'], $fs, $cacheName, true);
	}
}
die($fs);

?>