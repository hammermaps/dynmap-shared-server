<?php

use Phpfastcache\CacheManager;
use Phpfastcache\Drivers\Couchbase\Config;

//Names
$world_names = [
    'lobby' => 'I.M.Y.P. - Lobby'
];

/**
 * Class MySQL_Common
 */
class MySQL_Common
{
    private $database;
    private $cache;
    private $blank;
	
    public function __construct()
    {
        global $dbhost,$dbname,$dbuserid,$dbpassword;
        global $cachehost,$cacheuserid,$cachepassword,$cacheport,$cachebucket;
        $this->database = new Nette\Database\Connection('mysql:host='.$dbhost.';dbname='.$dbname,
            $dbuserid, $dbpassword);
        $this->database->connect();

        $this->cache = CacheManager::getInstance('couchbase', new Config([
            'host' => $cachehost,
            'port' => $cacheport,
            'username' => $cacheuserid,
            'password' => $cachepassword,
            'bucketName' => $cachebucket
        ]));

        //Cache blank.png
        $CachedString = $this->cache->getItem('blank');
        if (!is_null($CachedString->get())) {
            $this->blank = hex2bin($CachedString->get());
        } else {
            $this->blank = file_get_contents('../images/blank.png');
            $CachedString->set($this->blank)->expiresAfter(1200);
            $this->cache->save($CachedString);
        }
    }

    public function cacheGet($key="") {
        return null;
        $CachedString = $this->cache->getItem($key);
        if (is_null($CachedString->get())) {
            return null;
        }

        return $CachedString->get();
    }

    public function cacheSet($key="",$data="",$ttl=0) {
        $CachedString = $this->cache->getItem($key);
        $CachedString->set($data)->expiresAfter($ttl);
        $this->cache->save($CachedString);
    }

    public function __destruct()
    {
       // if(isset($this->database)) {
       //     $this->database->disconnect();
       // }
    }

    public function loadConfig(): void {
        global $dbprefix,$world_names;
        $cache = $this->cacheGet('worlds');
        if(!is_null($cache)) {
            $config = json_decode($cache,true);
        } else {
            if (!$this->database)
                $this->database->reconnect();

            $get = $this->database->fetch("SELECT `Content` FROM `".$dbprefix."StandaloneFiles` WHERE `FileName` = 'dynmap_config.json' LIMIT 1;");
            $get = json_decode($get['Content'], true);

            $config = $get;
            $config['worlds'] = [];
            $config['title'] = "Dynmap Cluster";
            $config['allowwebchat'] = false;
            $config['allowchat'] = false;
            $config['showlayercontrol'] = false;
            $config['maxcount'] = 100;
            $config['defaultworld'] = 'lobby';
            $config['showplayerfacesinmenu'] = false;
            $config['updaterate'] = 6000;
            $config['defaultworld'] = 'lobby';

            foreach ($get['components'] as $key => $component) {
                if (in_array($component['type'], ['chat', 'chatballoon', 'chatbox', 'timeofdayclock', 'link', 'coord', 'playermarkers'])) {
                    unset($config['components'][$key]);
                }
            }
            sort($config['components']);

            $result = $this->database->query("SELECT `Content` FROM `".$dbprefix."StandaloneFiles` WHERE `FileName` = 'dynmap_config.json'");
            foreach ($result as $row) {
                $get = json_decode($row->Content, true);
                foreach ($get['worlds'] as $world) {
                    $map = 'dynmap_' . $world['name'] . '.json';
                    $config_w = $this->database->fetch("SELECT `show` FROM `".$dbprefix."StandaloneFiles` WHERE `FileName` = ? LIMIT 1;",$map);
                    if($config_w->offsetGet('show')) {
                       if(array_key_exists($world['title'],$world_names)) {
                           $world['title'] = $world_names[$world['title']];
                       }

                        $config['worlds'][] = $world;
                    }
                }
            }
            unset($result);

            if(is_array($config)) {
                $this->cacheSet('worlds', json_encode($config), 600);
            }
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($config);
    }

    public function getUpdate(): void {
        global $dbprefix;
        $world = '';
        if(isset($_GET['world'])) {
            $world = strtolower($_GET['world']);
        }

        header('Content-type: application/json; charset=utf-8');
        if(strpos($world, '/') || strpos($world, '\\') || empty($world)) {
            echo json_encode(['error'=>'invalid-world']);
            return;
        }

        $cache = $this->cacheGet(sha1($world));
        if(!is_null($cache)) {
            $get = json_decode($cache,true);
        } else {
            if (!$this->database)
                $this->database->reconnect();

            $map = 'dynmap_' . $world . '.json';
            $get = $this->database->fetch("SELECT `Content` FROM `".$dbprefix."StandaloneFiles` WHERE `FileName` = ? LIMIT 1;",$map);
            $get = json_decode($get['Content'],true);
            unset($get['players']); //NULL
            unset($get['currentcount']); //NULL
            unset($get['confighash']); //NULL

            if(is_array($get)) {
                $this->cacheSet(sha1($world), json_encode($get), 5);
            }
        }

        echo json_encode($get);
    }

    public function getTiles(): void {
        global $dbprefix;
        $path = $_REQUEST['tile'];
        if ((!isset($path)) || strstr($path, "..")) {
            header('HTTP/1.0 500 Error');
            echo "<h1>500 Error</h1>";
            echo "Bad marker: " . $path;
            return;
        }

        $parts = explode("/", $path);
        if (count($parts) != 4) {
            echo $this->blank;
            return;
        }

        $cache = $this->cacheGet(sha1($path));
        if(!is_null($cache)) {
            $get = json_decode($cache,true);
            $get['Image'] = hex2bin($get['Image']);
        } else {
            if (!$this->database)
                $this->database->reconnect();

            $variant='STANDARD';
            $prefix = $parts[1];
            $plen = strlen($prefix);
            if(($plen > 4) && (substr($prefix, $plen - 4) === "_day")) {
                $prefix = substr($prefix, 0, $plen - 4);
                $variant = 'DAY';
            }

            $fparts = explode("_", $parts[3]);
            if (count($fparts) == 3) { // zoom_x_y
                $zoom = strlen($fparts[0]);
                $x = intval($fparts[1]);
                $y = intval($fparts[2]);
            }
            else if (count($fparts) == 2) { // x_y
                $zoom = 0;
                $x = intval($fparts[0]);
                $y = intval($fparts[1]);
            }
            else {
                echo $this->blank;
                return;
            }

            $get = $this->database->fetch('SELECT t.Image,t.Format,t.HashCode,t.LastUpdate FROM `'.$dbprefix.'Maps` m JOIN `'.$dbprefix.'Tiles` t '.
                'WHERE m.WorldID=? AND m.MapID=? AND m.Variant=? AND m.ID=t.MapID AND t.x=? AND t.y=? and t.zoom=?;'
                ,strtolower($parts[0]), $prefix, $variant, $x, $y, $zoom);

            if(is_object($get)) {
                $cache = (array)$get;
                $cache['Image'] = bin2hex($cache['Image']);
                $this->cacheSet(sha1($path), json_encode($cache), 108000); //30Min
                unset($cache);
            }
        }

        if (!empty($get)) {
            if (!$get['Format'])
                header('Content-Type: image/png');
            else
                header('Content-Type: image/jpeg');

            header('ETag: \''.$get['HashCode'] .'\'');
            header('Last-Modified: '.gmdate('D, d M Y H:i:s',$get['LastUpdate']/1000) .' GMT');
            echo $get['Image'];
            return;
        }

        echo $this->blank;
    }

    /**
     * Get world Marker
     */
    public function getMarker(): void {
        global $dbprefix;
        $path = strtolower($_REQUEST['marker']);
        if ((!isset($path)) || strstr($path, "..")) {
            header('HTTP/1.0 500 Error');
            echo "<h1>500 Error</h1>";
            echo "Bad marker: " . $path;
            exit();
        }

        $parts = explode("/", $path);
        if (($parts[0] != "faces") && ($parts[0] != "_markers_")) {
            header('HTTP/1.0 500 Error');
            echo "<h1>500 Error</h1>";
            echo "Bad marker: " . $path;
            exit();
        }

        $in = explode(".", $parts[1]);
        $name = implode(".", array_slice($in, 0, count($in) - 1));
        $ext = $in[count($in) - 1];
        if (($ext == "json") && (strpos($name, "marker_") == 0)) {
            $world = substr($name, 7);
            $cache = $this->cacheGet('markers_' . $world);
            if(!is_null($cache)) {
                $get = json_decode($cache,true);
                header('Content-Type: application/json');
                echo $get['Content'];
            } else {
                if (!$this->database)
                    $this->database->reconnect();

                $get = $this->database->fetch('SELECT `Content` FROM `'.$dbprefix.'MarkerFiles` WHERE `FileName` = ?;', $world);
                if (empty($get) || !count($get)) {
                    header('Content-Type: application/json');
                    echo json_encode([]);
                    return;
                }

                if (is_object($get)) {
                    $cache = (array)$get;
                    $this->cacheSet('markers_' . $world, json_encode($cache), 800);
                    unset($cache);
                }

                header('Content-Type: application/json');
                echo $get['Content'];
            }
        } else {
            $cache = $this->cacheGet('markers_icons_' . $name);
            if(!is_null($cache) && $cache != false) {
                $get = json_decode($cache,true);
                $get['Image'] = hex2bin($get['Image']);
            } else {
                if (!$this->database)
                    $this->database->reconnect();

                $get = $this->database->fetch('SELECT `Image` FROM `'.$dbprefix.'MarkerIcons` WHERE `IconName` = ?;', $name);
                if (empty($get) || !count($get)) {
                    echo $this->blank;
                    return;
                }

                if (is_object($get)) {
                    $cache = (array)$get;
                    $cache['Image'] = bin2hex($cache['Image']);
                    $this->cacheSet('markers_icons_' . $name, json_encode($cache), 108000); //30Min
                    unset($cache);
                }
            }

            header('Content-Type: image/png');
            echo $get['Image'];
        }
    }
}