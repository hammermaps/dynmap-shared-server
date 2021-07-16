<?php
require_once('vendor/autoload.php');
require_once('standalone/MySQL_Config.php');
require_once('standalone/MySQL_Common.php');

if(isset($_GET['call'])) {
    $common = new MySQL_Common();
    switch ($_GET['call']) {
        case 'configuration':
            $common->loadConfig();
            break;
        case 'markers':
            $common->getMarker();
            break;
        case 'tiles':
            $common->getTiles();
            break;
        case 'update':
            $common->getUpdate();
            break;
    }

    $common->__destruct();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Minecraft Dynamic Map</title>
	<meta charset="utf-8" />
	<meta name="keywords" content="minecraft, map, dynamic" />
	<meta name="description" content="Minecraft Dynamic Map" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />	

	<link rel="icon" href="images/dynmap.ico" type="image/ico" />

	<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
	<script type="text/javascript" src="js/leaflet.min.js"></script>

	<script type="text/javascript" src="js/custommarker.js"></script>

	<script type="text/javascript" src="js/dynmaputils.js"></script>
	<script type="text/javascript" src="js/sidebarutils.js"></script>

	<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
	<link rel="stylesheet" type="text/css" href="css/standalone.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="css/dynmap_style.css" media="screen" />

	<script type="text/javascript" src="js/jquery.json.js"></script>
	<script type="text/javascript" src="js/jquery.mousewheel.js"></script>
	<script type="text/javascript" src="js/minecraft.js"></script>
	<script type="text/javascript" src="js/map.js"></script>
	<script type="text/javascript" src="js/hdmap.js"></script>

	<script type="text/javascript">
		let config = {
			url: {
				configuration: '?call=configuration',
				update: '?call=update&world={world}',
				tiles: '?call=tiles&tile=',
				markers: '?call=markers&marker='
			}
		};

		$(document).ready(function() {
			window.dynmap = new DynMap($.extend({
				container: $('#mcmap')
			}, config));
		});
	</script>
</head>
<body>
<noscript>
 For full functionality of this site it is necessary to enable JavaScript.
 Here are the <a href="http://www.enable-javascript.com/" target="_blank">
 instructions how to enable JavaScript in your web browser</a>.
</noscript>
	<div id="mcmap"></div>
</body>
</html>