<?php
    if (!isset($bodyOnly)) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {font-family: Arial;}

        /* Style the tab */
        .costOfPriceInfoTab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
        }

        /* Style the buttons inside the tab */
        .costOfPriceInfoTab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            font-size: 17px;
        }

        /* Change background color of buttons on hover */
        .costOfPriceInfoTab button:hover {
            background-color: #ddd;
        }

        /* Create an active/current tablink class */
        .costOfPriceInfoTab button.active {
            background-color: #ccc;
        }

        /* Style the tab content */
        .priceinfotab {
            display: none;
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-top: none;
        }
    </style>
</head>
<body>
<?php } ?>

<!--<h2>Tabs</h2>
<p>Click on the buttons inside the tabbed menu:</p>
-->
<div class="costOfPriceInfoTab">
    <?php echo $priceInfoTabs ?>
</div>

<?php echo $priceInfoBlocks ?>

<script>
    /**
     * openPriceInfo (global).
     *
     * With Magento2 adaptions.
     *
     * @param evt
     * @param methodName
     */
    function openPriceInfo(evt, methodName) {
        var i, tabcontent, priceinfotablink;
        tabcontent = document.getElementsByClassName("priceinfotab");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        priceinfotablink = document.getElementsByClassName("priceinfotablink");
        for (i = 0; i < priceinfotablink.length; i++) {
            priceinfotablink[i].className = priceinfotablink[i].className.replace(" active", "");
        }
        // This part may receive an object instead of an element id, so if we have an element
        // ready on the function call, we should use that one instead of looking for it.
        if (typeof methodName === 'string') {
            document.getElementById(methodName).style.display = "block";
            if (null !== evt) {
                evt.currentTarget.className += " active";
            }
        } else {
            methodName.style.display = "block";
            if (null !== evt) {
                evt.currentTarget.className += " active";
            }
        }
    }

    window.onload = function() {
        var tabcontent = document.getElementsByClassName("priceinfotab");
        var currentID;
        for (var i = 0; i < tabcontent.length; i++) {
            currentID = tabcontent[i].id;
            break;
        }
        if (currentID !== '') {
            openPriceInfo(null, currentID);
        }
    }
</script>

<?php
if (!isset($bodyOnly)) {
?>

</body>
</html>
<?php } ?>
