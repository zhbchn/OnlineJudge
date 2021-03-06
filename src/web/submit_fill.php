<?php 
	session_start();
	require_once("include/db_info.inc.php");
	if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
		require_once("oj-header.php");
		echo "<a href='loginpage.php'>$MSG_Login</a>";
		require_once("oj-footer.php");
		exit(0);
	}
	require_once("include/memcache.php");
	require_once("include/const.inc.php");
	$now=strftime("%Y-%m-%d %H:%M",time());
	$user_id=$_SESSION[$OJ_NAME.'_'.'user_id'];
	
	//验证码
	$err_str="";
	$err_cnt=0;
	if($OJ_VCODE) $vcode=$_POST["vcode"];
	if($OJ_VCODE && ($_SESSION[$OJ_NAME.'_'."vcode"]==null || $vcode!= $_SESSION[$OJ_NAME.'_'."vcode"] || $vcode=="" || $vcode==null) ){
        $_SESSION[$OJ_NAME.'_'."vcode"]=null;
        $err_str=$err_str."Verification Code Wrong!\\n";
        $err_cnt++;
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	$sql="SELECT count(1) FROM `solution` WHERE result<4";
    $result=mysql_query_cache($sql);
    $row=$result[0];
    if($row[0]>50) $OJ_VCODE=true;

    //问题权限
	if (isset($_POST['cid'])){
		$pid=intval($_POST['pid']);
		$cid=intval($_POST['cid']);
		$sql="SELECT `problem_id`,'N' from `contest_problem` 
					where `num`='$pid' and contest_id=$cid";
	}else{
		$id=intval($_POST['id']);
		$sql="SELECT `problem_id`,defunct from `problem` where `problem_id`='$id' ";
		if(!isset($_SESSION[$OJ_NAME.'_'.'administrator']))
			$sql.=" and defunct='N' ";
		$sql.=" and problem_id not in (select distinct problem_id from contest_problem where `contest_id` IN (
				SELECT `contest_id` FROM `contest` WHERE 
				(`end_time`>'$now' or private=1) and `defunct`='N') )";
	}
	//echo $sql;	
	$res=mysql_query_cache($sql);
	if (isset($res) && count($res)<1 && !isset($_SESSION[$OJ_NAME.'_'.'administrator']) && !((isset($cid) && $cid<=0) || (isset($id) && $id<=0))){
		$view_errors=  "Where do find this link? No such problem.<br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	if($res[0][1]!='N' && !isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
		echo "res:$res,count:".count($res);
		$view_errors=  "Problem disabled.<br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

	//提交页面是否开启了测试功能，找问题id，假如在测试模式则id是负值，并判断是否启用test_run
	$test_run=false;
	if (isset($_POST['id'])) {
		$id=intval($_POST['id']);
	    $test_run=($id<=0);
	}else if (isset($_POST['pid']) && isset($_POST['cid']) && $_POST['cid']!=0){
		$pid=intval($_POST['pid']);
		$cid=intval($_POST['cid']);
	    $test_run=($cid<0);
		if($test_run) $cid=-$cid;
		// check user if private
		$sql="SELECT `private` FROM `contest` WHERE `contest_id`=? AND `start_time`<=? AND `end_time`>?";
		$result=pdo_query($sql,$cid,$now,$now);
		$rows_cnt=count($result);
		if ($rows_cnt!=1){
			echo "You Can't Submit Now Because Your are not invited by the contest or the contest is not running!!";
			require_once("oj-footer.php");
			exit(0);
		}else{
			$row=$result[0];
			$isprivate=intval($row[0]);
			if ($isprivate==1 && !isset($_SESSION[$OJ_NAME.'_'.'c'.$cid])){
				$sql="SELECT count(*) FROM `privilege` WHERE `user_id`=? AND `rightstr`=?";
				$result=pdo_query($sql,$user_id,"c$cid") ; 
				$row=$result[0];
				$ccnt=intval($row[0]);
				if ($ccnt==0 && !isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
					$view_errors= "You are not invited!\n";
					require("template/".$OJ_TEMPLATE."/error.php");
					exit(0);
				}
			}
		}
		$sql="SELECT `problem_id` FROM `contest_problem` WHERE `contest_id`=? AND `num`=?";
		$result=pdo_query($sql,$cid,$pid);
		$rows_cnt=count($result);
		if ($rows_cnt!=1){
			$view_errors= "No Such Problem!\n";
			require("template/".$OJ_TEMPLATE."/error.php");
			exit(0);
		}else{
			$row=$result[0];
			$id=intval($row['problem_id']);
			if($test_run) $id=-$id;
		}
	}else{
		$id=0;
		/*
			$view_errors= "No Such Problem!\n";
			require("template/".$OJ_TEMPLATE."/error.php");
			exit(0);
		*/
		$test_run=true;
	}
	$problem_flag=intval($_POST['problem_flag']);
	if($test_run) $id=-$id;
	$sql="SELECT * FROM `problem_fill` WHERE `problem_id`=?";
	$result=pdo_query($sql,$id);
	$row_fill=$result[0];
	if(isset($problem_flag) && $problem_flag == "0"){
		//$language=intval($_POST['language']);
		$language = $row_fill['language'];
		if ($language>count($language_name) || $language<0) $language=0;
		$language=strval($language); //strval — 获取变量的字符串值
		setcookie('lastlang',$language,time()+360000); //设置cookie
	}
	if($test_run) $id=-$id;
	$source=$_POST['source'];
	//$input_text="";
	//if(isset($_POST['input_text'])) $input_text=$_POST['input_text']; //案例测试用的
	if(get_magic_quotes_gpc()){
		$source=stripslashes($source);
		//$input_text=stripslashes($input_text);
	}
	if(isset($_POST['encoded_submit'])){
	   $source=base64_decode($source);
	}
	//$input_text=preg_replace ( "(\r\n)", "\n", $input_text );
	$source=($source);
	//$input_text=($input_text);
	//$source_user=$source;
	if($test_run) $id=-$id;
	
	/*//use append Main code
	$prepend_file="$OJ_DATA/$id/prepend.".$language_ext[$language];
	if(isset($OJ_APPENDCODE)&&$OJ_APPENDCODE&&file_exists($prepend_file)){
	     $source=file_get_contents($prepend_file)."\n".$source;
	}
	$append_file="$OJ_DATA/$id/append.".$language_ext[$language];
	//echo $append_file;
	if(isset($OJ_APPENDCODE)&&$OJ_APPENDCODE&&file_exists($append_file)){
	     $source.=("\n".file_get_contents($append_file));
	     //echo "$source";
	}
	//end of append */

	//if($language==6)
	   //$source="# coding=utf-8\n".$source;
	if($test_run) $id=0;
	$len=strlen($source);
	//echo $source;

	$ip = ($_SERVER['REMOTE_ADDR']);
	if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
	    $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    $tmp_ip=explode(',',$REMOTE_ADDR);
	    $ip =(htmlentities($tmp_ip[0],ENT_QUOTES,"UTF-8"));
	}
	if ($len==0){
		$view_errors="Code too short!<br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	if ($len>256){
		$view_errors="Code too long!<br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	// last submit
	$now=strftime("%Y-%m-%d %X",time()-10);
	$sql="SELECT `in_date` from `solution` where `user_id`=? and in_date>? order by `in_date` desc limit 1";
	$res=pdo_query($sql,$user_id,$now);
	if (count($res)==1){
		$view_errors="You should not submit more than twice in 10 seconds.....<br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	$solution_answer=$source;
	$source=preg_replace ( "(\r\n)", "\n", $source );
	$source = explode("\n", $source);
	if((~$OJ_LANGMASK)&(1<<$language)){
		//将问题解决添加入数据库
		if(isset($problem_flag) && $problem_flag == "1"){
			$problem_answer=$row_fill["problem_answer"];
			$problem_answer=preg_replace ( "(\r\n)", "\n", $problem_answer );
			$problem_answer = explode("\n", $problem_answer);
			$is_right = true;
			$err_answer="";
			if(count($source) == count($problem_answer)){
				for($i=0;$i<count($source);$i++){
					if(strcmp($source[$i], $problem_answer[$i])){
						$err_answer=$err_answer."第".($i+1)."行答案错误，检查答案并确定是否有其他多余字符。\\n";
						$is_right = false;
					}
				}
			}else{
				$err_answer="答案错误！";
				$is_right = false;
			}
			if($is_right){
				if (!isset($pid)){
					$sql="INSERT INTO solution(problem_id,user_id,in_date,ip,code_length,result) VALUES(?,?,NOW(),?,?,4)";
					$insert_id= pdo_query($sql,$id,$user_id,$ip,$len);
				}else{
					$sql="INSERT INTO solution(problem_id,user_id,in_date,ip,code_length,contest_id,num,result) VALUES(?,?,NOW(),?,?,?,?,4)";
					if(isset($OJ_OI_1_SOLUTION_ONLY)&&$OJ_OI_1_SOLUTION_ONLY){
						pdo_query("UPDATE solution set contest_id =0 where contest_id=? and user_id=? and num=?",$cid,$user_id,$pid);
					}
					$insert_id= pdo_query($sql,$id,$user_id,$ip,$len,$cid,$pid);
				}
				$sql="INSERT INTO `solution_fill`(`solution_id`,`problem_flag`,`solution_answer`) VALUES(?,?,?)";
				pdo_query($sql,$insert_id,$problem_flag,$solution_answer);
                $is_ok=true;
			}else{
                if (!isset($pid)){
                    $sql="INSERT INTO solution(problem_id,user_id,in_date,ip,code_length,result) VALUES(?,?,NOW(),?,?,6)";
                    $insert_id= pdo_query($sql,$id,$user_id,$ip,$len);
                }else{
                    $sql="INSERT INTO solution(problem_id,user_id,in_date,ip,code_length,contest_id,num,result) VALUES(?,?,NOW(),?,?,?,?,6)";
                    if(isset($OJ_OI_1_SOLUTION_ONLY)&&$OJ_OI_1_SOLUTION_ONLY){
                        pdo_query("UPDATE solution set contest_id =0 where contest_id=? and user_id=? and num=?",$cid,$user_id,$pid);
                    }
                    $insert_id= pdo_query($sql,$id,$user_id,$ip,$len,$cid,$pid);
                }
                $sql="INSERT INTO `solution_fill`(`solution_id`,`problem_flag`,`solution_answer`) VALUES(?,?,?)";
                pdo_query($sql,$insert_id,$problem_flag,$solution_answer);
                $sql="INSERT INTO `runtimeinfo`(`solution_id`,`error`) VALUES(?,?)";
                pdo_query($sql,$insert_id,$err_answer);
/*				echo "<script language='javascript'>\n";
				echo "alert('$err_answer');\n";
				echo "history.go(-1);\n";
				echo "</script>";
*/
                $is_ok=true;
			}
		}
		if(isset($problem_flag) && $problem_flag == "0"){
			if (!isset($pid)){
				$sql="INSERT INTO solution(problem_id,user_id,in_date,language,ip,code_length,result) VALUES(?,?,NOW(),?,?,?,14)";
				$insert_id= pdo_query($sql,$id,$user_id,$language,$ip,$len);
			}else{
				$sql="INSERT INTO solution(problem_id,user_id,in_date,language,ip,code_length,contest_id,num,result) VALUES(?,?,NOW(),?,?,?,?,?,14)";
				if(isset($OJ_OI_1_SOLUTION_ONLY)&&$OJ_OI_1_SOLUTION_ONLY){
					pdo_query("UPDATE solution set contest_id =0 where contest_id=? and user_id=? and num=?",$cid,$user_id,$pid);
				}
				$insert_id= pdo_query($sql,$id,$user_id,$language,$ip,$len,$cid,$pid);
			}
			$sql="INSERT INTO `solution_fill`(`solution_id`,`problem_flag`,`solution_answer`) VALUES(?,?,?)";
			pdo_query($sql,$insert_id,$problem_flag,$solution_answer);
			$problem_tempsource = $row_fill['problem_tempsource'];
			for($i=0;$i<substr_count($row_fill['problem_tempsource'], $row_fill['fillmd5']);$i++){
				$pos=strpos($problem_tempsource, $row_fill['fillmd5']);
				$problem_tempsource = substr_replace($problem_tempsource, $source[$i], $pos, 32);
			}
			$source=$problem_tempsource;
			$source_user=$source;
			//将代码插入数据库
			$sql="INSERT INTO `source_code_user`(`solution_id`,`source`) VALUES(?,?)";
			pdo_query($sql,$insert_id,$source_user);
			$sql="INSERT INTO `source_code`(`solution_id`,`source`) VALUES(?,?)";
			pdo_query($sql,$insert_id,$source);
			$sql="UPDATE solution set result=0 where solution_id=?";//将解决结果置为等待
			pdo_query($sql,$insert_id);
			$is_ok = true;
		}
		/*if($test_run){
			$sql="INSERT INTO `custominput`(`solution_id`,`input_text`) VALUES(?,?)";
			pdo_query($sql,$insert_id,$input_text);
		}*/
		//using redis task queue
        if($OJ_REDIS && $is_ok){
        	$redis = new Redis();
        	$redis->connect($OJ_REDISSERVER, $OJ_REDISPORT);
	   		if(isset($OJ_REDISAUTH)) $redis->auth($OJ_REDISAUTH);
       		$redis->lpush($OJ_REDISQNAME,$insert_id);
        	$redis->close();     
        }
	}
	if($is_ok){
		//重定向到状态页面
		$statusURI=strstr($_SERVER['REQUEST_URI'],"submit",true)."status.php";
		if (isset($cid)) $statusURI.="?cid=$cid";
	    $sid="";
	    if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	        $sid.=session_id().$_SERVER['REMOTE_ADDR']; //session_id — Get and/or set the current session id
	    }
	    if (isset($_SERVER["REQUEST_URI"])){
	        $sid.=$statusURI;
	    }
	    //echo $statusURI."<br>";
	  
	    $sid=md5($sid);
	    $file = "cache/cache_$sid.html";
	    //echo $file; 
	    if($OJ_MEMCACHE){
			$mem = new Memcache;
	        if($OJ_SAE)
	                $mem=memcache_init();
	        else{
	                $mem->connect($OJ_MEMSERVER,  $OJ_MEMPORT);
	        }
	        $mem->delete($file,0);
	    }
		else if(file_exists($file)) unlink($file);
	    //echo $file;
	    
		$statusURI="status.php?user_id=".$_SESSION[$OJ_NAME.'_'.'user_id'];
		if (isset($cid)) $statusURI.="&cid=$cid";
		if(!$test_run){	
			header ("Location: $statusURI");
		}else{
			if(isset($_GET['ajax'])){
		        echo $insert_id;
		    }else{ ?>
				<script>window.parent.setTimeout("fresh_result('<?php echo $insert_id;?>')",1000);</script>
			<?php }
		}
	}
?>
