<?php

function twentytwentyChildRegisterStyles()
{
    $parentStyle = 'twentytwenty-style';

    wp_enqueue_style($parentStyle, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('twentytwenty-child-style', get_stylesheet_uri(), [$parentStyle], wp_get_theme()->get('Version'));
}

add_action('wp_enqueue_scripts', 'twentytwentyChildRegisterStyles');

function customPasswordForm(string $output): string
{
    return is_home() ? '这是一篇受密码保护的文章 😑' : $output;
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
