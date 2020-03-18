<div id="<?php echo $methodHash ?>" class="priceinfotab">
    <?php if (empty($methodHtml)) { ?><iframe src="<?php echo $priceInfoUrl; ?>" class="priceinfoframe" style="width: 100%; height: 435px; border: none;"></iframe><?php } else { echo $methodHtml; } ?>
</div>
