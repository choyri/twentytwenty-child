<?php

const TWENTYTWENTY_PATH = '/wp-content/themes/twentytwenty';
const TWENTYTWENTY_URL_CDN_ROOT = 'https://cdn.jsdelivr.net/wp/themes/twentytwenty/1.1';

function enqueueChildStyleAndScript()
{
    $parentStyle = 'twentytwenty-style';

    wp_enqueue_style($parentStyle, TWENTYTWENTY_URL_CDN_ROOT . '/style.css');
    wp_enqueue_style('twentytwenty-child-style', get_stylesheet_uri(), [$parentStyle], wp_get_theme()->get('Version'));
    wp_enqueue_style('google-font', 'https://fonts.googleapis.com/css?family=Noto+Serif+SC&display=swap');

    wp_enqueue_script('instantpage', 'https://cdn.jsdelivr.net/npm/instant.page@3.0.0/instantpage.min.js', [], false, true);
    wp_script_add_data('instantpage', 'defer', true);
}

add_action('wp_enqueue_scripts', 'enqueueChildStyleAndScript');

function replaceAssetsURL(string $src, string $handle): string
{
    switch ($handle) {
        case 'twentytwenty-print-style':
        case 'twentytwenty-js':
            $length = strpos($src, TWENTYTWENTY_PATH) + strlen(TWENTYTWENTY_PATH);
            return substr_replace($src, TWENTYTWENTY_URL_CDN_ROOT, 0, $length);
        default:
            return $src;
    }
}

add_filter('style_loader_src', 'replaceAssetsURL', 10, 2);
add_filter('script_loader_src', 'replaceAssetsURL', 10, 2);

function changeTypeOfInstantpage(string $tag, string $handle): string
{
    if ('instantpage' !== $handle) {
        return $tag;
    }

    $tag = str_replace('type="text/javascript" ', '', $tag);
    $tag = str_replace('<script', '<script type="module"', $tag);

    return $tag;
}

add_filter('script_loader_tag', 'changeTypeOfInstantpage', 10, 2);

function addFavicon(string $url): string
{
    return 'https://img.choyri.com/favicon.ico';
}

add_action('get_site_icon_url', 'addFavicon');

function modifyDocumentTitleParts(array $title): array
{
    if (is_front_page()) {
        unset($title['tagline']);
    }

    if (is_search()) {
        $title['title'] = str_replace(['&#8220;', '&#8221;'], ['「', '」'], $title['title']);
    }

    return $title;
}

add_filter('document_title_parts', 'modifyDocumentTitleParts');

function customPasswordForm(string $output): string
{
    return (is_home() || is_category()) ? '这是一篇受密码保护的文章 😑' : $output;
}

add_filter('the_password_form', 'customPasswordForm');

function autoAddMoreForPost(WP_Post &$post)
{
    if (is_admin()) {
        return;
    }

    $post->post_content = trim($post->post_content);

    // 分割段落
    preg_match_all('/\n+\s+/', $post->post_content, $eolMatches, PREG_OFFSET_CAPTURE);

    $getPos = function (array $matches, int $paragraph = 2): int {
        $wantDirectReturn = false;

        while (true) {
            if (!isset($matches[0][$paragraph - 1])) {
                return 0;
            }

            // 因为用了 PREG_OFFSET_CAPTURE，多了一个 [1] 用于表示 pos
            $pos = $matches[0][$paragraph - 1][1];

            if ($wantDirectReturn) {
                return $pos;
            }

            // 如果长度不够 300，继续往下一段落探索
            if ($pos < 300) {
                $paragraph++;
                continue;
            }

            // 如果长度大于 1000，太长了，返回上一段落
            if ($pos > 1000) {
                $paragraph--;
                $wantDirectReturn = true;
                continue;
            }

            // 长度还可以，返回吧
            return $pos;
        }

        return 0;
    };


    if ($pos = $getPos($eolMatches)) {
        $firstPrePos = strpos(substr($post->post_content, 0, $pos), '<pre');

        if ($firstPrePos) {
            $isNoClosingTag = strpos(substr($post->post_content, $firstPrePos, $pos), '</pre>') === false;

            if ($isNoClosingTag) {
                $pos = $firstPrePos;
            }
        }

        $replacement = PHP_EOL . '<!--more-->' . PHP_EOL;
        $post->post_content = substr_replace($post->post_content, $replacement, $pos, 0);
    }
}

add_action('the_post', 'autoAddMoreForPost');

function customOriginalExcerpt(string $excerpt, WP_Post $post): string
{
    if (is_search()) {
        $ret = preg_replace('/\s*/', '', strip_tags($post->post_content));

        $s = get_search_query();
        $keywords = preg_split('/\s+/', $s);

        $firstKeywordLen = mb_strlen($keywords[0]);
        $firstKeywordPos = mb_stripos($ret, $keywords[0]);

        if ($firstKeywordPos === false) {
            return $excerpt;
        }

        $start = $firstKeywordPos > 10 ? $firstKeywordPos - 10 : 0;
        $length = $firstKeywordLen > 100 ? $firstKeywordLen : 100;

        $ret = mb_strimwidth($ret, $start, $length * 2, '……');

        if ($start > 0) {
            $ret = '…' . $ret;
        }

        foreach ($keywords as $v) {
            $replacement = '<em class="highlight">' . $v . '</em>';
            $ret = preg_replace('/(' . $v . ')/is', $replacement, $ret);
        }

        return $ret;
    }

    return $excerpt;
}

add_filter('get_the_excerpt', 'customOriginalExcerpt', 10, 2);

function customFontFamily(array $fontFamily): array
{
    // https://picturepan2.github.io/spectre/elements/typography.html#typography-fonts

    $fontFamily['zh-CN'] = [
        '\'Noto Serif SC\'',
        '-apple-system', 'system-ui', 'BlinkMacSystemFont',
        '\'Segoe UI\'', 'Roboto', '\'PingFang SC\'', '\'Hiragino Sans GB\'',
        '\'Microsoft YaHei New\'', '\'Microsoft YaHei\'', '\'Helvetica Neue\'', 'sans-serif',
    ];

    return $fontFamily;
}

add_filter('twentytwenty_get_localized_font_family_types', 'customFontFamily');

function removeAuthorOfPostMeta(array $postMeta): array
{
    foreach ($postMeta as $key => $meta) {
        if ($meta === 'author' || $meta === 'comments' && is_home()) {
            unset($postMeta[$key]);
        }
    }

    return $postMeta;
}

add_filter('twentytwenty_post_meta_location_single_top', 'removeAuthorOfPostMeta', 10, 2);

function modifyArchiveWidgetType(array $args): array
{
    $args['type'] = 'yearly';

    return $args;
}

add_filter('widget_archives_args', 'modifyArchiveWidgetType');
