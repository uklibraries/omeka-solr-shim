<?php
namespace ExploreUK;

define('EUK_BASE_DIR', dirname(dirname(dirname(dirname(__FILE__)))));
define('EUK_BACK_TO_SEARCH_TEXT', 'Back to Search Results');
define('EUK_DETAIL_FIELD_ORDER', array(
    'usage_display',
    'accession_number_s',
    'container_list_s',
    'contributor_s',
    'coverage_display',
    'subject_topic_facet',
    'publisher_display',
    'format',
    'id',
    'finding_aid_url_s',
    'mets_url_display',
));
define('EUK_FACETABLE', array(
    'source_s',
    'subject_topic_facet',
    'format',
    'pub_date',
));
define('EUK_FACETS', array('format', 'pub_date', 'source_s'));
define('EUK_HIT_FIELDS', array(
    'title' => 'title_display',
    'thumb' => 'thumbnail_url_s',
    'source' => 'source_s',
    'pubdate' => 'pub_date',
    'format' => 'format',
));
define('EUK_LOCALE', array(
    'en' => array(
        'accession_number_s' => 'Accession Number',
        'author_display' => 'Creator',
        'container_list_s' => 'Containers',
        'contributor_s' => 'Contributor',
        'coverage_display' => 'Geographic Subject',
        'description_display' => 'Description',
        'facet_menu_title' => 'Filter Your Results',
        'finding_aid_url_s' => 'XML Collection Guide',
        'format' => 'Format',
        'id' => 'Permalink',
        'language_display' => 'Language',
        'mets_url_display' => 'XML Metadata',
        'pub_date' => 'Date',
        'publisher_display' => 'Publisher',
        'collection_url' => 'Collection',
        'source_s' => 'Collection',
        'subject_topic_facet' => 'Library of Congress Subject Headings',
        'usage_display' => 'Rights',
        'open_collection_guide' => 'collection guide',
        'more_items' => 'more from this collection',
    ),
));
define('EUK_MAX_LABEL', 80);
define('EUK_PER_PAGE_OPTS', array(20, 50, 100));
define('EUK_REQUIRES_CAPITALIZATION', array(
    'language_display',
));
define('EUK_RESULT_DROP_FIELDS', array(
    'format',
));
define('EUK_RESULT_FACET_ORDER', array(
    'source',
    'pubdate',
    'format',
));
define('EUK_TEMPLATE_DIR', dirname(__FILE__) . '/templates');
define('EUK_TITLE_FIELD_ORDER', array(
    'pub_date',
    'author_display',
    'language_display',
    'collection_url', # NOTE: this implies source_s
    'description_display',
));

/* helper functions */

function type_for($format, $type)
{
    $type_for = array(
        'archival material' => 'collection',
        'athletic publications' => 'text',
        'books' => 'text',
        'collections' => 'collection',
        'course catalogs' => 'text',
        'directories' => 'text',
        'images' => 'image',
        'journals' => 'text',
        'ledgers' => 'text',
        'maps' => 'image',
        'minutes' => 'text',
        'newspapers' => 'text',
        'oral histories' => 'sound',
        'scrapbooks' => array('text', 'image'),
        'theses' => 'text',
        'yearbooks' => array('text', 'image'),
    );
    if (array_key_exists($format, $type_for)) {
        return $type_for[$format];
    } else {
        return $type;
    }
}

function brevity($message, $length = 0)
{
    if ($length == 0) {
        $length = EUK_MAX_LABEL;
    }
    if (strlen($message) <= $length) {
        return $message;
    }
    $source_words = preg_split('/\b/', $message);
    $target_words = array();
    $current_length = 0;
    foreach ($source_words as $word) {
        if (($current_length == 0) || $current_length + strlen($word) <= $length) {
            $target_words[] = $word;
            $current_length += strlen($word);
        } else {
            break;
        }
    }
    $count = count($target_words);
    if ($count == 0) {
        $message = '…';
    } else {
        $terminal = $target_words[$count - 1];
        if (preg_match('/^\W+$/', $terminal)) {
            array_pop($target_words);
        }
        $message = implode('', $target_words) . '…';
    }
    return $message;
}

function facet_displayname($facet)
{
    $euk_locale = EUK_LOCALE;
    if (isset($euk_locale['en'][$facet])) {
        return ucfirst($euk_locale['en'][$facet]);
    } else {
        return 'unknown';
    }
}

function navsHashFromFlatList($navs)
{
    $hash = array();
    for ($i = 0; $i < count($navs); $i += 2) {
        $hash[$navs[$i]] = $navs[$i + 1];
    }
    return $hash;
}
