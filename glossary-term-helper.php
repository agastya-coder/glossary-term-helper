<?php
/**
 * Plugin Name:  Glossary-Term-Helper
 * Description:  Lightweight auto-tooltip glossary with Sanskrit support.
 * Version:      0.1.0
 * Author:       Svami Ladili
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ----------------------------------------------------------
 *  CONSTANTS
 * ---------------------------------------------------------- */
define( 'GTH_PATH', plugin_dir_path( __FILE__ ) );
define( 'GTH_URL',  plugin_dir_url( __FILE__ ) );

/* ----------------------------------------------------------
 *  AUTOLOADER (very small)
 * ---------------------------------------------------------- */
foreach ( glob( GTH_PATH . 'admin/*.php' ) as $file ) require_once $file;

/* ----------------------------------------------------------
 *  ENQUEUE ASSETS
 * ---------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'gth_assets' );
function gth_assets() {
    wp_enqueue_style(  'gth-css', GTH_URL . 'assets/css/gth-frontend.css', [], '1.0.0' );
    wp_enqueue_script( 'gth-js',  GTH_URL . 'assets/js/gth-frontend.js', [ 'jquery' ], '1.0.0', true );
}

/* ----------  PRINT DYNAMIC CSS  ---------- */
add_action( 'wp_head', function () {
    $uc = get_option( 'gth_underline_color', '#fff' );
    $us = get_option( 'gth_underline_style', 'dotted' );
    $bg = get_option( 'gth_tooltip_bg', '#222' );
    $tc = get_option( 'gth_tooltip_text_color', '#fff' );
    $fs = (int) get_option( 'gth_tooltip_font_size', 16 );
    echo "
    <style>
    .gth-term { border-bottom: 1px {$us} {$uc} !important; }
    .gth-term::after {
        background: {$bg};
        color: {$tc};
        font-size: {$fs}px;
    }
    </style>
    ";
} );

/* ----------------------------------------------------------
 *  CPT: Glossary Term
 * ---------------------------------------------------------- */
add_action( 'init', 'gth_register_cpt' );
function gth_register_cpt() {
    register_post_type( 'gth_term', [
        'labels'        => [ 'name' => 'Glossary Terms', 'singular_name' => 'Term' ],
        'public'        => false,
        'show_ui'       => true,
        'menu_icon'     => 'dashicons-book-alt',
        'supports'      => [ 'title', 'editor', 'thumbnail' ],
    ] );
}

/* ----------------------------------------------------------
 *  REST ENDPOINT for AJAX search
 * ---------------------------------------------------------- */
add_action( 'rest_api_init', function () {
    register_rest_route( 'gth/v1', '/search/(?P<word>.+)', [
        'methods'  => 'GET',
        'callback' => 'gth_rest_search',
        'args'     => [ 'word' => [ 'sanitize_callback' => 'sanitize_text_field' ] ]
    ] );
} );
function gth_rest_search( WP_REST_Request $r ) {
    global $wpdb;
    $word = $r['word'];
    $case = get_option( 'gth_case_sensitive', 0 ) ? 'BINARY' : '';
    $sql  = "SELECT post_title, post_content FROM {$wpdb->posts}
             WHERE post_type = 'gth_term'
             AND post_status = 'publish'
             AND {$case} post_title LIKE %s";
    $like = $case ? $word : "%{$word}%";
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $like ) );
    return rest_ensure_response( $rows );
}

/* ----------------------------------------------------------
 *  FRONT-END HIGHLIGHTER
 * ---------------------------------------------------------- */
add_filter( 'the_content', 'gth_highlight_terms', PHP_INT_MAX );
add_filter( 'widget_text', 'gth_highlight_terms', PHP_INT_MAX );
add_filter( 'nav_menu_item_title', 'gth_highlight_terms', PHP_INT_MAX );

// Force scan on the entire final HTML (front-end only)
add_action( 'template_redirect', function () {
    if ( is_admin() ) return;
    ob_start( function ( $html ) {
        return gth_highlight_terms( $html );
    } );
} );
////
function gth_highlight_terms( $content ) {
    if ( is_admin() || empty( $content ) ) {
        return $content;
    }

    /* 1.  Build a list of every form we must wrap  */
    $forms = [];
    foreach ( get_posts( [ 'post_type' => 'gth_term', 'numberposts' => -1 ] ) as $t ) {
        $desc = esc_attr( wp_strip_all_tags( $t->post_content ) );

        // Always include the bare title
        $forms[ $t->post_title ] = $desc;

        // Plus any comma-separated synonyms
        $syn = get_post_meta( $t->ID, '_gth_synonyms', true );
        foreach ( array_filter( array_map( 'trim', explode( ',', $syn ) ) ) as $s ) {
            if ( $s !== '' ) {
                $forms[ $s ] = $desc;
            }
        }
    }

    if ( empty( $forms ) ) {
        return $content;
    }

    /* 2.  Sort longest → shortest to avoid “ātma” hiding “ātman” */
    uksort( $forms, function ( $a, $b ) {
        return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' );
    } );

    /* 3.  Build ONE regex with every form */
    $regex = '/('
           . implode( '|', array_map( fn( $w ) => preg_quote( $w, '/' ), array_keys( $forms ) ) )
           . ')/iu';

    /* 4.  Wrap the first occurrence of each form */
    $content = preg_replace_callback(
        $regex,
        function ( $m ) use ( $forms ) {
            $w = $m[0];
            return '<span class="gth-term" data-desc="' . $forms[ $w ] . '">' . $w . '</span>';
        },
        $content,
        1   // only one wrap per distinct form
    );

    return $content;
}
////
/* ----------------------------------------------------------
 * 1.  GLOSSARY INDEX SHORTCODE  [gth_index]
 * ---------------------------------------------------------- */
add_shortcode( 'gth_index', function () {
    $terms = get_posts( [
        'post_type'      => 'gth_term',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    ] );
    if ( empty( $terms ) ) return '';

    $out = '<div class="gth-index">';
    foreach ( $terms as $t ) {
        $syn = esc_html( get_post_meta( $t->ID, '_gth_synonyms', true ) );
        $syn = $syn ? ' <em>(' . $syn . ')</em>' : '';
        $out .= '<div class="gth-index-item">';
        $out .= '<h3>' . esc_html( $t->post_title ) . $syn . '</h3>';
        $out .= '<div class="gth-index-desc">' . wpautop( $t->post_content ) . '</div>';
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
} );

/* ----------------------------------------------------------
 * 2.  CSV IMPORTER ADMIN PAGE
 * ---------------------------------------------------------- */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=gth_term',
        'Import CSV',
        'Import CSV',
        'manage_options',
        'gth_import',
        'gth_import_page'
    );
} );

function gth_import_page() {
    echo '<div class="wrap"><h1>Import Sanskrit Glossary CSV</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field( 'gth_import', 'gth_import_nonce' );
    echo '<input type="file" name="gth_csv" accept=".csv"><br>';
    submit_button( 'Upload & Import' );
    echo '</form>';

    if ( ! empty( $_FILES['gth_csv']['tmp_name'] ) && check_admin_referer( 'gth_import', 'gth_import_nonce' ) ) {
        $fp = fopen( $_FILES['gth_csv']['tmp_name'], 'r' );
        $count = 0;
        while ( ( $row = fgetcsv( $fp ) ) !== false ) {
            [$term, $desc, $syn] = array_pad( $row, 3, '' );
            $pid = wp_insert_post( [
                'post_type'   => 'gth_term',
                'post_title'  => $term,
                'post_content'=> $desc,
                'post_status' => 'publish',
            ] );
            update_post_meta( $pid, '_gth_synonyms', $syn );
            $count++;
        }
        echo "<p><strong>{$count}</strong> terms imported.</p>";
    }
    echo '</div>';
}
