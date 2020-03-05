<?php

function twentytwentyChildRegisterStyles()
{
    $parentStyle = 'twentytwenty-style';

    wp_enqueue_style($parentStyle, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('twentytwenty-child-style', get_stylesheet_uri(), [$parentStyle], wp_get_theme()->get('Version'));
}

add_action('wp_enqueue_scripts', 'twentytwentyChildRegisterStyles');
