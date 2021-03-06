<?php
ini_set("max_execution_time", 0);
if(ROLE != 'admin') die('{"type":"error","emsg":"权限不足！"}');
global $m;
//参数替换
function getgs($gs,$type=false){
	$data = str_ireplace('{百度ID}','(.*)',$gs);
	$data = str_ireplace('{百度BDUSS}','([0-9a-zA-Z\-\~]+)',$data);
	if($type) {
		global $re_a,$re_b;
		$re_a = stripos($gs,'{百度ID}');
		$re_b = stripos($gs,'{百度BDUSS}');
		if ($re_a > $re_b) {
			$re_a = 2;$re_b = 1;
		} else {
			$re_a = 1;$re_b = 2;
		}
	}
	return $data;
}
//导入BDUSS
if (isset($_GET['new'])) {
  $import_str = !empty($_POST['import_str']) ? $_POST['import_str'] : '';
  if(empty($import_str)) die('{"type":"error","emsg":"导入文本不能为空！"}');
  $gs = option::get('xy_import_gs');
  if(stripos($gs,'{百度ID}')===FALSE) die('{"type":"error","emsg":"导入格式缺少参数 <strong>{百度ID}</strong> ！"}');
  elseif(stripos($gs,'{百度BDUSS}')===FALSE) die('{"type":"error","emsg":"导入格式缺少参数 <strong>{百度BDUSS}</strong> ！"}');
  $check = option::get('xy_import_check') == 1 ? true : false;
  $import_str = preg_replace('/[\r\n]+/', PHP_EOL, $import_str);
  $arr = explode(PHP_EOL,$import_str);
  $total = count($arr);
  $refresh = option::get('xy_import_refresh');
  $hs=$cf=$ok=$err=$up=$sx=$re_a=$re_b=0;
  for($i=0;$i<$total;$i++){
	preg_match('/'.getgs($gs,true).'/',$arr[$i], $re);
	if (!empty($re[$re_b])) {
	  $hs++;
	  $x = $m->once_fetch_array("SELECT COUNT(*) AS bduss FROM `".DB_NAME."`.`".DB_PREFIX."baiduid` where `bduss` = '".$re[$re_b]."';");
	  if ($x['bduss'] > 0) {
		$cf++;
	  } else {
		$baidu_name = $check ? sqladds(getBaiduId($re[$re_b])) : sqladds($re[$re_a]);
		if(empty($baidu_name)){
		  $sx++;
		} else {
		  $z = $m->once_fetch_array("SELECT COUNT(*) AS bdname FROM `".DB_NAME."`.`".DB_PREFIX."baiduid` where `name` = '".$baidu_name."';");
		  if ($z['bdname'] > 0) {
			$sql = "UPDATE `".DB_NAME."`.`".DB_PREFIX."baiduid` SET `uid`='".UID."', `bduss`='".$re[$re_b]."' where `name`='".$baidu_name."';";
			$m->query($sql) ? $up++ : $err++;
		  } else {
			$sql = "INSERT INTO `".DB_NAME."`.`".DB_PREFIX."baiduid` (`uid`, `bduss`, `name`) VALUES ('".UID."', '".$re[$re_b]."', '".$baidu_name."');";
			$m->query($sql) ? $ok++ : $err++;
		  }
		}
	  }
	}
  }
  if($refresh == 1 && $ok > 0) {
	  $info = ',"status":"refresh"';
  }elseif($ok > 0) {
	  $info = ',"status":"success"';
  } else {
	  $info='';
  }
  die('{"info":"批量导入完成。<br/><br/>匹配行数：['.$hs.']<br/>导入成功：['.$ok.']<br/>导入失败：['.$err.']<br/>更新记录：['.$up.']<br/>失效数量：['.$sx.']<br/>已存在数：['.$cf.']<br/>"'.$info.'}');
}
//基本设置
if (isset($_GET['set'])) {
	if(!empty($_POST['gs'])) {
		$gs = ' '.$_POST['gs'];
		if(!stristr($gs,'{百度ID}')) {
			die('{"type":"error","emsg":"缺少参数 <strong>{百度ID}</strong> ！"}');
		} elseif (!stristr($gs,'{百度BDUSS}')) {
			die('{"type":"error","emsg":"缺少参数 <strong>{百度BDUSS}</strong> ！"}');
		}
		$check = !empty($_POST['check']) ? 1 : 0;
		$refresh = !empty($_POST['refresh']) ? 1 : 0;
		option::set('xy_import_gs',$_POST['gs']);
		option::set('xy_import_check',$_POST['check']);
		option::set('xy_import_refresh',$_POST['refresh']);
		die('{"type":"success","regular":"'.urlencode(getgs($_POST['gs'])).'"}');
	} else {
		die('{"type":"error","emsg":"导入格式不能为空！"}');
	}
}
//删除所有绑定
if (isset($_GET['delete'])) {
  $s = $m->query("SELECT * FROM `".DB_NAME."`.`".DB_PREFIX."baiduid` where `uid` = '".UID."';");
  while ($x = $m->fetch_array($s)) {
	$t = $m->once_fetch_array("SELECT `t` FROM `".DB_NAME."`.`".DB_PREFIX."users` where `id` = '".UID."';");
	$m->query("DELETE FROM `".DB_NAME."`.`".DB_PREFIX.$t['t']."` WHERE `uid` = '".UID."' and `pid` = '".$x['id']."';");
	$m->query("DELETE FROM `".DB_NAME."`.`".DB_PREFIX."baiduid` WHERE `uid` = '".UID."' and `id` = '".$x['id']."';");
  }
  echo '<script language="JavaScript">alert("删除所有绑定完成。");location.href="index.php?mod=baiduid";</script>';
}