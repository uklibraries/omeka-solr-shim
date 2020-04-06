<?php
namespace ExploreUK;

class ExploreUK
{
    private $config;
    private $omeka;

    public function __construct()
    {
        $this->omeka = new OmekaShim();
        $this->config = array(
            'base' => '',
            'theme' => $this->omeka->getOption('public_theme'),
        );
        if (realpath(EUK_BASE_DIR) !== realpath($_SERVER['DOCUMENT_ROOT'])) {
            $this->config['base'] = basename(EUK_BASE_DIR) . '/';
        }
        if ($this->omeka->getThemeOption('euk_dip_store_base_url') === 'https://nyx.uky.edu/dips') {
            $this->config['prod'] = true;
        } elseif ($this->omeka->getThemeOption('euk_dip_store_base_url') === 'https://exploreuk.uky.edu/dips') {
            $this->config['prod'] = true;
        } else {
            $this->config['prod'] = false;
        }
    }

    private function configure()
    {
        $this->config['solr'] = $this->omeka->getThemeOption('euk_solr');
        $this->config['query'] = new Query(array(), $this->config['solr']);
        $this->config['fa_base'] = $this->omeka->getThemeOption('euk_findingaid_base_url');
        $this->config['logo'] = $this->omeka->getThemeOption('logo');
        $this->config['simple_pages'] = $this->omeka->getSimplePages();
    }

    public function run()
    {
        $this->configure();
        if (isset($_SERVER['HTTP_HOST'])) {
            $this->config['host'] = $_SERVER['HTTP_HOST'];
        } else {
            $this->config['host'] = $_SERVER['SERVER_NAME'];
        }

        $request_uri = strtok($_SERVER['REQUEST_URI'], '?');
        $query_string = $_SERVER['QUERY_STRING'];

        $base = $this->config['base'];
        if (preg_match("#^/{$base}(catalog/)?(?<slug>[^/]+)/?#", $request_uri, $matches)) {
            $slug = $matches['slug'];
            foreach ($this->config['simple_pages'] as $page) {
                if ($page->slug === $slug) {
                    return $this->simplePage($page);
                }
            }
        }

        if (preg_match("#^/{$base}catalog/oai?#", $request_uri, $matches)) {
            $oai = new Oai($this->config);
            $oai->run();
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/find/?#", $request_uri, $matches)) {
            $this->find($matches['id']);
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/download/?#", $request_uri, $matches)) {
            $this->download($matches['id']);
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/paged/?#", $request_uri, $matches)) {
            $this->embedPagedViewer($matches['id']);
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/text/?#", $request_uri, $matches)) {
            $this->text($matches['id']);
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/zoom/?#", $request_uri, $matches)) {
            $this->pageViewer($matches['id'], 'zoom');
        } elseif (preg_match("#^/{$base}catalog/(?<id>[^/]+)/?#", $request_uri, $matches)) {
            $this->pageViewer($matches['id']);
        } elseif (preg_match("#^/{$base}catalog/?#", $request_uri, $matches)) {
            $this->index();
        } elseif (preg_match("#^/{$base}text/(?<id>[^/]+)/?#", $request_uri, $matches)) {
            $this->text($matches['id']);
        } else {
            $this->index();
        }
    }

    public function cleanupHost($value)
    {
        $value = preg_replace('#https://nyx#', 'https://exploreuk', $value);
        return $value;
    }

    public function cleanupDoc($doc)
    {
        if ($this->config['prod']) {
            $result = array();
            foreach ($doc as $key => $value) {
                if (is_string($value)) {
                    $value = $this->cleanupHost($value);
                    $result[$key] = $value;
                } elseif (is_array($value)) {
                    $result[$key] = array();
                    foreach ($value as $item) {
                        $item = $this->cleanupHost($item);
                        $result[$key][] = $item;
                    }
                }
            }
            return $result;
            #return $doc;
        }
        $result = array();
        foreach ($doc as $key => $value) {
            if (is_string($value)) {
                if (strpos($value, 'https://nyx.uky.edu/dips/') === 0) {
                    $value = preg_replace('/dips/', 'dipstest', $value);
                } elseif (strpos($value, 'http://nyx.uky.edu/dips/') === 0) {
                    $value = preg_replace('/dips/', 'dipstest', $value);
                }
                $value = preg_replace('#https://nyx#', 'https://exploreuk', $value);
                $result[$key] = $value;
            } elseif (is_array($value)) {
                $result[$key] = array();
                foreach ($value as $item) {
                    if (strpos($item, 'https://nyx.uky.edu/dips/') === 0) {
                        $item = preg_replace('/dips/', 'dipstest', $item);
                    } elseif (strpos($item, 'http://nyx.uky.edu/dips/') === 0) {
                        $item = preg_replace('/dips/', 'dipstest', $item);
                    }
                    $item = preg_replace('#https://nyx#', 'https://exploreuk', $item);
                    $result[$key][] = $item;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function cleanupDocs($docs)
    {
        #if ($this->config['prod']) {
        #    return $docs;
        #}
        $result = array();
        foreach ($docs as $doc) {
            $result[] = $this->cleanupDoc($doc);
        }
        return $result;
    }

    public function document($id)
    {
        $pieces = array();
        $pieces[] = 'fq=' . urlencode("id:$id");
        $pieces[] = 'fl=' . urlencode("*");
        $pieces[] = 'wt=json';
        $query = implode('&', $pieces);
        $url = $this->config['solr'] . '?' . $query;
        $result = json_decode(file_get_contents($url), true);
        if (isset($result['response']) and count($result['response']['docs']) > 0) {
            return $this->cleanupDoc($result['response']['docs'][0]);
        } else {
            return null;
        }
    }

    public function pages($id)
    {
        $doc = $this->document($id);
        if ($doc['object_type_s'][0] === 'section') {
            $parent = $id;
        } else {
            $parent = preg_replace('/_[^_]+$/', '', $id);
        }
        $pieces = array();
        $pieces[] = 'fq=' . urlencode("parent_id_s:$parent");
        $pieces[] = 'fq=' . urlencode("object_type_s:page");
        $pieces[] = 'wt=json';
        $pieces[] = 'fl=' . urlencode('id,reference_image_url_s,reference_image_width_s,reference_image_height_s');
        $pieces[] = 'rows=10000';
        $pieces[] = 'sort=browse_key_sort+asc';
        $query = implode('&', $pieces);
        $url = $this->config['solr'] . '?' . $query;
        $result = json_decode(file_get_contents($url), true);
        if (isset($result['response']) and count($result['response']['docs']) > 0) {
            return $this->cleanupDocs($result['response']['docs']);
        } else {
            return null;
        }
    }

    public function find($id)
    {
        $euk_solr = $this->config['solr'];

        parse_str($_SERVER['QUERY_STRING'], $params);
        $q = null;
        foreach ($params as $key => $value) {
            if ($key === 'q') {
                $q = $value;
                break;
            }
        }

        $pieces = array();
        $pieces[] = 'q=' . urlencode($q);
        $pieces[] = 'mm=1';
        $pieces[] = 'fq=' . urlencode("parent_id_s:$id");
        $pieces[] = 'fq=' . urlencode("object_type_s:page");
        $pieces[] = 'wt=json';
        $pieces[] = 'rows=10000';
        $pieces[] = 'fl=' . urlencode('id,reference_image_url_s,reference_image_width_s,reference_image_height_s,text_s,sequence_number_display');

        $url = "$euk_solr?" . implode('&', $pieces);
        $result = json_decode(file_get_contents($url), true);

        $response = array(
            'q' => $q,
            'matches' => array(),
        );

        if (isset($result['response']) && (count($result['response']['docs']) > 0)) {
            foreach ($result['response']['docs'] as $doc) {
                if (isset($doc['text_s'])) {
                    $snippet = highlight_snippet($doc['text_s'][0], $q, 5);
                    if (strlen($snippet) > 5) {
                        $response['matches'][] = array(
                            'text' => $snippet,
                            'par' => array(array(
                                'boxes' => array(),
                                'page' => intval($doc['sequence_number_display'][0]),
                                'page_width' => intval($doc['reference_image_width_s'][0]),
                                'page_height' => intval($doc['reference_image_height_s'][0]),
                                'page_image' => $this->cleanupHost($doc['reference_image_url_s'][0]),
                            )),
                        );
                    }
                }
            }
        }

        header("Content-Type: application/json\n");
        print json_encode($response);
    }

    public function download($id)
    {
        if (!isset($_GET['type'])) {
            return;
        }

        switch ($_GET['type']) {
            case 'jpeg':
                $field = 'reference_image_url_s';
                $mime = 'image/jpeg';
                break;
            case 'pdf':
                $field = 'pdf_url_display';
                $mime = 'application/pdf';
                break;
            default:
                return;
        }

        $doc = $this->document($id);
        # XXX handle null document

        $url = null;
        if (isset($doc[$field])) {
            $url = $doc[$field];
            if (is_array($url)) {
                $url = $url[0];
            }
        }
        $url = $this->cleanupHost($url);

        /* TODO: maybe have a metadata-determined filename? */
        $name = basename($url);

        header("Content-type: $mime");
        header("Content-Disposition: attachment; filename=\"$name\"");

        /* TODO: maybe stream this instead */
        readfile($url);
    }

    public function text($id)
    {
        $doc = $this->document($id);
        # XXX handle null document

        $text_field = 'text_s';

        if (array_key_exists($text_field, $doc)) {
            print '<pre>' . implode("\n", $doc[$text_field]) . "</pre>\n";
        }
    }

    public function getVisiblePages($pages)
    {
        $visiblePages = array();
        foreach ($pages as $page) {
            if ($page['visible']) {
                $newPage = array();
                $newPage['label'] = $page['label'];
                $newPage['uri'] = $page['uri'];
                if ($page['uri'] === '/') {
                    $newPage['suppress'] = true;
                } elseif (substr($page['uri'], 0, 1) !== '/') {
                    $newPage['external'] = true;
                }
                $subPages = $this->getVisiblePages($page['pages']);
                if (count($subPages) > 0) {
                    $newPage['pages'] = $subPages;
                    $newPage['active'] = true;
                }
                $visiblePages[] = $newPage;
                $subPages = array();
            }
        }
        return $visiblePages;
    }

    public function embedPagedViewer($id)
    {
        $metadata = array(
            'base' => $this->config['base'],
            'logo' => $this->config['logo'],
            'front_page' => false,
            'page_title' => 'ExploreUK - rare and unique research materials from UK Libraries.',
            'theme' => $this->config['theme'],
            'query' => $this->config['query'],
        );
        $metadata['page_description'] = $metadata['page_title'];

        $pages = $this->pages($id);
        if ($pages) {
            $parent = preg_replace('/_[^_]+$/', '', $id);
            $sequence = intval(preg_replace('/.*[^_]+_/', '', $id)) - 1;
            $search_host = 'https://' . $_SERVER['HTTP_HOST'] . '/catalog/' . $parent . '/find';
            $images_base_url = 'https://' . $_SERVER['HTTP_HOST'] . '/themes/' . $metadata['theme'] . '/BookReader/images/';

            $metadata['script'] = array(
                'json' => json_encode($pages),
                'search_host' => json_encode($search_host),
                'imagesBaseURL' => json_encode($images_base_url),
                'query' => json_encode($metadata['query']->q('q')),
            );
        }

        $view = new View($metadata, 'paged');
        $view->render();
    }

    public function pageViewer($id, $template = 'page')
    {
        $doc = $this->document($id);
        if (is_null($doc)) {
            header('Location: /');
            return;
        }

        $format = $doc['format'];
        $object_type = $doc['object_type_s'];
        if (is_array($object_type)) {
            $object_type = $object_type[0];
        }
        if ($format === 'collections' && $object_type === 'collection') {
            header('Location: ' . $this->config['fa_base'] . $id);
            return;
        }

        $metadata = array(
            'id' => $id,
            'logo' => $this->config['logo'],
            'action' => 'page',
            'base' => $this->config['base'],
            'front_page' => false,
            'theme' => $this->config['theme'],
            'query' => $this->config['query'],
        );

        $metadata['search_link'] = $this->config['solr'] . '?' . $metadata['query']->searchParams();
        $metadata['back_to_search'] = $this->path('/catalog/' . $metadata['query']->link());
        $metadata['back_to_search_text'] = EUK_BACK_TO_SEARCH_TEXT;

        $flat = array();
        foreach ($doc as $key => $value) {
            # XXX: Consider adding $euk_repeatable_fields to config.
            if ($key === 'subject_topic_facet') {
                $flat[$key] = $value;
            } elseif (is_array($value) and count($value) > 0) {
                if (preg_match('/_url/', $key)) {
                    $flat[$key] = $this->cleanupHost($value[0]);
                } else {
                    $flat[$key] = $value[0];
                }
            } elseif (isset($value)) {
                if (preg_match('/_url/', $key)) {
                    $flat[$key] = $this->cleanupHost($value);
                } else {
                    $flat[$key] = $value;
                }
            } else {
                $flat[$key] = '';
            }
        }
        $details = array();
        $pageMetadata = array();
        $desired = array(
            array('Title', 'title_display'),
            array('Creator', 'author_display'),
            array('Format', 'format'),
            array('Publication date', 'pub_date_display'),
            array('Date uploaded', 'date_digitized_display'),
            array('Language', 'language_display'),
            array('Publisher', 'publisher_display'),
            array('Type', 'type_display'),
            array('Accession number', 'accession_number_display'),
            array('Source', 'source_s'),
            array('Coverage', 'coverage_s'),
            array('Finding aid', 'finding_aid_url_s'),
            array('Metadata record', 'mets_url_display'),
            array('Rights', 'usage_display'),
        );
        foreach ($desired as $row) {
            $label = $row[0];
            $key = $row[1];
            $link = false;
            if ($key === 'type_display') {
                $value = type_for($doc['format'], $doc['type_display']);
            } else {
                if (isset($doc[$key])) {
                    $value = $doc[$key];
                } else {
                    $value = false;
                }
            }
            if ($key === 'finding_aid_url_s' or $key === 'mets_url_display') {
                $value = $this->cleanupHost($value);
                $link = true;
            }
            if ($value) {
                $details[$key] = array(
                    'label' => $label,
                    'key' => $key,
                    'value' => $value,
                    'link' => $link,
                );
            }
        }
        $details['id'] = array(
            'label' => 'Permalink',
            'key' => 'id',
            'value' => $id,
        );

        $metadata['page_title'] = brevity(htmlspecialchars($doc['title_display']));
        if (array_key_exists('finding_aid_url_s', $doc)) {
            $entry = array(
                'label' => 'Collection guide',
                'anchor' => true,
                'key' => 'collection_guide',
                'value' => $this->path('/catalog/' . preg_replace('/_.*/', '', $doc['object_id_s'][0]) . $metadata['query']->link()),
                'link' => true,
            );
            $details['collection_url'] = array(
                'label' => EUK_LOCALE['en']['collection_url'],
                'key' => 'collection_url',
                'value' => array(
                    'base_id' => preg_replace('/_.*/', '', $doc['object_id_s'][0]),
                    'source_s' => $doc['source_s'][0],
                ),
            );
            $details['collection_guide'] = $entry;
            $metadata['page_description'] = htmlspecialchars(
                $doc['title_display'] . ', ' .
                implode(', ', $doc['source_s']) . ', ' .
                'University of Kentucky Libraries - ExploreUK',
                ENT_QUOTES,
                'UTF-8'
            );
        } else {
            $metadata['page_description'] = htmlspecialchars(
                $doc['title_display'] . ', ' .
                'University of Kentucky Libraries - ExploreUK',
                ENT_QUOTES,
                'UTF-8'
            );
        }

        if ($object_type === 'section' && ($format !== 'audio' && $format !== 'audiovisual')) {
            $flat['embed_url'] = $this->path("/catalog/$id/paged" . $metadata['query']->link());
            $text_field = 'text_s';
            if (array_key_exists($text_field, $doc)) {
                $flat['text'] = array(
                    'href' => $this->path("/catalog/$id/text"),
                );
            }
            $metadata['item_book'] = $flat;
            $metadata['downloadable'] = true;
            $metadata['downloadable_extra'] = '<br>of this page';

            # Do we have a multipage item here?
            $pdf_url = 'pdf_url_display';
            if (array_key_exists($pdf_url, $doc)) {
                $metadata['downloadable_single'] = false;
                $metadata['downloadable_single_extra'] = '<br>of entire item';
            } else {
                $metadata['downloadable_single'] = true;
                $metadata['downloadable_single_extra'] = $metadata['downloadable_extra'];
            }
        } else {
            switch ($format) {
                case 'audio':
                    $metadata['item_audio'] = array(
                        'audio' => array(
                            'href_id' => "audio_$id",
                            'href' => $this->cleanupHost($flat['reference_audio_url_s']),
                        ),
                    );
                    $metadata['script_media'] = true;
                    break;
                case 'audiovisual':
                    $metadata['item_audio'] = array(
                        'video' => array(
                            'href_id' => "video_$id",
                            'href' => $this->cleanupHost($flat['reference_video_url_s']),
                        ),
                    );
                    $metadata['script_media'] = true;
                    break;
                case 'drawings (visual works)':
                    /* fall through */
                case 'images':
                    $metadata['item_image'] = $flat;
                    $metadata['script_image'] = array(
                        'osd_id' => 'viewer',
                        'prefix_url' => $this->themePath('openseadragon/images/'),
                        'ref_id' => 'reference_image',
                    );
                    $metadata['downloadable'] = true;
                    break;
                case 'annual reports':
                    /* fall through */
                case 'architectural drawings (visual works)':
                    /* fall through */
                case 'archival material':
                    /* fall through */
                case 'athletic publications':
                    /* fall through */
                case 'booklets':
                    /* fall through */
                case 'books':
                    /* fall through */
                case 'course catalogs':
                    /* fall through */
                case 'directories':
                    /* fall through */
                case 'handscrolls':
                    /* fall through */
                case 'indexes (reference sources)':
                    /* fall through */
                case 'journals':
                    /* fall through */
                case 'ledgers':
                    /* fall through */
                case 'maps':
                    /* fall through */
                case 'minutes':
                    /* fall through */
                case 'newsletters':
                    /* fall through */
                case 'newspapers':
                    /* fall through */
                case 'yearbooks':
                    $flat['embed_url'] = $this->path("/catalog/$id/paged" . $metadata['query']->link());
                    $text_field = 'text_s';
                    if (array_key_exists($text_field, $doc)) {
                        $flat['text'] = array(
                            'href' => $this->path("/catalog/$id/text"),
                        );
                    }
                    $metadata['item_book'] = $flat;
                    $metadata['downloadable'] = true;
                    $metadata['downloadable_extra'] = '<br>of this page';
                    break;
                default:
                    $pieces = array();
                    foreach ($doc as $field => $value) {
                        if (is_array($value)) {
                            $value = implode('; ', $value);
                        }
                        $pieces[] = "<b>$field</b>: $value";
                    }
                    $metadata['item'] = '<ul><li>' . implode('</li><li>', $pieces) . "</li></ul>\n";
                    break;
            }
        }

        $metadata['flat'] = $flat;
        $metadata['details'] = $details;

        $raw_pages = json_decode($this->omeka->getOption('public_navigation_main'), true);
        $metadata['nav'] = $this->getVisiblePages($raw_pages);
        $metadata['z_simple_pages'] = $this->config['simple_pages'];
        $view = new View($metadata, $template);
        $view->render();
    }

    public function index()
    {
        $raw_pages = json_decode($this->omeka->getOption('public_navigation_main'), true);
        $metadata = array(
            'base' => $this->config['base'],
            'logo' => $this->config['logo'],
            'theme' => $this->config['theme'],
            'query' => $this->config['query'],
            'nav' => $this->getVisiblePages($raw_pages),
        );

        $metadata['q'] = $metadata['query']->q('q');
        $metadata['search_link'] = $this->config['solr'] . '?' . $metadata['query']->searchParams();
        $metadata['back_to_search'] = $this->path('/catalog/' . $metadata['query']->link());
        if ($this->config['query']->nontrivial()) {
            $result = $this->config['query']->search();
            $metadata['page_title'] = htmlspecialchars($metadata['q'], ENT_QUOTES, 'UTF-8') . ' - ExploreUK';

            $euk_requires_capitalization = EUK_REQUIRES_CAPITALIZATION;

            # Facets
            $metadata['active_facets'] = array();
            foreach ($metadata['query']->q('f') as $f_term => $ary) {
                foreach ($ary as $value => $truth) {
                    $remove_link = $metadata['query']->removeFilterLink($f_term, $value);
                    $field_label = facet_displayname($f_term);
                    if (isset($result['facet_counts']['facet_fields'][$f_term])) {
                        $facet_counts = $result['facet_counts']['facet_fields'][$f_term];
                        $count = 0;
                        if (count($facet_counts) > 0) {
                            $navs = navsHashFromFlatList($facet_counts);
                            $count = $navs[$value];
                        }
                        $value_label = $value;
                        if (in_array($f_term, $euk_requires_capitalization)) {
                            $value_label = ucfirst($value_label);
                        }
                        $value_label = value_label_cleanup($value_label);
                        $hidden_value_label = $value_label;
                        if ($hidden_value_label === 'collection guides') {
                            $hidden_value_label = 'collections';
                        }
                        $metadata['active_facets'][] = array(
                            'field_label' => $field_label,
                            'remove_link' => $this->path('/catalog/' . $remove_link),
                            'field_raw' => $f_term,
                            'value_label' => $value_label,
                            'hidden_value_label' => $hidden_value_label,
                            'count' => $count,
                        );
                    }
                }
            }

            $metadata['facets'] = array();
            $facets = EUK_FACETS;
            foreach ($facets as $facet) {
                $facet_counts = $result['facet_counts']['facet_fields'][$facet];
                if (count($facet_counts) > 2) {
                    $navs = navsHashFromFlatList($facet_counts);
                    $values = array();
                    $qf = $metadata['query']->q('f');
                    $nontrivial = false;
                    foreach ($navs as $label => $count) {
                        if (!isset($qf[$facet][$label])) {
                            $nontrivial = true;
                            $add_link = $metadata['query']->addFilterLink($facet, $label);
                            $value_label = $label;
                            if (in_array($facet, $euk_requires_capitalization)) {
                                $value_label = ucfirst($value_label);
                            }
                            $value_label = value_label_cleanup($value_label);
                            $values[] = array(
                                'add_link' => $this->path('/catalog/' . $add_link),
                                'value_label' => $value_label,
                                'count' => $count,
                            );
                        }
                    }
                    if ($nontrivial) {
                        $metadata['facets'][] = array(
                            'field_label' => facet_displayname($facet),
                            'values' => $values,
                            'field_raw' => $facet,
                        );
                    }
                }
            }

            $metadata['facet_full_lists'] = array();
            $facets_by_count = $metadata['query']->getFacetsByCount();
            $facets_by_index = $metadata['query']->getFacetsByIndex();
            foreach ($facets as $facet) {
                $metadata['facet_full_lists'][$facet] = array(
                    'field_label' => facet_displayname($facet),
                    'field_raw' => $facet,
                    'by-count' => array(),
                    'by-index' => array(),
                );

                $facet_counts = $facets_by_count['facet_counts']['facet_fields'][$facet];
                if (count($facet_counts) > 2) {
                    $navs = navsHashFromFlatList($facet_counts);
                    foreach ($navs as $label => $count) {
                        $add_link = $metadata['query']->addFilterLink($facet, $label);
                        $value_label = $label;
                        if (in_array($facet, $euk_requires_capitalization)) {
                            $value_label = ucfirst($value_label);
                        }
                        $value_label = value_label_cleanup($value_label);
                        $metadata['facet_full_lists'][$facet]['by-count'][] = array(
                            'add_link' => $this->path('/catalog/' . $add_link),
                            'value_label' => $value_label,
                            'count' => $count,
                        );
                    }
                }

                $facet_counts = $facets_by_index['facet_counts']['facet_fields'][$facet];
                if (count($facet_counts) > 2) {
                    $navs = navsHashFromFlatList($facet_counts);
                    foreach ($navs as $label => $count) {
                        $add_link = $metadata['query']->addFilterLink($facet, $label);
                        $value_label = $label;
                        if (in_array($facet, $euk_requires_capitalization)) {
                            $value_label = ucfirst($value_label);
                        }
                        $value_label = value_label_cleanup($value_label);
                        $metadata['facet_full_lists'][$facet]['by-index'][] = array(
                            'add_link' => $this->path('/catalog/' . $add_link),
                            'value_label' => $value_label,
                            'count' => $count,
                        );
                    }
                }
            }
        } else {
            $metadata['page_title'] = 'ExploreUK - rare and unique research materials from UK Libraries.';
        }
        $metadata['page_description'] = $metadata['page_title'];

        if (!$this->config['query']->nontrivial()) {
            $metadata['front_page'] = true;
            $metadata['search_items_count_text'] = $this->omeka->getThemeOption('search_items_count_text');

            $colln = $this->omeka->getCollectionByTitle('Background image rotation');
            $metadata['colln'] = $colln;
            $items = $this->omeka->getItems($colln->id, array('featured' => true));
            if (count($items) > 0) {
                $index = array_rand($items);
                $item = $items[$index];
                $metadata['featured_image'] = $this->omeka->getItemMetadata($item->id);
            } else {
                $metadata['featured_image'] = array(
                    'image' => '',
                    'label' => '',
                    'url' => '',
                );
            }

            $metadata['popular_resources'] = array();
            $popular_resources = array();
            $colln = $this->omeka->getCollectionByTitle('Popular Resources');
            $items = $this->omeka->getItems($colln->id);
            foreach ($items as $item) {
                $im = $this->omeka->getItemMetadata($item->id);
                $popular_resources[$im['position']] = $im;
            }
            ksort($popular_resources);
            foreach ($popular_resources as $key => $im) {
                $metadata['popular_resources'][] = $im;
            }

            $metadata['additional_resources'] = array();
            $additional_resources = array();
            $colln = $this->omeka->getCollectionByTitle('Additional Resources');
            $items = $this->omeka->getItems($colln->id);
            foreach ($items as $item) {
                $im = $this->omeka->getItemMetadata($item->id);
                $additional_resources[$im['position']] = $im;
            }
            ksort($additional_resources);
            foreach ($additional_resources as $key => $im) {
                $metadata['additional_resources'][] = $im;
            }
            $view = new View($metadata, 'front-page');
        } else {
            # Pagination and results
            $metadata['facet_menu_title'] = EUK_LOCALE['en']['facet_menu_title'];
            $metadata['front_page'] = false;
            $metadata['pagination'] = array();
            $metadata['results'] = array();
            if (intval($result['response']['numFound']) > 0) {
                #pagination
                $pagination_data = array(
                    'first' => $metadata['query']->q('offset') + 1,
                    'last' => $metadata['query']->q('offset') + $metadata['query']->q('rows'),
                    'count' => $result['response']['numFound'],
                );
                if ($metadata['query']->q('offset') > 0) {
                    $pagination_data['previous'] = $this->path('/catalog/' . $metadata['query']->previousLink());
                }
                if ($pagination_data['last'] <= $pagination_data['count']) {
                    $pagination_data['next'] = $this->path('/catalog/' . $metadata['query']->nextLink());
                } else {
                    $pagination_data['last'] = $pagination_data['count'];
                }
                $metadata['pagination'] = $pagination_data;

                # results
                $docs = $this->cleanupDocs($result['response']['docs']);
                $results = array();
                for ($i = 0; $i < count($docs); $i++) {
                    $results_data = array();
                    # raw to begin
                    foreach (EUK_HIT_FIELDS as $field => $solr_field) {
                        $raw_field = null;
                        if (isset($docs[$i][$solr_field])) {
                            $raw_field = $docs[$i][$solr_field];
                            if (is_array($raw_field)) {
                                $results_data[$field] = htmlspecialchars($raw_field[0], ENT_QUOTES, 'UTF-8');
                            } else {
                                $results_data[$field] = htmlspecialchars($raw_field, ENT_QUOTES, 'UTF-8');
                            }
                            if ($results_data[$field] === 'collections') {
                                $results_data[$field] = 'collection guides';
                            }
                        }
                    }
                    # cleanup
                    if (isset($results_data['thumb'])) {
                        $results_data['thumb'] = str_replace('http:', 'https:', $results_data['thumb']);
                        $results_data['thumb'] = str_replace('_tb.jpg', '_ftb.jpg', $results_data['thumb']);
                        $results_data['thumb'] = $this->cleanupHost($results_data['thumb']);
                    }
                    $results_data['link'] = $this->path('/catalog/' . $docs[$i]['id'] . $metadata['query']->link());
                    $results_data['number'] = $metadata['query']->q('offset') + $i + 1;
                    $results_data['target'] = '';
                    if ($results_data['format'] === 'collections') {
                        $results_data['target'] = ' target="_blank" rel="noopener"';
                    }
                    $results[] = $results_data;
                }
                $metadata['results'] = $results;
                $view = new View($metadata, 'search-results');
            } else {
                $metadata['suggestions'] = array();
                if (isset($result['spellcheck'])) {
                    foreach ($result['spellcheck']['suggestions'] as $word) {
                        if (isset($word['suggestion'])) {
                            foreach ($word['suggestion'] as $suggestion) {
                                $metadata['suggestions'][] = $suggestion['word'];
                            }
                        }
                    }
                }
                $view = new View($metadata, 'no-results');
            }
        }

        $view->render();
    }

    public function path($path)
    {
        $base = $this->config['base'];
        $url = str_replace('//', '/', "$base$path");
        $url = preg_replace('/\?$/', '', $url);
        if (strpos($url, '/') !== 0) {
            $url = "/$url";
        }
        return $url;
    }

    public function themePath($path)
    {
        return $this->path('/themes/' . $this->config['theme'] . "/$path");
    }

    public function simplePage($page)
    {
        $raw_pages = json_decode($this->omeka->getOption('public_navigation_main'), true);
        $metadata = array(
            'action' => 'simple-page',
            'logo' => $this->config['logo'],
            'front_page' => false,
            'base' => $this->config['base'],
            'theme' => $this->config['theme'],
            'query' => $this->config['query'],
            'nav' => $this->getVisiblePages($raw_pages),
        );
        $metadata['page'] = $this->omeka->getSimplePage($page->id);
        $metadata['page_title'] = $metadata['page']->title;
        $metadata['page_description'] = $metadata['page']->title;

        $view = new View($metadata, 'simple-page');
        $view->render();
    }
}
