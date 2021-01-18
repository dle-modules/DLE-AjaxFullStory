<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2020 SoftNews Media Group
=====================================================
 This code is protected by copyright
=====================================================
 File: show.full.php
-----------------------------------------------------
 Use: View full news and comments
=====================================================
*/

if (!defined('DATALIFEENGINE')) {
    header("HTTP/1.1 403 Forbidden");
    header('Location: ../../');
    die("Hacking attempt!");
}

require_once(DLEPlugins::Check(ENGINE_DIR.'/classes/templates.class.php'));

$template_dir = ROOT_DIR.'/templates/'.$config['skin'];

// Пытаемся получить даные из шаблона с настройками
$presetFile = (!empty($_GET['preset'])) ? $_GET['preset'] : false;

if ($presetFile) {
    // Если название шаблона передано, то получаем из него конфиг
    if (file_exists($template_dir.'/'.$presetFile.'.tpl')) {
        // Если файл существует - берём из него контент с настройками
        $preset = file_get_contents($template_dir.'/'.$presetFile.'.tpl');
        $arConf = [];
    } else {
        $afs = '<div class="afs-error afs-tpl-error">
			<b>Ошибка.</b> Отсутствует файл шаблона: /templates/'.$config['skin'].'/'.$presetFile.'.tpl
		</div>';
        die($afs);
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
    $existFields = [
        'short_story',
        'full_story',
        'xfields',
        'comm_num',
        'fixed',
        'tags',
    ];

    // Поля, которые отбираются из БД в любом случае
    $_queryFields = [
        'id',
        'title',
        'date',
        'category',
        'alt_name',
        'approve',
        'autor',
    ];

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

$newsID = (int)$_GET['newsId'];

// Конфиг модуля
$afsCfg = [
    'template'    => !empty($_GET['template']) ? $_GET['template'] : 'ajax/fullstory',
    'cachePrefix' => !empty($arConf['cachePrefix']) ? $arConf['cachePrefix'].'_'.$newsID : 'full_'.$newsID,
    'newsId'      => ($newsID > 0) ? $newsID : '0',
    'fields'      => $queryFields,
];

if (!file_exists($template_dir.'/'.$afsCfg['template'].'.tpl')) {
    $afs = '<div class="afs-error afs-tpl-error">
			<b>Ошибка.</b> Отсутствует файл шаблона: /templates/'.$config['skin'].'/'.$afsCfg['template'].'.tpl
		</div>';
    die($afs);
}

if ($afsCfg['newsId'] < 1) {
    $afs = '<div class="afs-error afs-news-error"><b>Ошибка.</b> Статья не существует или удалена.</div>';
    die($afs);
}

// Получаем новость
$query = "SELECT ".$afsCfg['fields']." FROM ".PREFIX."_post LEFT JOIN ".PREFIX."_post_extras ON (".PREFIX."_post.id="
    .PREFIX."_post_extras.news_id) WHERE id = ".$afsCfg['newsId'];


$allow_list     = explode(',', $user_group[$member_id['user_group']]['allow_cats']);
$not_allow_cats = explode(',', $user_group[$member_id['user_group']]['not_allow_cats']);

$perm             = 1;
$i                = 0;
$news_found       = false;
$allow_full_cache = false;

$row = dle_cache($afsCfg['cachePrefix'], $query);

if ($row) {
    $row = json_decode($row, true);
}

if (is_array($row)) {
    $full_cache = true;
} else {
    $row        = $db->super_query($query);
    $full_cache = false;
}

$options = news_permission($row['access']);

if ($options[$member_id['user_group']] AND $options[$member_id['user_group']] != 3) {
    $perm = 1;
}
if ($options[$member_id['user_group']] == 3) {
    $perm = 0;
}

if ($row['id'] AND !$row['approve'] AND $member_id['name'] != $row['autor']
    AND !$user_group[$member_id['user_group']]['allow_all_edit']
) {
    $perm = 0;
}

if ($row['id'] AND $config['no_date'] AND !$config['news_future']
    AND !$user_group[$member_id['user_group']]['allow_all_edit']
) {
    if (strtotime($row['date']) > $_TIME) {
        $perm = 0;
    }
}

$need_pass = $row['need_pass'];

if ($row['id'] AND $need_pass AND $member_id['user_group'] > 2) {
    if (!$_SESSION['news_pass_'.$row['id'].'']) {
        $perm = 0;
        $afs  = '<div class="afs-error afs-perm-error"><b>'.$user_group[$member_id['user_group']]['group_name']
            .'</b> не имеют доступа для просмотра статей из данного раздела.</div>';
        die($afs);
    } else {
        $need_pass = false;
    }
}

if ($config['category_separator'] != ',') {
    $config['category_separator'] = ' '.$config['category_separator'];
}

// Когда статья не найдена, скажем об этом.
if (!$row['id'] AND $perm) {
    $afs = '<div class="afs-error afs-news-error"><b>Ошибка.</b> Статья не существует или удалена.</div>';
    die($afs);
}

if (!$perm) {
    $afs = '<div class="afs-error afs-perm-error"><b>'.$user_group[$member_id['user_group']]['group_name']
        .'</b> не имеют доступа для просмотра статей из данного раздела.</div>';
    die($afs);
}

if (!$row['category']) {
    $my_cat      = "---";
    $my_cat_link = "---";
} else {

    $my_cat      = [];
    $my_cat_link = [];
    $cat_list    = explode(',', $row['category']);

    if (count($cat_list) == 1) {

        if ($allow_list[0] != "all" AND !in_array($cat_list[0], $allow_list)) {
            $perm = 0;
        }

        if ($not_allow_cats[0] != "" AND in_array($cat_list[0], $not_allow_cats)) {
            $perm = 0;
        }

        if ($cat_info[$cat_list[0]]['id']) {
            $my_cat[]    = $cat_info[$cat_list[0]]['name'];
            $my_cat_link = get_categories($cat_list[0], $config['category_separator']);
        } else {
            $my_cat_link = "---";
        }

    } else {

        foreach ($cat_list as $element) {

            if ($allow_list[0] != "all" AND !in_array($element, $allow_list)) {
                $perm = 0;
            }

            if ($not_allow_cats[0] != "" AND in_array($element, $not_allow_cats)) {
                $perm = 0;
            }

            if ($element AND $cat_info[$element]['id']) {
                $my_cat[] = $cat_info[$element]['name'];
                if ($config['allow_alt_url']) {
                    $my_cat_link[] = "<a href=\"".$config['http_home_url'].get_url($element)
                        ."/\">{$cat_info[$element]['name']}</a>";
                } else {
                    $my_cat_link[]
                        = "<a href=\"$PHP_SELF?do=cat&amp;category={$cat_info[$element]['alt_name']}\">{$cat_info[$element]['name']}</a>";
                }
            }
        }

        if (count($my_cat_link)) {
            $my_cat_link = implode("{$config['category_separator']} ", $my_cat_link);
        } else {
            $my_cat_link = "---";
        }
    }

    if (count($my_cat)) {
        $my_cat = implode("{$config['category_separator']} ", $my_cat);
    } else {
        $my_cat = "---";
    }

}


if ($row['id'] AND $perm) {

    $config['fullcache_days'] = intval($config['fullcache_days']);

    if ($config['fullcache_days'] < 1) {
        $config['fullcache_days'] = 30;
    }

    if (strtotime($row['date']) >= ($_TIME - ($config['fullcache_days'] * 86400))) {

        $allow_full_cache = true;

    }

    $xfields = xfieldsload();

    $category_id = intval($row['category']);

    // Подцепляем шаблонизатор
    $tpl      = new dle_template();
    $tpl->dir = $template_dir;
    define('TEMPLATE_DIR', $tpl->dir);
    // Подгружаем шаблон
    $tpl->load_template($afsCfg['template'].'.tpl');

    if (stripos($tpl->copy_template, "{next-") !== false OR stripos($tpl->copy_template, "{prev-") !== false) {
        $link      = "";
        $prev_next = false;

        if ($allow_full_cache) {
            $prev_next = dle_cache("news", "next_prev_l_".$row['id']);
            if ($prev_next) {
                $prev_next = json_decode($prev_next, true);
            }
        }
        if (!is_array($prev_next)) {

            $row_link = $db->super_query("SELECT id, date, title, category, alt_name FROM ".PREFIX
                ."_post WHERE category = '{$row['category']}' AND date >= '{$row['date']}'{$where_date} AND id != '{$row['id']}' AND approve = '1' ORDER BY date ASC LIMIT 1");

            if ($row_link['id']) {
                if ($config['allow_alt_url']) {
                    if ($config['seo_type'] == 1 OR $config['seo_type'] == 2) {
                        if (intval($row_link['category']) and $config['seo_type'] == 2) {
                            $link = $config['http_home_url'].get_url(intval($row_link['category']))."/".$row_link['id']
                                ."-".$row_link['alt_name'].".html";
                        } else {
                            $link = $config['http_home_url'].$row_link['id']."-".$row_link['alt_name'].".html";
                        }
                    } else {
                        $link = $config['http_home_url'].date('Y/m/d/', strtotime($row_link['date']))
                            .$row_link['alt_name'].".html";
                    }
                } else {
                    $link = $config['http_home_url']."index.php?newsid=".$row_link['id'];
                }

                $prev_next['next_title'] = str_replace("&amp;amp;", "&amp;",
                    htmlspecialchars(strip_tags(stripslashes($row_link['title'])), ENT_QUOTES, $config['charset']));
            } else {
                $prev_next['next_title'] = "";
            }

            $prev_next['next_link'] = $link;
            $link                   = "";

            $row_link = $db->super_query("SELECT id, date, title, category, alt_name FROM ".PREFIX
                ."_post WHERE category = '{$row['category']}' AND date <= '{$row['date']}'{$where_date} AND id != '{$row['id']}' AND approve = '1' ORDER BY date DESC LIMIT 1");

            if ($row_link['id']) {
                if ($config['allow_alt_url']) {
                    if ($config['seo_type'] == 1 OR $config['seo_type'] == 2) {
                        if (intval($row_link['category']) and $config['seo_type'] == 2) {
                            $link = $config['http_home_url'].get_url(intval($row_link['category']))."/".$row_link['id']
                                ."-".$row_link['alt_name'].".html";
                        } else {
                            $link = $config['http_home_url'].$row_link['id']."-".$row_link['alt_name'].".html";
                        }
                    } else {
                        $link = $config['http_home_url'].date('Y/m/d/', strtotime($row_link['date']))
                            .$row_link['alt_name'].".html";
                    }
                } else {
                    $link = $config['http_home_url']."index.php?newsid=".$row_link['id'];
                }

                $prev_next['prev_title'] = str_replace("&amp;amp;", "&amp;",
                    htmlspecialchars(strip_tags(stripslashes($row_link['title'])), ENT_QUOTES, $config['charset']));

            } else {
                $prev_next['prev_title'] = "";
            }

            $prev_next['prev_link'] = $link;

            if ($allow_full_cache) {
                create_cache("news", json_encode($prev_next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    "next_prev_l_".$row['id']);
            }

        }

        if ($prev_next['next_link']) {
            $tpl->set('[next-url]', "");
            $tpl->set('[/next-url]', "");
            $tpl->set('{next-url}', $prev_next['next_link']);
            $tpl->set('{next-title}', $prev_next['next_title']);
        } else {
            $tpl->set('{next-url}', "");
            $tpl->set('{next-title}', "");
            $tpl->set_block("'\\[next-url\\](.*?)\\[/next-url\\]'si", "");
        }

        if ($prev_next['prev_link']) {
            $tpl->set('[prev-url]', "");
            $tpl->set('[/prev-url]', "");
            $tpl->set('{prev-url}', $prev_next['prev_link']);
            $tpl->set('{prev-title}', $prev_next['prev_title']);
        } else {
            $tpl->set('{prev-url}', "");
            $tpl->set('{prev-title}', "");
            $tpl->set_block("'\\[prev-url\\](.*?)\\[/prev-url\\]'si", "");
        }

    }

    if ($config['allow_read_count']) {
        if ($config['allow_read_count'] == 2) {

            $readcount = $db->super_query("SELECT count(*) as count FROM ".PREFIX
                ."_read_log WHERE news_id='{$row['id']}' AND ip='{$_IP}'");

            if (!$readcount['count']) {

                if ($config['cache_count']) {
                    $db->query("INSERT INTO ".PREFIX."_views (news_id) VALUES ('{$row['id']}')");
                } else {
                    $db->query("UPDATE ".PREFIX."_post_extras SET news_read=news_read+1 WHERE news_id='{$row['id']}'");
                }

                $db->query("INSERT INTO ".PREFIX."_read_log (news_id, ip) VALUES ('{$row['id']}', '{$_IP}')");
            }

        } else {

            if ($config['cache_count']) {
                $db->query("INSERT INTO ".PREFIX."_views (news_id) VALUES ('{$row['id']}')");
            } else {
                $db->query("UPDATE ".PREFIX."_post_extras SET news_read=news_read+1 WHERE news_id='{$row['id']}'");
            }
        }
    }

    if ($allow_full_cache AND !$full_cache) {
        create_cache($afsCfg['cachePrefix'], json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $query);
    }

    $news_found  = true;
    $empty_full  = false;
    $row['date'] = strtotime($row['date']);

    if ((strlen($row['full_story']) < 13) and (strpos($tpl->copy_template, "{short-story}") === false)) {
        $row['full_story'] = $row['short_story'];
        $empty_full        = true;
    }


    $row['full_story'] = str_replace("{PAGEBREAK}", "", $row['full_story']);
    $row['title']      = stripslashes($row['title']);

    $comments_num = $row['comm_num'];

    $news_find = [
        '{comments-num}'  => number_format($comments_num, 0, ',', ' '),
        '{views}'         => number_format($row['news_read'], 0, ',', ' '),
        '{category}'      => $my_cat,
        '{link-category}' => $my_cat_link,
        '{news-id}'       => $row['id'],
    ];

    if (date('Ymd', $row['date']) == date('Ymd', $_TIME)) {

        $tpl->set('{date}', $lang['time_heute'].langdate(", H:i", $row['date']));

    } elseif (date('Ymd', $row['date']) == date('Ymd', ($_TIME - 86400))) {

        $tpl->set('{date}', $lang['time_gestern'].langdate(", H:i", $row['date']));

    } else {

        $tpl->set('{date}', langdate($config['timestamp_active'], $row['date']));

    }
    $news_date          = $row['date'];
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

    $tpl->set_block("'\\[comments\\](.*?)\\[/comments\\]'si", "");
    $tpl->set_block("'\\[not-comments\\](.*?)\\[/not-comments\\]'si", "");


    if ($row['votes']) {

        $tpl->set('[poll]', "");
        $tpl->set('[/poll]', "");
        $tpl->set_block("'\\[not-poll\\](.*?)\\[/not-poll\\]'si", "");

    } else {

        $tpl->set('[not-poll]', "");
        $tpl->set('[/not-poll]', "");
        $tpl->set_block("'\\[poll\\](.*?)\\[/poll\\]'si", "");
    }

    if ($vk_url) {
        $tpl->set('[vk]', "");
        $tpl->set('[/vk]', "");
        $tpl->set('{vk_url}', $vk_url);
    } else {
        $tpl->set_block("'\\[vk\\](.*?)\\[/vk\\]'si", "");
        $tpl->set('{vk_url}', '');
    }
    if ($odnoklassniki_url) {
        $tpl->set('[odnoklassniki]', "");
        $tpl->set('[/odnoklassniki]', "");
        $tpl->set('{odnoklassniki_url}', $odnoklassniki_url);
    } else {
        $tpl->set_block("'\\[odnoklassniki\\](.*?)\\[/odnoklassniki\\]'si", "");
        $tpl->set('{odnoklassniki_url}', '');
    }
    if ($facebook_url) {
        $tpl->set('[facebook]', "");
        $tpl->set('[/facebook]', "");
        $tpl->set('{facebook_url}', $facebook_url);
    } else {
        $tpl->set_block("'\\[facebook\\](.*?)\\[/facebook\\]'si", "");
        $tpl->set('{facebook_url}', '');
    }
    if ($google_url) {
        $tpl->set('[google]', "");
        $tpl->set('[/google]', "");
        $tpl->set('{google_url}', $google_url);
    } else {
        $tpl->set_block("'\\[google\\](.*?)\\[/google\\]'si", "");
        $tpl->set('{google_url}', '');
    }
    if ($mailru_url) {
        $tpl->set('[mailru]', "");
        $tpl->set('[/mailru]', "");
        $tpl->set('{mailru_url}', $mailru_url);
    } else {
        $tpl->set_block("'\\[mailru\\](.*?)\\[/mailru\\]'si", "");
        $tpl->set('{mailru_url}', '');
    }
    if ($yandex_url) {
        $tpl->set('[yandex]', "");
        $tpl->set('[/yandex]', "");
        $tpl->set('{yandex_url}', $yandex_url);
    } else {
        $tpl->set_block("'\\[yandex\\](.*?)\\[/yandex\\]'si", "");
        $tpl->set('{yandex_url}', '');
    }

    if ($row['editdate']) {
        $_DOCUMENT_DATE = $row['editdate'];
    } else {
        $_DOCUMENT_DATE = $row['date'];
    }

    if ($row['view_edit'] and $row['editdate']) {

        if (date('Ymd', $row['editdate']) == date('Ymd', $_TIME)) {

            $tpl->set('{edit-date}', $lang['time_heute'].langdate(", H:i", $row['editdate']));

        } elseif (date('Ymd', $row['editdate']) == date('Ymd', ($_TIME - 86400))) {

            $tpl->set('{edit-date}', $lang['time_gestern'].langdate(", H:i", $row['editdate']));

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

        $social_tags['news_keywords'] = $row['tags'];

        $tags = [];

        $row['tags'] = explode(",", $row['tags']);

        foreach ($row['tags'] as $value) {

            $value   = trim($value);
            $url_tag = str_replace(["&#039;", "&quot;", "&amp;"], ["'", '"', "&"], $value);

            if ($config['allow_alt_url']) {
                $tags[] = "<span><a href=\"".$config['http_home_url']."tags/".rawurlencode($url_tag)."/\">".$value
                    ."</a></span>";
            } else {
                $tags[] = "<span><a href=\"$PHP_SELF?do=tags&amp;tag=".rawurlencode($url_tag)."\">".$value
                    ."</a></span>";
            }

        }

        $tpl->set('{tags}', implode(" ", $tags));

    } else {

        $tpl->set_block("'\\[tags\\](.*?)\\[/tags\\]'si", "");
        $tpl->set('{tags}', "");

    }

    $tpl->set('', $news_find);

    $url_cat     = $category_id;
    $category_id = $row['category'];

    if (strpos($tpl->copy_template, "[catlist=") !== false) {
        $tpl->copy_template = preg_replace_callback("#\\[(catlist)=(.+?)\\](.*?)\\[/catlist\\]#is", "check_category",
            $tpl->copy_template);
    }

    if (strpos($tpl->copy_template, "[not-catlist=") !== false) {
        $tpl->copy_template = preg_replace_callback("#\\[(not-catlist)=(.+?)\\](.*?)\\[/not-catlist\\]#is",
            "check_category", $tpl->copy_template);
    }

    $category_id = $url_cat;

    if ($category_id AND $cat_info[$category_id]['icon']) {

        $tpl->set('{category-icon}', $cat_info[$category_id]['icon']);

    } else {

        $tpl->set('{category-icon}', "{THEME}/dleimages/no_icon.gif");

    }

    if ($category_id) {

        if ($config['allow_alt_url']) {
            $tpl->set('{category-url}', $config['http_home_url'].get_url($category_id)."/");
        } else {
            $tpl->set('{category-url}', "$PHP_SELF?do=cat&category={$cat_info[$category_id]['alt_name']}");
        }

    } else {
        $tpl->set('{category-url}', "#");
    }

    $tpl->set_block("'\\[print-link\\](.*?)\\[/print-link\\]'si", "");


    if ($config['rating_type'] == "1") {
        $tpl->set('[rating-type-2]', "");
        $tpl->set('[/rating-type-2]', "");
        $tpl->set_block("'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "");
        $tpl->set_block("'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "");
        $tpl->set_block("'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "");
    } elseif ($config['rating_type'] == "2") {
        $tpl->set('[rating-type-3]', "");
        $tpl->set('[/rating-type-3]', "");
        $tpl->set_block("'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "");
        $tpl->set_block("'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "");
        $tpl->set_block("'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "");
    } elseif ($config['rating_type'] == "3") {
        $tpl->set('[rating-type-4]', "");
        $tpl->set('[/rating-type-4]', "");
        $tpl->set_block("'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "");
        $tpl->set_block("'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "");
        $tpl->set_block("'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "");
    } else {
        $tpl->set('[rating-type-1]', "");
        $tpl->set('[/rating-type-1]', "");
        $tpl->set_block("'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "");
        $tpl->set_block("'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "");
        $tpl->set_block("'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "");
    }

    if ($row['allow_rate']) {

        $dislikes = ($row['vote_num'] - $row['rating']) / 2;
        $likes    = $row['vote_num'] - $dislikes;

        $tpl->set('{likes}', "<span id=\"likes-id-".$row['id']."\" class=\"ignore-select\">".$likes."</span>");
        $tpl->set('{dislikes}', "<span id=\"dislikes-id-".$row['id']."\" class=\"ignore-select\">".$dislikes."</span>");

        $tpl->set('{rating}', ShowRating($row['id'], $row['rating'], $row['vote_num'],
            $user_group[$member_id['user_group']]['allow_rating']));
        $tpl->set('{vote-num}', "<span id=\"vote-num-id-".$row['id']."\">".$row['vote_num']."</span>");
        $tpl->set('[rating]', "");
        $tpl->set('[/rating]', "");

        if ($row['vote_num']) {
            $ratingscore = str_replace(',', '.', round(($row['rating'] / $row['vote_num']), 1));
        } else {
            $ratingscore = 0;
        }

        $tpl->set('{ratingscore}', $ratingscore);

        if ($user_group[$member_id['user_group']]['allow_rating']) {

            if ($config['rating_type']) {

                $tpl->set('[rating-plus]', "<a href=\"#\" onclick=\"doRate('plus', '{$row['id']}'); return false;\" >");
                $tpl->set('[/rating-plus]', '</a>');

                if ($config['rating_type'] == "2" OR $config['rating_type'] == "3") {

                    $tpl->set('[rating-minus]',
                        "<a href=\"#\" onclick=\"doRate('minus', '{$row['id']}'); return false;\" >");
                    $tpl->set('[/rating-minus]', '</a>');

                } else {
                    $tpl->set_block("'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "");
                }

            } else {
                $tpl->set_block("'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "");
                $tpl->set_block("'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "");
            }

        } else {
            $tpl->set_block("'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "");
            $tpl->set_block("'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "");
        }

    } else {

        $tpl->set('{rating}', "");
        $tpl->set('{vote-num}', "");
        $tpl->set('{likes}', "");
        $tpl->set('{dislikes}', "");
        $tpl->set('{ratingscore}', "");
        $tpl->set_block("'\\[rating\\](.*?)\\[/rating\\]'si", "");
        $tpl->set_block("'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "");
        $tpl->set_block("'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "");
    }
    $tpl->set_block("'\\[comments-subscribe\\](.*?)\\[/comments-subscribe\\]'si", "");

    if ($config['allow_alt_url']) {

        $go_page = $config['http_home_url']."user/".urlencode($row['autor'])."/";
        $tpl->set('[day-news]', "<a href=\"".$config['http_home_url'].date('Y/m/d/', $row['date'])."\" >");

    } else {

        $go_page = "$PHP_SELF?subaction=userinfo&amp;user=".urlencode($row['autor']);
        $tpl->set('[day-news]',
            "<a href=\"$PHP_SELF?year=".date('Y', $row['date'])."&amp;month=".date('m', $row['date'])."&amp;day="
            .date('d', $row['date'])."\" >");

    }

    $tpl->set('[/day-news]', "</a>");
    $tpl->set('[profile]', "<a href=\"".$go_page."\">");
    $tpl->set('[/profile]', "</a>");

    $tpl->set('{login}', $row['autor']);

    $tpl->set('{author}', "<a onclick=\"ShowProfile('".urlencode($row['autor'])."', '".$go_page."', '"
        .$user_group[$member_id['user_group']]['admin_editusers']."'); return false;\" href=\"".$go_page."\">"
        .$row['autor']."</a>");

    $_SESSION['referrer'] = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, $config['charset']);

    $tpl->set('[full-link]', "<a href=\"".$full_link."\">");
    $tpl->set('[/full-link]', "</a>");

    $tpl->set('{full-link}', $full_link);

    if ($row['allow_comm']) {

        $tpl->set('[com-link]', "<a id=\"dle-comm-link\" href=\"".$full_link."#comment\">");
        $tpl->set('[/com-link]', "</a>");

    } else {
        $tpl->set_block("'\\[com-link\\](.*?)\\[/com-link\\]'si", "");
    }

    if (!$row['approve'] and ($member_id['name'] == $row['autor']
            and !$user_group[$member_id['user_group']]['allow_all_edit'])
    ) {

        $tpl->set('[edit]', "<a href=\"".$config['http_home_url']."index.php?do=addnews&amp;id=".$row['id']."\" >");
        $tpl->set('[/edit]', "</a>");

        if ($config['allow_quick_wysiwyg']) {
            $allow_comments_ajax = true;
        }

    } elseif ($is_logged and (($member_id['name'] == $row['autor']
                and $user_group[$member_id['user_group']]['allow_edit'])
            or $user_group[$member_id['user_group']]['allow_all_edit'])
    ) {

        $tpl->set('[edit]', "<a onclick=\"return dropdownmenu(this, event, MenuNewsBuild('".$row['id']
            ."', 'full'), '170px')\" href=\"#\">");
        $tpl->set('[/edit]', "</a>");

        if ($config['allow_quick_wysiwyg']) {
            $allow_comments_ajax = true;
        }

    } else {
        $tpl->set_block("'\\[edit\\](.*?)\\[/edit\\]'si", "");
    }

    if ($is_logged) {

        $fav_arr = explode(',', $member_id['favorites']);

        if (!in_array($row['id'], $fav_arr)) {

            $tpl->set('{favorites}',
                "<a id=\"fav-id-".$row['id']."\" href=\"$PHP_SELF?do=favorites&amp;doaction=add&amp;id=".$row['id']
                ."\"><img src=\"".$config['http_home_url']
                ."templates/{$config['skin']}/dleimages/plus_fav.gif\" onclick=\"doFavorites('".$row['id']
                ."', 'plus', 0); return false;\" title=\"".$lang['news_addfav']
                ."\" style=\"vertical-align: middle;border: none;\" alt=\"\"></a>");
            $tpl->set('[add-favorites]', "<a id=\"fav-id-".$row['id']."\" onclick=\"doFavorites('".$row['id']
                ."', 'plus', 1); return false;\" href=\"$PHP_SELF?do=favorites&amp;doaction=add&amp;id=".$row['id']
                ."\">");
            $tpl->set('[/add-favorites]', "</a>");
            $tpl->set_block("'\\[del-favorites\\](.*?)\\[/del-favorites\\]'si", "");
        } else {

            $tpl->set('{favorites}',
                "<a id=\"fav-id-".$row['id']."\" href=\"$PHP_SELF?do=favorites&amp;doaction=del&amp;id=".$row['id']
                ."\"><img src=\"".$config['http_home_url']
                ."templates/{$config['skin']}/dleimages/minus_fav.gif\" onclick=\"doFavorites('".$row['id']
                ."', 'minus', 0); return false;\" title=\"".$lang['news_minfav']
                ."\" style=\"vertical-align: middle;border: none;\" alt=\"\"></a>");
            $tpl->set('[del-favorites]', "<a id=\"fav-id-".$row['id']."\" onclick=\"doFavorites('".$row['id']
                ."', 'minus', 1); return false;\" href=\"$PHP_SELF?do=favorites&amp;doaction=del&amp;id=".$row['id']
                ."\">");
            $tpl->set('[/del-favorites]', "</a>");
            $tpl->set_block("'\\[add-favorites\\](.*?)\\[/add-favorites\\]'si", "");
        }

    } else {
        $tpl->set('{favorites}', "");
        $tpl->set_block("'\\[add-favorites\\](.*?)\\[/add-favorites\\]'si", "");
        $tpl->set_block("'\\[del-favorites\\](.*?)\\[/del-favorites\\]'si", "");
    }

    $tpl->set('[complaint]', "<a href=\"javascript:AddComplaint('".$row['id']."', 'news')\">");
    $tpl->set('[/complaint]', "</a>");

    $tpl->set('{poll}', '');

    if ($config['allow_banner']) {
        include_once(DLEPlugins::Check(ENGINE_DIR.'/modules/banners.php'));
    }

    if ($config['allow_banner'] AND count($banners)) {

        foreach ($banners as $name => $value) {
            $tpl->copy_template = str_replace("{banner_".$name."}", $value, $tpl->copy_template);

            if ($value) {
                $tpl->copy_template = str_replace("[banner_".$name."]", "", $tpl->copy_template);
                $tpl->copy_template = str_replace("[/banner_".$name."]", "", $tpl->copy_template);
            }
        }
    }

    $tpl->set_block("'{banner_(.*?)}'si", "");
    $tpl->set_block("'\\[banner_(.*?)\\](.*?)\\[/banner_(.*?)\\]'si", "");

    $row['short_story'] = stripslashes($row['short_story']);
    $row['full_story']  = stripslashes($row['full_story']);
    $row['xfields']     = stripslashes($row['xfields']);

    if ($config['allow_links'] AND function_exists('replace_links') AND isset($replace_links['news'])) {
        $row['short_story'] = replace_links($row['short_story'], $replace_links['news']);
        $row['full_story']  = replace_links($row['full_story'], $replace_links['news']);
    }

    if (stripos($tpl->copy_template, "{image-") !== false) {

        $images = [];
        preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['short_story'].$row['xfields'], $media);
        $data = preg_replace('/(img|src)("|\'|="|=\')(.*)/i', "$3", $media[0]);

        foreach ($data as $url) {
            $info = pathinfo($url);
            if (isset($info['extension'])) {
                if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus"
                    OR strpos($info['dirname'], 'engine/data/emoticons') !== false
                ) {
                    continue;
                }
                $info['extension'] = strtolower($info['extension']);
                if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif')
                    || ($info['extension'] == 'png')
                    || ($info['extension'] == 'webp')
                ) {
                    array_push($images, $url);
                }
            }
        }

        if (count($images)) {
            $i = 0;
            foreach ($images as $url) {
                $i++;
                $tpl->copy_template = str_replace('{image-'.$i.'}', $url, $tpl->copy_template);
                $tpl->copy_template = str_replace('[image-'.$i.']', "", $tpl->copy_template);
                $tpl->copy_template = str_replace('[/image-'.$i.']', "", $tpl->copy_template);
                $tpl->copy_template = preg_replace("#\[not-image-{$i}\](.+?)\[/not-image-{$i}\]#is", "",
                    $tpl->copy_template);
            }

        }

        $tpl->copy_template = preg_replace("#\[image-(.+?)\](.+?)\[/image-(.+?)\]#is", "", $tpl->copy_template);
        $tpl->copy_template = preg_replace("#\\{image-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg",
            $tpl->copy_template);
        $tpl->copy_template = preg_replace("#\[not-image-(.+?)\]#i", "", $tpl->copy_template);
        $tpl->copy_template = preg_replace("#\[/not-image-(.+?)\]#i", "", $tpl->copy_template);

    }

    if (stripos($tpl->copy_template, "{fullimage-") !== false) {

        $images = [];
        preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['full_story'], $media);
        $data = preg_replace('/(img|src)("|\'|="|=\')(.*)/i', "$3", $media[0]);

        foreach ($data as $url) {
            $info = pathinfo($url);
            if (isset($info['extension'])) {
                if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus"
                    OR strpos($info['dirname'], 'engine/data/emoticons') !== false
                ) {
                    continue;
                }
                $info['extension'] = strtolower($info['extension']);
                if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif')
                    || ($info['extension'] == 'png')
                    || ($info['extension'] == 'webp')
                ) {
                    array_push($images, $url);
                }
            }
        }

        if (count($images)) {
            $i = 0;
            foreach ($images as $url) {
                $i++;
                $tpl->copy_template = str_replace('{fullimage-'.$i.'}', $url, $tpl->copy_template);
                $tpl->copy_template = str_replace('[fullimage-'.$i.']', "", $tpl->copy_template);
                $tpl->copy_template = str_replace('[/fullimage-'.$i.']', "", $tpl->copy_template);
            }

        }

        $tpl->copy_template = preg_replace("#\[fullimage-(.+?)\](.+?)\[/fullimage-(.+?)\]#is", "", $tpl->copy_template);
        $tpl->copy_template = preg_replace("#\\{fullimage-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg",
            $tpl->copy_template);

    }

    $images     = [];
    $allcontent = $row['full_story'].$row['short_story'].$row['xfields'];
    preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $allcontent, $media);
    $data = preg_replace('/(img|src)("|\'|="|=\')(.*)/i', "$3", $media[0]);

    foreach ($data as $url) {
        $info = pathinfo($url);
        if (isset($info['extension'])) {
            if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus" OR strpos($info['dirname'],
                    'engine/data/emoticons') !== false
            ) {
                continue;
            }
            $info['extension'] = strtolower($info['extension']);
            if (($info['extension'] == 'jpg' || $info['extension'] == 'jpeg' || $info['extension'] == 'gif'
                    || $info['extension'] == 'png'
                    || $info['extension'] == 'webp') AND !in_array($url, $images)
            ) {
                array_push($images, $url);
            }
        }
    }

    if (count($images)) {
        $social_tags['image'] = str_replace("/thumbs/", "/", $images[0]);
        $social_tags['image'] = str_replace("/medium/", "/", $social_tags['image']);
    }

    if (preg_match("#<!--dle_video_begin:(.+?)-->#is", $allcontent, $media)) {
        $media[1] = str_replace("&#124;", "|", $media[1]);

        $media[1] = explode(",", trim($media[1]));

        if (count($media[1]) > 1 AND stripos($media[1][0], "http") === false AND intval($media[1][0])) {
            $media[1] = explode("|", $media[1][1]);
        } else {
            $media[1] = explode("|", $media[1][0]);
        }

        $social_tags['video'] = $media[1][0];

    }

    if (preg_match("#<!--dle_audio_begin:(.+?)-->#is", $allcontent, $media)) {
        $media[1] = str_replace("&#124;", "|", $media[1]);

        $media[1] = explode(",", trim($media[1]));

        if (count($media[1]) > 1 AND stripos($media[1][0], "http") === false AND intval($media[1][0])) {
            $media[1] = explode("|", $media[1][1]);
        } else {
            $media[1] = explode("|", $media[1][0]);
        }

        $social_tags['audio'] = $media[1][0];

    }

    if ($smartphone_detected) {

        if (!$config['allow_smart_format']) {

            $row['short_story'] = strip_tags($row['short_story'], '<p><br><a>');
            $row['full_story']  = strip_tags($row['full_story'], '<p><br><a>');

        } else {

            if (!$config['allow_smart_images']) {

                $row['short_story'] = preg_replace("#<!--TBegin(.+?)<!--TEnd-->#is", "", $row['short_story']);
                $row['short_story'] = preg_replace("#<!--MBegin(.+?)<!--MEnd-->#is", "", $row['short_story']);
                $row['short_story'] = preg_replace("#<img(.+?)>#is", "", $row['short_story']);
                $row['full_story']  = preg_replace("#<!--TBegin(.+?)<!--TEnd-->#is", "", $row['full_story']);
                $row['full_story']  = preg_replace("#<!--MBegin(.+?)<!--MEnd-->#is", "", $row['full_story']);
                $row['full_story']  = preg_replace("#<img(.+?)>#is", "", $row['full_story']);

            }

            if (!$config['allow_smart_video']) {

                $row['short_story'] = preg_replace("#<!--dle_video_begin(.+?)<!--dle_video_end-->#is", "",
                    $row['short_story']);
                $row['short_story'] = preg_replace("#<!--dle_audio_begin(.+?)<!--dle_audio_end-->#is", "",
                    $row['short_story']);
                $row['short_story'] = preg_replace("#<!--dle_media_begin(.+?)<!--dle_media_end-->#is", "",
                    $row['short_story']);
                $row['full_story']  = preg_replace("#<!--dle_video_begin(.+?)<!--dle_video_end-->#is", "",
                    $row['full_story']);
                $row['full_story']  = preg_replace("#<!--dle_audio_begin(.+?)<!--dle_audio_end-->#is", "",
                    $row['full_story']);
                $row['full_story']  = preg_replace("#<!--dle_media_begin(.+?)<!--dle_media_end-->#is", "",
                    $row['full_story']);

            }

        }

    }
    $tpl->set('{comments}', "<!--dlecomments-->");
    $tpl->set('{addcomments}', "<!--dleaddcomments-->");
    $tpl->set('{navigation}', "<!--dlenavigationcomments-->");

    if ($config['image_lazy'] AND $view_template != "print") {
        $row['short_story'] = preg_replace_callback("#<img(.+?)>#i", "enable_lazyload", $row['short_story']);
        $row['full_story']  = preg_replace_callback("#<img(.+?)>#i", "enable_lazyload", $row['full_story']);
    }

    $row['full_story'] = str_replace("{PAGEBREAK}", "", $row['full_story']);
    $row['full_story'] = preg_replace("'\[page=(.*?)\](.*?)\[/page\]'si", "\\2", $row['full_story']);
    $tpl->set_block("'\\[pages\\](.*?)\\[/pages\\]'si", "");
    $tpl->set('{pages}', "");

    $tpl->set('{short-story}', $row['short_story']);

    $tpl->set('{full-story}', $row['full_story']);

    if (preg_match("#\\{full-story limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches)) {
        $count = intval($matches[1]);

        $row['full_story'] = preg_replace("#<!--dle_spoiler(.+?)<!--spoiler_text-->#is", "", $row['full_story']);
        $row['full_story'] = preg_replace("#<!--spoiler_text_end-->(.+?)<!--/dle_spoiler-->#is", "",
            $row['full_story']);
        $row['full_story'] = preg_replace("'\[attachment=(.*?)\]'si", "", $row['full_story']);
        $row['full_story'] = preg_replace("#\[hide(.*?)\](.+?)\[/hide\]#is", "", $row['full_story']);

        $row['full_story'] = str_replace("><", "> <", $row['full_story']);
        $row['full_story'] = strip_tags($row['full_story'], "<br>");
        $row['full_story'] = trim(str_replace("<br>", " ",
            str_replace("<br />", " ", str_replace("\n", " ", str_replace("\r", "", $row['full_story'])))));
        $row['full_story'] = preg_replace('/\s+/u', ' ', $row['full_story']);

        if ($count AND dle_strlen($row['full_story'], $config['charset']) > $count) {

            $row['full_story'] = dle_substr($row['full_story'], 0, $count, $config['charset']);

            if (($temp_dmax = dle_strrpos($row['full_story'], ' ', $config['charset']))) {
                $row['full_story'] = dle_substr($row['full_story'], 0, $temp_dmax, $config['charset']);
            }

        }

        $tpl->set($matches[0], $row['full_story']);

    }

    $tpl->set('{title}',
        str_replace("&amp;amp;", "&amp;", htmlspecialchars($row['title'], ENT_QUOTES, $config['charset'])));

    if (preg_match("#\\{title limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches)) {
        $count        = intval($matches[1]);
        $row['title'] = strip_tags($row['title']);

        if ($count AND dle_strlen($row['title'], $config['charset']) > $count) {

            $row['title'] = dle_substr($row['title'], 0, $count, $config['charset']);

            if (($temp_dmax = dle_strrpos($row['title'], ' ', $config['charset']))) {
                $row['title'] = dle_substr($row['title'], 0, $temp_dmax, $config['charset']);
            }

        }
        $tpl->set($matches[0],
            str_replace("&amp;amp;", "&amp;", htmlspecialchars($row['title'], ENT_QUOTES, $config['charset'])));

    }

    $xfieldsdata = $row['xfields'];
    $category_id = $row['category'];

    $all_xf_content = [];

    if (count($xfields)) {

        $xfieldsdata = xfieldsdataload($row['xfields']);

        foreach ($xfields as $value) {
            $preg_safe_name = preg_quote($value[0], "'");

            if ($value[20]) {

                $value[20] = explode(',', $value[20]);

                if ($value[20][0] AND !in_array($member_id['user_group'], $value[20])) {
                    $xfieldsdata[$value[0]] = "";
                }

            }

            if ($value[3] == "yesorno") {

                if (intval($xfieldsdata[$value[0]])) {
                    $xfgiven                = true;
                    $xfieldsdata[$value[0]] = $lang['xfield_xyes'];
                } else {
                    $xfgiven                = false;
                    $xfieldsdata[$value[0]] = $lang['xfield_xno'];
                }

            } else {

                if ($xfieldsdata[$value[0]] == "") {
                    $xfgiven = false;
                } else {
                    $xfgiven = true;
                }

            }

            if (!$xfgiven) {
                $tpl->copy_template
                                    = preg_replace("'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is",
                    "", $tpl->copy_template);
                $tpl->copy_template = str_ireplace("[xfnotgiven_{$value[0]}]", "", $tpl->copy_template);
                $tpl->copy_template = str_ireplace("[/xfnotgiven_{$value[0]}]", "", $tpl->copy_template);
            } else {
                $tpl->copy_template
                                    = preg_replace("'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is",
                    "", $tpl->copy_template);
                $tpl->copy_template = str_ireplace("[xfgiven_{$value[0]}]", "", $tpl->copy_template);
                $tpl->copy_template = str_ireplace("[/xfgiven_{$value[0]}]", "", $tpl->copy_template);
            }

            if (strpos($tpl->copy_template, "[ifxfvalue {$value[0]}") !== false) {
                $tpl->copy_template = preg_replace_callback("#\\[ifxfvalue(.+?)\\](.+?)\\[/ifxfvalue\\]#is",
                    "check_xfvalue", $tpl->copy_template);
            }

            if ($value[6] AND !empty($xfieldsdata[$value[0]])) {
                $temp_array = explode(",", $xfieldsdata[$value[0]]);
                $value3     = [];

                foreach ($temp_array as $value2) {

                    $value2 = trim($value2);

                    if ($value2) {

                        $value4 = str_replace(["&#039;", "&quot;", "&amp;", "&#123;", "&#91;", "&#58;"],
                            ["'", '"', "&", "{", "[", ":"], $value2);

                        if ($value[3] == "datetime") {

                            $value2 = strtotime($value4);

                            if (!trim($value[24])) {
                                $value[24] = $config['timestamp_active'];
                            }

                            if ($value[25]) {

                                if ($value[26]) {
                                    $value2 = langdate($value[24], $value2);
                                } else {
                                    $value2 = langdate($value[24], $value2, false, $customlangdate);
                                }

                            } else {
                                $value2 = date($value[24], $value2);
                            }

                        }

                        if ($config['allow_alt_url']) {
                            $value3[] = "<a href=\"".$config['http_home_url']."xfsearch/".$value[0]."/"
                                .rawurlencode($value4)."/\">".$value2."</a>";
                        } else {
                            $value3[] = "<a href=\"$PHP_SELF?do=xfsearch&amp;xfname=".$value[0]."&amp;xf="
                                .rawurlencode($value4)."\">".$value2."</a>";
                        }
                    }

                }

                if (empty($value[21])) {
                    $value[21] = ", ";
                }

                $xfieldsdata[$value[0]] = implode($value[21], $value3);

                unset($temp_array);
                unset($value2);
                unset($value3);
                unset($value3);

            } elseif ($value[3] == "datetime" AND !empty($xfieldsdata[$value[0]])) {

                $xfieldsdata[$value[0]] = strtotime(str_replace("&#58;", ":", $xfieldsdata[$value[0]]));

                if (!trim($value[24])) {
                    $value[24] = $config['timestamp_active'];
                }

                if ($value[25]) {

                    if ($value[26]) {
                        $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]]);
                    } else {
                        $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]], false, $customlangdate);
                    }

                } else {
                    $xfieldsdata[$value[0]] = date($value[24], $xfieldsdata[$value[0]]);
                }


            }

            if ($config['allow_links'] AND $value[3] == "textarea" AND function_exists('replace_links')) {
                $xfieldsdata[$value[0]] = replace_links($xfieldsdata[$value[0]], $replace_links['news']);
            }

            if ($value[3] == "image" AND $xfieldsdata[$value[0]]) {

                $temp_array = explode('|', $xfieldsdata[$value[0]]);

                if (count($temp_array) > 1) {

                    $temp_alt   = $temp_array[0];
                    $temp_value = $temp_array[1];

                } else {

                    $temp_alt   = '';
                    $temp_value = $temp_array[0];

                }

                $path_parts = @pathinfo($temp_value);

                if ($value[12] AND file_exists(ROOT_DIR."/uploads/posts/".$path_parts['dirname']."/thumbs/"
                        .$path_parts['basename'])
                ) {
                    $thumb_url = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/thumbs/"
                        .$path_parts['basename'];
                    $img_url   = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/"
                        .$path_parts['basename'];
                } else {
                    $img_url   = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/"
                        .$path_parts['basename'];
                    $thumb_url = "";
                }

                if ($thumb_url) {
                    $tpl->set("[xfvalue_thumb_url_{$value[0]}]", $thumb_url);
                    $xfieldsdata[$value[0]]
                        = "<a href=\"$img_url\" class=\"highslide\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"$thumb_url\" alt=\"{$temp_alt}\"></a>";
                } else {
                    $tpl->set("[xfvalue_thumb_url_{$value[0]}]", $img_url);
                    $xfieldsdata[$value[0]]
                        = "<img class=\"xfieldimage {$value[0]}\" src=\"{$img_url}\" alt=\"{$temp_alt}\">";
                }

                $tpl->set("[xfvalue_image_url_{$value[0]}]", $img_url);

            }

            if ($value[3] == "image" AND !$xfieldsdata[$value[0]]) {

                $tpl->set("[xfvalue_thumb_url_{$value[0]}]", "");
                $tpl->set("[xfvalue_image_url_{$value[0]}]", "");

            }

            if ($value[3] == "imagegalery" AND $xfieldsdata[$value[0]] AND stripos($tpl->copy_template,
                    "[xfvalue_{$value[0]}") !== false
            ) {

                $fieldvalue_arr       = explode(',', $xfieldsdata[$value[0]]);
                $gallery_image        = [];
                $gallery_single_image = [];
                $xf_image_count       = 0;
                $single_need          = false;

                if (stripos($tpl->copy_template, "[xfvalue_{$value[0]} image=") !== false) {
                    $single_need = true;
                }

                foreach ($fieldvalue_arr as $temp_value) {
                    $xf_image_count++;

                    $temp_value = trim($temp_value);

                    if ($temp_value == "") {
                        continue;
                    }

                    $temp_array = explode('|', $temp_value);

                    if (count($temp_array) > 1) {

                        $temp_alt   = $temp_array[0];
                        $temp_value = $temp_array[1];

                    } else {

                        $temp_alt   = '';
                        $temp_value = $temp_array[0];

                    }

                    $path_parts = @pathinfo($temp_value);

                    if ($value[12] AND file_exists(ROOT_DIR."/uploads/posts/".$path_parts['dirname']."/thumbs/"
                            .$path_parts['basename'])
                    ) {
                        $thumb_url = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/thumbs/"
                            .$path_parts['basename'];
                        $img_url   = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/"
                            .$path_parts['basename'];
                    } else {
                        $img_url   = $config['http_home_url']."uploads/posts/".$path_parts['dirname']."/"
                            .$path_parts['basename'];
                        $thumb_url = "";
                    }

                    if ($thumb_url) {

                        $gallery_image[]
                            = "<li><a href=\"$img_url\" onclick=\"return hs.expand(this, { slideshowGroup: 'xf_{$row['id']}_{$value[0]}' })\" target=\"_blank\"><img src=\"{$thumb_url}\" alt=\"{$temp_alt}\"></a></li>";
                        $gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]']
                            = "<a href=\"{$img_url}\" class=\"highslide\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"{$thumb_url}\" alt=\"{$temp_alt}\"></a>";

                    } else {
                        $gallery_image[] = "<li><img src=\"{$img_url}\" alt=\"{$temp_alt}\"></li>";
                        $gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]']
                                         = "<img class=\"xfieldimage {$value[0]}\" src=\"{$img_url}\" alt=\"{$temp_alt}\">";
                    }

                }

                if ($single_need AND count($gallery_single_image)) {
                    foreach ($gallery_single_image as $temp_key => $temp_value) {
                        $tpl->set($temp_key, $temp_value);
                    }
                }

                $xfieldsdata[$value[0]] = "<ul class=\"xfieldimagegallery {$value[0]}\">".implode($gallery_image)
                    ."</ul>";

            }

            if ($config['image_lazy'] AND $view_template != "print") {
                $xfieldsdata[$value[0]] = preg_replace_callback("#<img(.+?)>#i", "enable_lazyload",
                    $xfieldsdata[$value[0]]);
            }

            $tpl->set("[xfvalue_{$value[0]}]", $xfieldsdata[$value[0]]);

            $all_xf_content[] = $xfieldsdata[$value[0]];

            if (preg_match("#\\[xfvalue_{$preg_safe_name} limit=['\"](.+?)['\"]\\]#i", $tpl->copy_template, $matches)) {
                $count = intval($matches[1]);

                $xfieldsdata[$value[0]] = str_replace("><", "> <", $xfieldsdata[$value[0]]);
                $xfieldsdata[$value[0]] = strip_tags($xfieldsdata[$value[0]], "<br>");
                $xfieldsdata[$value[0]] = trim(str_replace("<br>", " ", str_replace("<br />", " ",
                    str_replace("\n", " ", str_replace("\r", "", $xfieldsdata[$value[0]])))));
                $xfieldsdata[$value[0]] = preg_replace('/\s+/u', ' ', $xfieldsdata[$value[0]]);

                if ($count AND dle_strlen($xfieldsdata[$value[0]], $config['charset']) > $count) {

                    $xfieldsdata[$value[0]] = dle_substr($xfieldsdata[$value[0]], 0, $count, $config['charset']);

                    if (($temp_dmax = dle_strrpos($xfieldsdata[$value[0]], ' ', $config['charset']))) {
                        $xfieldsdata[$value[0]] = dle_substr($xfieldsdata[$value[0]], 0, $temp_dmax,
                            $config['charset']);
                    }

                }

                $tpl->set($matches[0], $xfieldsdata[$value[0]]);

            }
        }
    }

    $tpl->compile('afs');

    if (stripos($tpl->result['afs'], "[hide") !== false) {

        $tpl->result['afs'] = preg_replace_callback("#\[hide(.*?)\](.+?)\[/hide\]#is",
            function ($matches) use ($member_id, $user_group, $lang) {

                $matches[1] = str_replace(["=", " "], "", $matches[1]);
                $matches[2] = $matches[2];

                if ($matches[1]) {

                    $groups = explode(',', $matches[1]);

                    if (in_array($member_id['user_group'], $groups) OR $member_id['user_group'] == "1") {
                        return $matches[2];
                    } else {
                        return "<div class=\"quote dlehidden\">".$lang['news_regus']."</div>";
                    }

                } else {

                    if ($user_group[$member_id['user_group']]['allow_hide']) {
                        return $matches[2];
                    } else {
                        return "<div class=\"quote dlehidden\">".$lang['news_regus']."</div>";
                    }

                }

            }, $tpl->result['afs']);
    }

    if ($config['allow_banner'] AND count($banner_in_news)) {

        foreach ($banner_in_news as $name) {
            $tpl->result['afs'] = str_replace("{banner_".$name."}", $banners[$name], $tpl->result['afs']);

            if ($banners[$name]) {
                $tpl->result['afs'] = str_replace("[banner_".$name."]", "", $tpl->result['afs']);
                $tpl->result['afs'] = str_replace("[/banner_".$name."]", "", $tpl->result['afs']);
            }
        }

        $tpl->result['afs'] = preg_replace("'\\[banner_(.*?)\\](.*?)\\[/banner_(.*?)\\]'si", '', $tpl->result['afs']);

    }

    $news_id = $row['id'];


    if ($config['files_allow'] AND $news_found) {
        if (strpos($tpl->result['afs'], "[attachment=") !== false) {
            $tpl->result['afs'] = show_attach($tpl->result['afs'], $news_id);
        }
    }

    $tpl->clear();

    $afs = $tpl->result['afs'];

}

unset($row);

// Устанавливаем правильные заголовки
$seconds = 86400; // 1 день для кеша в браузере

// Определяем дату создания кеша, при использовании файлового кеша
// @TODO: наладить Expires при работе с мемкешем.
$_end_file = ($is_logged) ? '_'.$member_id['user_group'] : '_0';
$filedate  = ENGINE_DIR.'/cache/'.$afsCfg['cachePrefix'].'_'.md5($cacheName).$_end_file.'.tmp';

header('Content-Type: text/html; charset='.$config['charset']);
header('Cache-Control: public, max-age='.$seconds);

if (!file_exists($filedate)) {
    $lastModified = $_TIME;
} else {
    $etag         = md5_file($filedate);
    $lastModified = filemtime($filedate);

    header("Expires: ".gmdate("D, d M Y H:i:s", $lastModified + $seconds)." GMT");
    header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");

    header("Etag: $etag");
    if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified
        || @trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag
    ) {
        header("HTTP/1.1 304 Not Modified");
        exit;
    }
}

// Выводим результат
echo $afs;