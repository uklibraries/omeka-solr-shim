<?php
$p = m('pagination');
?>
<div class="row pagination">

<div class="rows-select">
<form action="/catalog" class="per_page" method="get">
<label for="per_page">Show <select id="per_page" name="per_page" onchange="this.form.submit()" title="Number of results to display per page">
<?php
$opts = $euk_per_page_opts;
foreach ($opts as $opt) {
    if (q('rows') == $opt) {
        print "<option value=\"$opt\" selected=\"selected\">$opt</option>\n";
    }
    else {
        print "<option value=\"$opt\">$opt</option>\n";
    }
}
?>
</select> per page</label>
<input name="commit" type="hidden" value="search" />
<input name="search_field" type="hidden" value="all_fields" />
<input name="q" type="hidden" value="<?php echo q('q'); ?>" />
    <noscript><input name="commit" type="submit" value="update" /></noscript>
</form>
</div>

<div class="page-navigation">
<p>
<?php if (isset($p['previous'])): ?>
    <a href="<?php echo $p['previous']; ?>" class="prev_page">&laquo; Previous</a>
<?php else: ?>
    &laquo; Previous
<?php endif; ?>
<?php echo $p['first'] . ' - ' . $p['last'] . ' of ' . $p['count']; ?>
<?php if (isset($p['next'])): ?>
    <a href="<?php echo $p['next']; ?>" class="next_page">Next &raquo;</a>
<?php else: ?>
    <span class="disabled next_page">Next &raquo;</span>
<?php endif; ?>
</p>
</div>

</div>