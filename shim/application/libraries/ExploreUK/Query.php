<?php
namespace ExploreUK;

class Query
{
    private $query;
    private $solr;

    public function __construct($query = array(), $solr = false)
    {
        $this->query = array(
            'q' => null,
            'fq' => array(),
            'f' => array(),
            'offset' => 0,
            'rows' => 20,
            'ui' => null,
        );
        $this->solr = $solr;
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
                    $this->query['q'] = $value;
                } elseif ($key == 'fq[]') {
                    $this->query['fq'][] = $value;
                } elseif (substr($key, 0, 2) == 'f[') {
                    $subkey = substr($key, 2, -3);
                    if (!isset($this->query['f'][$subkey])) {
                        $this->query['f'][$subkey] = array();
                    }
                    $this->query['f'][$subkey][$value] = true;
                } elseif ($key == 'offset') {
                    $this->query['offset'] = intval($value);
                } elseif ($key == 'per_page') {
                    $this->query['rows'] = intval($value);
                } elseif ($key == 'ui') {
                    switch ($value) {
                        case '1':
                            /* fall through */
                        case '2':
                            $this->query['ui'] = $value;
                            break;

                        default:
                            /* do nothing */
                            break;
                    }
                }
            }
        }
        $this->query = array_merge(
            $this->query,
            $query
        );
    }

    public function nontrivial()
    {
        return ((strlen($this->query['q']) > 0) ||
                (count($this->query['fq']) > 0) ||
                (count($this->query['f']) > 0));
    }

    public function q($key)
    {
        if (isset($this->query[$key])) {
            return $this->query[$key];
        } else {
            return false;
        }
    }

    public function link()
    {
        $query = $this->query;
        $pieces = array();
        if (strlen($query['q']) > 0) {
            $pieces[] = 'q=' . urlencode($query['q']);
        }
        foreach ($query['fq'] as $fq_term) {
            $pieces[] = 'fq[]=' . urlencode($fq_term);
        }
        foreach ($query['f'] as $f_term => $ary) {#$value) {
            foreach ($ary as $key => $truth) {
                $pieces[] = urlencode("f[$f_term][]") . '=' . urlencode($key);
            }
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

    public function suggestedLink($term)
    {
        $query = $this->query;
        $query['q'] = $term;
        $suggested = new Query($query, $this->solr);
        return $suggested->link();
    }

    public function previousLink()
    {
        $query = $this->query;
        $offset = $this->query['offset'] - $this->query['rows'];
        if ($offset > 0) {
            $query['offset'] = $offset;
        } else {
            $query['offset'] = 0;
        }
        $previous = new Query($query, $this->solr);
        return $previous->link();
    }

    public function nextLink()
    {
        $query = $this->query;
        $offset = $this->query['offset'] + $this->query['rows'];
        if ($offset > 0) {
            $query['offset'] = $offset;
        } else {
            $query['offset'] = 0;
        }
        $next = new Query($query, $this->solr);
        return $next->link();
    }

    public function search()
    {
        return $this->searchByParams($this->searchParams());
    }

    public function getFacetsByCount()
    {
        return $this->searchByParams($this->facetsByCountParams());
    }

    public function getFacetsByIndex()
    {
        return $this->searchByParams($this->facetsByIndexParams());
    }

    public function searchByParams($params)
    {
        $url = $this->solr . '?' . $params;
        return json_decode(file_get_contents($url), true);
    }

    public function searchParams()
    {
        $facets = EUK_FACETS;
        $q = $this->query['q'];
        $fq = $this->query['fq'];
        $f = $this->query['f'];
        $offset = $this->query['offset'];
        $pieces = array();
        $pieces[] = 'rows=' . $this->query['rows'];
        $pieces[] = 'wt=json';
        $pieces[] = 'q=' . urlencode($q);
        $pieces[] = 'mm=1';
        if ($offset > 0) {
            $pieces[] = "start=$offset";
        }
        if (count($facets) > 0) {
            $pieces[] = 'facet=true';
            $pieces[] = 'facet.mincount=1';
            $pieces[] = 'facet.limit=10';
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
            foreach ($f as $label => $ary) {
                foreach ($ary as $key => $truth) {
                    if ($label === 'pub_date_sort') {
                        $pieces[] = 'fq=' . urlencode("$label:$key");
                    } else {
                        $pieces[] = 'fq=' . urlencode("{!raw f=$label}$key");
                    }
                }
            }
        }
        # compound object
        $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
        return implode('&', $pieces);
    }

    public function facetsByCountParams()
    {
        $facets = EUK_FACETS;
        $q = $this->query['q'];
        $fq = $this->query['fq'];
        $f = $this->query['f'];
        $offset = $this->query['offset'];
        $pieces = array();
        $pieces[] = 'rows=' . $this->query['rows'];
        $pieces[] = 'wt=json';
        $pieces[] = 'q=' . urlencode($q);
        $pieces[] = 'mm=1';
        if ($offset > 0) {
            $pieces[] = "start=$offset";
        }
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
            foreach ($f as $label => $ary) {
                foreach ($ary as $key => $truth) {
                    if ($label === 'pub_date_sort') {
                        $pieces[] = 'fq=' . urlencode("$label:$key");
                    } else {
                        $pieces[] = 'fq=' . urlencode("{!raw f=$label}$key");
                    }
                }
            }
        }
        # compound object
        $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
        return implode('&', $pieces);
    }

    public function facetsByIndexParams()
    {
        $facets = EUK_FACETS;
        $q = $this->query['q'];
        $fq = $this->query['fq'];
        $f = $this->query['f'];
        $offset = $this->query['offset'];
        $pieces = array();
        $pieces[] = 'rows=' . $this->query['rows'];
        $pieces[] = 'wt=json';
        $pieces[] = 'q=' . urlencode($q);
        $pieces[] = 'mm=1';
        if ($offset > 0) {
            $pieces[] = "start=$offset";
        }
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
            foreach ($f as $label => $ary) {
                foreach ($ary as $key => $truth) {
                    if ($label === 'pub_date_sort') {
                        $pieces[] = 'fq=' . urlencode("$label:$key");
                    } else {
                        $pieces[] = 'fq=' . urlencode("{!raw f=$label}$key");
                    }
                }
            }
        }
        # compound object
        $pieces[] = 'fq=' . urlencode("compound_object_split_b:true");
        return implode('&', $pieces);
    }

    public function removeFilterLink($facet, $label)
    {
        $query = $this->query;
        $query['f'] = array();
        $query['offset'] = 0;
        foreach ($this->query['f'] as $potential_term => $ary) {
            if ($potential_term != $facet) {
                $query['f'][$potential_term] = $ary;
            } else {
                if (!isset($query['f'][$potential_term])) {
                    $query['f'][$potential_term] = array();
                }
                foreach ($ary as $key => $truth) {
                    if ($key != $label) {
                        $query['f'][$potential_term][$key] = true;
                    }
                }
                if (count($query['f'][$potential_term]) == 0) {
                    unset($query['f'][$potential_term]);
                }
            }
        }
        $removeFilter = new Query($query, $this->solr);
        return $removeFilter->link();
    }

    public function addFilterLink($facet, $label)
    {
        $query = $this->query;
        if (!isset($query['f'][$facet])) {
            $query['f'][$facet] = array();
        }
        $query['f'][$facet][$label] = true;
        $query['offset'] = 0;
        $addFilter = new Query($query, $this->solr);
        return $addFilter->link();
    }
}
