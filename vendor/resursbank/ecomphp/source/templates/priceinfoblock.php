<div id="<?php echo $methodHash ?>" class="tabcontent">
    <?php if (empty($methodHtml)) { ?>
        <iframe src="<?php echo $priceInfoUrl; ?>" style="width: 100%; height: 435px; border: none;"></iframe>
    <?php } else {
        echo $methodHtml;
    } ?>
</div>
