<?php
$p = m('pagination');
?>
<div id="facet_group_mobile">
    <div id="facet_group_mobile_top">
        <details id="facet_group_mobile_container" class="facet-menu-mobile bg-uklwhite row">
            <summary id="facet_group_mobile_head"><?php echo $euk_locale['en']['facet_menu_title']; ?></summary>
            <?php require("facets.php"); ?>
        </details>
    </div>
</div>
<div class="results">
    <div id="resultsfacets">
<div id="facet_group">
<div id="facet_group_left">
    <div class="bg-uklblue" id="facet_group_left_container">
        <span id="facet_group_left_head"><?php echo $euk_locale['en']['facet_menu_title']; ?></span>
    </div>
    <?php require("facets.php"); ?>
</div>
</div>
    </div>
<div id="resultsmain">
<?php require("pagination.php"); ?>

<div class="row">
<ul class="result-list">
<?php foreach (m('results') as $r): ?>
<li class="result-item">
    <p class="result-number"><?php echo $r['number']; ?>.</p>
    <div class="result-summary">
        <h3><a href="<?php echo $r['link']; ?>"><?php echo euk_brevity($r['title']); ?></a></h3>
        <?php if (isset($r['thumb'])): ?>
        <a class="image-placeholder" href="<?php echo $r['link']; ?>">
            <img class="lazy" src="<?php echo $theme_path; ?>/images/middlegray.png" data-src="<?php echo $r['thumb']; ?>" alt="<?php echo $r['title']; ?>" title="<?php echo $r['title']; ?>">
        </a>
        <?php else: ?>
        <div class="image-placeholder"></div>
        <?php endif; ?>
        <ul class="result-metadata">
            <?php foreach ($euk_result_facet_order as $field): ?>
                <?php if (isset($r[$field])): ?>
                    <?php $label = $euk_locale['en'][$hit_fields[$field]]; ?>
                    <?php if (in_array($field, $euk_result_drop_fields)): ?>
                        <?php $lclass = ' class="result-metadata-drop"'; ?>
                    <?php else: ?>
                        <?php $lclass = ''; ?>
                    <?php endif; ?>
                    <li<?php echo $lclass; ?>><span class="result-metadata-label"><?php echo $label; ?>:</span>
                    <?php echo $r[$field]; ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</li>
<?php endforeach; ?>
</ul>
</div>

<?php require("pagination.php"); ?>
        </div>
<?php require("more-facets.php"); ?>
