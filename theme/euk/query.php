<?php
function euk_on_front_page() {
    global $euk_query;
    return ($_SERVER['REQUEST_URI'] === '/') || ($_SERVER['REQUEST_URI'] === '/index.php?action=index');
}

function euk_initialize_id() {
    global $euk_id;
    if (isset($_GET['id'])) {
        $euk_id = $_GET['id'];
    }
    else {
        $euk_id = 'unknown';
    }
}

function euk_initialize_query() {
    global $euk_query;
    $euk_query = array(
        'q' => null,
        'fq' => array(),
        'f' => array(),
        'offset' => 0,
        'rows' => 20,
        'ui' => null,
    );
    $raw_params = array();
    if (isset($_SERVER['QUERY_STRING'])) {
        $raw_params = explode('&', str_replace('?', '', $_SERVER['QUERY_STRING']));
    }
    foreach ($raw_params as $raw_param) {
        preg_match('/(?<key>[^=]+)=(?<value>.*)/', $raw_param, $matches);
        if (count($matches) > 0) {
            $key = urldecode($matches['key']);
            $value = urldecode($matches['value']);
            if ($key == 'q' and strlen($value) > 0) {
                $euk_query['q'] = $value;
            }
            elseif ($key == 'fq[]') {
                $euk_query['fq'][] = $value;
            }
            elseif (substr($key, 0, 2) == 'f[') {
                $subkey = substr($key, 2, -3);
                $euk_query['f'][$subkey] = $value;
            }
            elseif ($key == 'offset') {
                $euk_query['offset'] = intval($value);
            }
            elseif ($key == 'per_page') {
                $euk_query['rows'] = intval($value);
            }
            elseif ($key == 'ui') {
                switch ($value) {
                case '1':
                    /* fall through */
                case '2':
                    $euk_query['ui'] = $value;
                    break;

                default:
                    /* do nothing */
                    break;
                }
            }
        }
    }
    return $euk_query;
}

function euk_link_to_query($query) {
    $pieces = array();
    if (strlen($query['q']) > 0) {
        $pieces[] = 'q=' . urlencode($query['q']);
    }
    foreach ($query['fq'] as $fq_term) {
        $pieces[] = 'fq[]=' . urlencode($fq_term);
    }
    foreach ($query['f'] as $f_term => $value) {
        $pieces[] = urlencode("f[$f_term][]") . '=' . urlencode($value);
    }
    if (!isset($query['offset'])) {
        $query['offset'] = 0;
    }
    if ($query['offset'] > 0) {
        $pieces[] = 'offset=' . urlencode($query['offset']);
    }
    if ($query['rows'] > 0) {
        $pieces[] = 'per_page=' . urlencode($query['rows']);
    }
    if (isset($query['ui'])) {
        $pieces[] = 'ui=' . urlencode($query['ui']);
    }
    return '?' . implode('&', $pieces);
}

function euk_previous_link() {
    global $euk_query;
    $offset = $euk_query['offset'] - $euk_query['rows'];
    if ($offset > 0) {
        $previous_query['offset'] = $offset;
    }
    else {
        $offset = 0;
    }
    return euk_link_to_query(array(
        'q' => $euk_query['q'],
        'fq' => $euk_query['fq'],
        'f' => $euk_query['f'],
        'offset' => $offset,
        'rows' => $euk_query['rows'],
        'ui' => $euk_query['ui'],
    ));
}

function euk_next_link() {
    global $euk_query;
    $offset = $euk_query['offset'] + $euk_query['rows'];
    if ($offset > 0) {
        $previous_query['offset'] = $offset;
    }
    else {
        $offset = 0;
    }
    return euk_link_to_query(array(
        'q' => $euk_query['q'],
        'fq' => $euk_query['fq'],
        'f' => $euk_query['f'],
        'offset' => $offset,
        'rows' => $euk_query['rows'],
        'ui' => $euk_query['ui'],
    ));
}

function euk_add_filter($facet, $label) {
    global $euk_query;
    $f = $euk_query['f'];
    $f[$facet] = $label;
    return euk_link_to_query(array(
        'q' => $euk_query['q'],
        'fq' => $euk_query['fq'],
        'f' => $f,
        'rows' => $euk_query['rows'],
        'ui' => $euk_query['ui'],
    ));
}

function euk_remove_search_term($label) {
    global $euk_query;
    return euk_link_to_query(array(
        'q' => '',
        'fq' => $euk_query['fq'],
        'f' => $euk_query['f'],
        'rows' => $euk_query['rows'],
        'ui' => $euk_query['ui'],
    ));
}

function euk_remove_filter($facet, $label) {
    global $euk_query;
    $f = array();
    foreach ($euk_query['f'] as $potential_term => $label) {
        if ($potential_term != $facet) {
            $f[$potential_term] = $label;
        }
    }
    return euk_link_to_query(array(
        'q' => $euk_query['q'],
        'fq' => $euk_query['fq'],
        'f' => $f,
        'rows' => $euk_query['rows'],
        'ui' => $euk_query['ui'],
    ));
}

function euk_get_facets_by_count() {
    global $euk_solr;
    $url = "$euk_solr?" . euk_build_search_params_by_count();
    return json_decode(file_get_contents($url), true);
}

function euk_build_search_params_by_count() {
    global $euk_query;
    global $facets;
    $q = $euk_query['q'];
    $fq = $euk_query['fq'];
    $f = $euk_query['f'];
    $pieces = array();
    $pieces[] = 'rows=0';
    $pieces[] = 'wt=json';
    $pieces[] = 'q=' . urlencode($q);
    if (count($facets) > 0) {
        $pieces[] = 'facet=true';
        $pieces[] = 'facet.mincount=1';
        $pieces[] = 'facet.limit=-1';
        foreach ($facets as $facet) {
            $pieces[] = "facet.field=$facet";
        }
        $pieces[] = 'facet.sort=count';
    }
    if (count($fq) > 0) {
        foreach ($fq as $spec) {
            $pieces[] = 'fq=' . urlencode($spec);
        }
    }
    if (count($f) > 0) {
        foreach ($f as $label => $value) {
            $pieces[] = 'fq=' . urlencode("{!raw f=$label}$value");
        }
    }
    # compound object
    $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
    return implode('&', $pieces);
}

function euk_get_facets_by_index() {
    global $euk_solr;
    $url = "$euk_solr?" . euk_build_search_params_by_index();
    return json_decode(file_get_contents($url), true);
}

function euk_build_search_params_by_index() {
    global $euk_query;
    global $facets;
    $q = $euk_query['q'];
    $fq = $euk_query['fq'];
    $f = $euk_query['f'];
    $pieces = array();
    $pieces[] = 'rows=0';
    $pieces[] = 'wt=json';
    $pieces[] = 'q=' . urlencode($q);
    if (count($facets) > 0) {
        $pieces[] = 'facet=true';
        $pieces[] = 'facet.mincount=1';
        $pieces[] = 'facet.limit=-1';
        foreach ($facets as $facet) {
            $pieces[] = "facet.field=$facet";
        }
        $pieces[] = 'facet.sort=index';
    }
    if (count($fq) > 0) {
        foreach ($fq as $spec) {
            $pieces[] = 'fq=' . urlencode($spec);
        }
    }
    if (count($f) > 0) {
        foreach ($f as $label => $value) {
            $pieces[] = 'fq=' . urlencode("{!raw f=$label}$value");
        }
    }
    # compound object
    $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
    return implode('&', $pieces);
}

function euk_get_search_results() {
    global $euk_solr;
    $url = "$euk_solr?" . euk_build_search_params();
    return json_decode(file_get_contents($url), true);
}

function euk_build_search_params() {
    global $euk_query;
    global $facets;
    $q = $euk_query['q'];
    $fq = $euk_query['fq'];
    $f = $euk_query['f'];
    $offset = $euk_query['offset'];
    $pieces = array();
    $pieces[] = 'rows=' . $euk_query['rows'];
    $pieces[] = 'wt=json';
    $pieces[] = 'q=' . urlencode($q);
    if ($offset > 0) {
        $pieces[] = "start=$offset";
    }
    if (count($facets) > 0) {
        $pieces[] = 'facet=true';
        $pieces[] = 'facet.mincount=1';
        $pieces[] = 'facet.limit=20';
        foreach ($facets as $facet) {
            $pieces[] = "facet.field=$facet";
        }
    }
    if (count($fq) > 0) {
        foreach ($fq as $spec) {
            $pieces[] = 'fq=' . urlencode($spec);
        }
    }
    if (count($f) > 0) {
        foreach ($f as $label => $value) {
            $pieces[] = 'fq=' . urlencode("{!raw f=$label}$value");
        }
    }
    # compound object
    $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
    return implode('&', $pieces);
}

function euk_get_document($id) {
    global $euk_solr;
    $url = "$euk_solr?" . euk_document_query($id);
    $result = json_decode(file_get_contents($url), true);
    if (isset($result['response']) and count($result['response']['docs']) > 0) {
        return $result['response']['docs'][0];
    }
    else {
        return null;
    }
}

function euk_document_query($id) {
    $pieces = array();
    $pieces[] = 'fq=' . urlencode("id:$id");
    $pieces[] = 'fl=' . urlencode("*");
    $pieces[] = 'wt=json';
    return implode('&', $pieces);
}

function euk_get_pages($id) {
    global $euk_solr;
    $url = "$euk_solr?" . euk_pages_query($id);
    $result = json_decode(file_get_contents($url), true);
    if (isset($result['response']) and count($result['response']['docs']) > 0) {
        return $result['response']['docs'];
    }
    else {
        return null;
    }
}

function euk_pages_query($id) {
    $parent = preg_replace('/_[^_]+$/', '', $id);
    $pieces = array();
    $pieces[] = 'fq=' . urlencode("parent_id_s:$parent");
    $pieces[] = 'wt=json';
    $pieces[] = 'fl=' . urlencode('id,reference_image_url_s,reference_image_width_s,reference_image_height_s');
    $pieces[] = 'rows=10000';
    $pieces[] = 'sort=browse_key_sort+asc';
    return implode('&', $pieces);
}

function euk_back_to_search() {
    global $euk_query;
    return json_encode(euk_link_to_query($euk_query));
}
