<?php
//set_time_limit(12000);
ini_set('memory_limit', '2500M');
//ini_set('display_errors', false);
if(get_extension_funcs("gd") === false){
    echo 'PHP-GD is required to run MazeGen';
    exit();
}
error_reporting(-1);
$im = imagecreatetruecolor(450, 50);

if(!isset($_GET['x'])){
    $_GET['x'] = $_GET['y'] = 80;
}
if(isset($_GET['xy']) and $_GET['xy'] !== ''){
    $_GET['x'] = $_GET['y'] = $_GET['xy'];
}

if($_GET['x'] == '' or $_GET['y'] == '' or !is_numeric($_GET['x']) or !is_numeric($_GET['y']) or $_GET['x'] < 3 or $_GET['y'] < 3 ){
    imagettftext($im, 35, 0, 10, 38, imagecolorallocate($im, 255, 0, 0), 'gulim.ttc', 'Invalid argument');
    send($im);
}

$im = imagecreatetruecolor(($x = $_GET['x']*2 + 1), ($y = $_GET['y']*2 + 1));
$im = imagefilledrectangle($im, 0, 0, $x, $y, imagecolorallocate($im, 245, 245, 245));
render($im, createMazeArray($_GET['x'], $_GET['y']));
//waterMark($im);
send($im);
function render(&$res, $arr){
    for($i = 0; $i <= $_GET['x']*2; $i++){
        for($ii = 0; $ii <= $_GET['y']*2; $ii++) imagefilledrectangle($res, $i, $ii, $i + 1 - 1, $ii + 1 - 1, $arr[$i][$ii] === true ? imagecolorallocate($res, 10, 10, 10) : imagecolorallocate($res, 245, 245, 245));
    }
    imagefilledrectangle($res, 1, 1, 2 - 1, 2 - 1, imagecolorallocate($res, 0, 245, 0));
    imagefilledrectangle($res, $_GET['x'] * 2 - 1, $_GET['y'] * 2 - 1, $_GET['x'] * 2 - 1, $_GET['y'] * 2 - 1, imagecolorallocate($res, 245, 0, 0));
    if(mt_rand(0, 99)%2 === 1){
        imagefilledrectangle($res, 2, 1, 2, 1, imagecolorallocate($res, 245, 245, 245));
    } else {
        imagefilledrectangle($res, 1, 2, 1, 2, imagecolorallocate($res, 245, 245, 245));
    }
}

function createMazeArray($x, $y){
    $a1 = array();
    $a2 = array();
    //$head = array(array(98, 98));
    $head = array(array(($x - $x%2)/2, ($y - $y%2)/2));
    for($i = 0; $i <= $x*2; $i++){
        for($ii = 0; $ii <= $y*2; $ii++){
            if($i % 2 === 0 or $ii % 2 === 0){
                $a1[$i][$ii] = true;
            } else {
                $a1[$i][$ii] = false;
            }
        }
    }
    for($i = 0; $i < $x; $i++){
        for($ii = 0; $ii < $y; $ii++){
            $a2[$i][$ii] = false;
        }
    }
    $blocks = $x*$y-1;
    $a1[1][1] = false;
    $a2[0][0] = true; //Preset
    while($blocks > 0){
        $h = $head[mt_rand(0, count($head)-1)];
            $val = mt_rand(1, 15);
            if($val & 1 and $h[1] !== 0 and $a2[$h[0]][$h[1]-1] === false){
                $a1[$h[0]*2+1][$h[1]*2] = false;
                $a2[$h[0]][$h[1]-1] = true;
                $head[] = array($h[0], $h[1]-1);
                $blocks--;
            }
            if($val >> 1 & 1 and $h[0] !== 0 and $a2[$h[0]-1][$h[1]] === false){
                $a1[$h[0]*2][$h[1]*2+1] = false;
                $a2[$h[0]-1][$h[1]] = true;
                $head[] = array($h[0]-1, $h[1]);
                $blocks--;
            }
            if($val >> 2 & 1 and $h[1] !== $_GET['y'] - 1 and $a2[$h[0]][$h[1]+1] === false){
                $a1[$h[0]*2+1][$h[1]*2+2] = false;
                $a2[$h[0]][$h[1]+1] = true;
                $head[] = array($h[0], $h[1]+1);
                $blocks--;
            }
            if($val >> 3 & 1 and $h[0] !== $_GET['x'] - 1 and $a2[$h[0]+1][$h[1]] === false){
                $a1[$h[0]*2+2][$h[1]*2+1] = false;
                $a2[$h[0]+1][$h[1]] = true;
                $head[] = array($h[0]+1, $h[1]);
                $blocks--;
            }
            unset($h);
        
    }
    return $a1;
}

function waterMark(&$res){
    
}

function send($img){
    header("Content-type: image/png");
    imagepng($img);
    imagedestroy($img);
    exit();
}

?>
