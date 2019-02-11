<?php
function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
			if(function_exists("openssl_random_pseudo_bytes")){
				$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
			}else{
				$rnd = hexdec(bin2hex(rand()."_".rand()));
			}
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
}

function getToken($length=32){
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    for($i=0;$i<$length;$i++){
        $token .= $codeAlphabet[crypto_rand_secure(0,strlen($codeAlphabet))];
    }
    return $token;
}

function pwGen($password,$md5ed=False) 
{
	if (!$md5ed) $password=md5($password);
	$salt = sha1(rand());
	$salt = substr($salt, 0, 4);
	$hash = base64_encode( sha1($password . $salt, true) . $salt ); //base64_encode — 使用 MIME base64 对数据进行编码
	return $hash; 
}

function pwCheck($password,$saved)
{
	if (isOldPW($saved)){
		if(!isOldPW($password)) $mpw = md5($password);
		else $mpw=$password;
		if ($mpw==$saved) return True;
		else return False;
	}
  //这一步牛逼学着点
	$svd=base64_decode($saved); // base64_decode — 对使用 MIME base64 编码的数据进行解码
	$salt=substr($svd,20); //substr — 返回字符串的子串 
	if(!isOldPW($password)) $password=md5($password);
	$hash = base64_encode( sha1(($password) . $salt, true) . $salt ); //sha1 — 计算字符串的 sha1 散列值
	if (strcmp($hash,$saved)==0) return True;
	else return False;
}

function isOldPW($password)
{
	if(strlen($password)!=32) return false; //已经变成了md5码，一共32位
	for ($i=strlen($password)-1;$i>=0;$i--)
	{
		$c = $password[$i];
		if ('0'<=$c && $c<='9') continue;
		if ('a'<=$c && $c<='f') continue;
		if ('A'<=$c && $c<='F') continue;
		return False;
	}
	return True;
}

function is_valid_user_name($user_name){
	$len=strlen($user_name);
	for ($i=0;$i<$len;$i++){
		if (
			($user_name[$i]>='a' && $user_name[$i]<='z') ||
			($user_name[$i]>='A' && $user_name[$i]<='Z') ||
			($user_name[$i]>='0' && $user_name[$i]<='9') ||
			$user_name[$i]=='_'||
			($i==0 && $user_name[$i]=='*') 
		);
		else return false;
	}
	return true;
}

function sec2str($sec){
	return sprintf("%02d:%02d:%02d",$sec/3600,$sec%3600/60,$sec%60);
}
function is_running($cid){
  $now=strftime("%Y-%m-%d %H:%M",time());
  $sql="SELECT count(*) FROM `contest` WHERE `contest_id`=? AND `end_time`>?";
  $result=pdo_query($sql,$cid,$now);
  $row=$result[0];
  $cnt=intval($row[0]);
  return $cnt>0;
}
function check_ac($cid,$pid){
	//require_once("./include/db_info.inc.php");
	global $OJ_NAME;
	
	$sql="SELECT count(*) FROM `solution` WHERE `contest_id`=? AND `num`=? AND `result`='4' AND `user_id`=?";
	$result=pdo_query($sql,$cid,$pid,$_SESSION[$OJ_NAME.'_'.'user_id']);
	 $row=$result[0];
	$ac=intval($row[0]);
	if ($ac>0) return "<font color=green>Y</font>";
	$sql="SELECT count(*) FROM `solution` WHERE `contest_id`=? AND `num`=? AND `result`!=4 and `problem_id`!=0  AND `user_id`=?";
	$result=pdo_query($sql,$cid,$pid,$_SESSION[$OJ_NAME.'_'.'user_id']);
	$row=$result[0];
	$sub=intval($row[0]);
	
	if ($sub>0) return "<font color=red>N</font>";
	else return "";
}



function RemoveXSS($val) {
   /* remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
   // this prevents some character re-spacing such as <java\0script>
   // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
  //删除所有不可打印的字符。 允许CR（0a）和LF（0b）和TAB（9）
    //这可以防止某些字符重新间隔，例如<java \ 0script>
    //请注意，您必须稍后使用\ n，\ n和\ t来处理拆分，因为它们*在某些输入中被允许*/
   $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val); //preg_replace — 执行一个正则表达式的搜索和替换

   // straight replacements, the user should never need these since they're normal characters
   // this prevents like <IMG SRC=@avascript:alert('XSS')>
   //直接替换，用户永远不需要这些，因为它们是普通字符
    //这可以防止像<IMG SRC = @ avascript：alert（'XSS'）>
   $search = 'abcdefghijklmnopqrstuvwxyz';
   $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
   $search .= '1234567890!@#$%^&*()';
   $search .= '~`";:?+/={}[]-_|\'\\';
   for ($i = 0; $i < strlen($search); $i++) {
      // ;? matches the ;, which is optional
      // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

      // @ @ search for the hex values
      $val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;
      // @ @ 0{0,7} matches '0' zero to seven times
      $val = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;
   }

   // now the only remaining whitespace attacks are \t, \n, and \r   //, 'style'
   $ra1 = Array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
   $ra2 = Array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
   $ra = array_merge($ra1, $ra2);

   $found = true; // keep replacing as long as the previous round replaced something
   while ($found == true) {
      $val_before = $val;
      for ($i = 0; $i < sizeof($ra); $i++) {
         $pattern = '/';
         for ($j = 0; $j < strlen($ra[$i]); $j++) {
            if ($j > 0) {
               $pattern .= '(';
               $pattern .= '(&#[xX]0{0,8}([9ab]);)';
               $pattern .= '|';
               $pattern .= '|(&#0{0,8}([9|10|13]);)';
               $pattern .= ')*';
            }
            $pattern .= $ra[$i][$j];
         }
         $pattern .= '/i';
         $replacement = substr($ra[$i], 0, 2).'<x>'.substr($ra[$i], 2); // add in <> to nerf the tag
         $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
         if ($val_before == $val) {
            // no replacements were made, so exit the loop
            $found = false;
         }
      }
   }
   return $val;
}
?>
