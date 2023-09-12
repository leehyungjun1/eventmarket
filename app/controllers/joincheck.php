<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class joincheck extends front_base {
	function __construct() {
		parent::__construct();
	}

	public function main_index()
	{
		redirect("/joincheck/index");
	}

	public function index()
	{
		redirect("/joincheck/joincheck_view");
	}	
	
	
	public function joincheck_view(){
	//$_GET['popup'] = true;
	$joincheck_seq = (int) $_GET['seq'];
	$mode = $_GET['mode'];
	if(empty( $_GET['page'])){$sc['page'] = 1;}	else{$sc['page'] = $_GET['page'];}
	if(!empty( $_GET['tdate'])){		
	$tdate = date('Y-m-d',strtotime($_GET['tdate']));	
	}else{
	$tdate = date('Y-m-d');
	}
	//이벤트 정보 가져오기	
	$query = $this->db->query("select * from fm_joincheck where joincheck_seq=?",$joincheck_seq);
	$data = $query->row_array();
	$chskin = $data['skin'];
	$stampskin = $data['stamp_skin'];
	
	
	$tpl = 'joincheck/'.$chskin.'.html';
	$today = date('Y-m-d');
	$minfo = $this->userInfo;
	$minfo['user_name'] = $minfo['user_name'] ? $minfo['user_name'] : $minfo['userid'];

	// 이벤트 기간 체크
	if(!defined('__ISADMIN__')) {
		if($data['start_date'] > $today){
			pageRedirect("/",getAlert("et051"));	// "이벤트 시작 전입니다."
			exit;
		}else if($data['end_date'] < $today){
			//pageRedirect("/","종료된  이벤트입니다.");
			//exit;
		}
	}
	
	//로그인 회원 결과값 가져오기
	$mquery=$this->db->query("select * from fm_joincheck_result where joincheck_seq=".$joincheck_seq." and member_seq=?",$minfo['member_seq']);
	$mdata = $mquery->row_array();
	
	if($data['check_clear_type']=='straight'){
		$data['mclear_type']= getAlert("sy065");	// '연속';
		$mdata['acount']=$mdata['straight_cnt'];
	}else{
		$mdata['acount']=$mdata['count_cnt'];
		
	}
	if($mdata['acount']==''){$mdata['acount']='0';}
	$mdata['chage']= $data['check_clear_count'] - $mdata['acount'];
	if($mdata['chage'] < 0) { $mdata['chage']=0;} 
	
	switch($data['check_type']){
		case 'comment':
			$mdata['type_ment'] = getAlert("sy066");	//"댓글을 입력하면";
		break;
		case 'stamp':
			$mdata['type_ment'] = getAlert("sy067");	//"'출석체크 버튼'을 누르면";
		break;
		case 'login':
			$mdata['type_ment'] = getAlert("sy068");	//"로그인 하면";
		break;		
	}
	
	$sdate=$data['start_date'];
	$edate=$data['end_date'];
	//상위 공통 	
	
	//댓글형
	if($chskin=='comment_basic'||$chskin=='comment_simple'){
		
		
		$next = date('Y-m-d',strtotime('+1 day',strtotime($tdate)));
		$prev = date('Y-m-d',strtotime('-1 day',strtotime($tdate)));
		
		//내가 댓글 찾기가 아닐때
		if($mode != 'mylog'){
						
			$a_style='';
			//$a_style='style="font-size:11px; font-family:tahoma; font-weight:bold; color:#6f6f6f;"';
			if($chskin=='comment_basic'){
				$data['tdate']=str_replace('-','.',$tdate);
			}elseif($chskin=='comment_simple'){
				$data['tdate']= date('Y'.getAlert("sy006").' m'.getAlert("sy007").' d'.getAlert("sy008").'',strtotime($tdate));	// 년월일
			}	
					
			if($tdate <= $sdate){
				$data['prev_day']= 	"<span ".$a_style.">".str_replace('-','.',$prev)."</span>";	
			}else{		
				$data['prev_day'] = "<a ".$a_style." href='?seq=".$joincheck_seq."&tdate=".str_replace('.','',$prev)."'><img src='/data/joincheck/".$chskin."/btn_arrow_prev.gif'>".str_replace('-','.',$prev)."</a>";
			}
			if($tdate >= $edate || $next > $today ){
				$data['next_day']= 	"<span ".$a_style.">".str_replace('-','.',$next)."</span>";
			}else{
				$data['next_day'] = "<a ".$a_style." href='?seq=".$joincheck_seq."&tdate=".str_replace('.','',$next)."'>".str_replace('-','.',$next)."<img src='/data/joincheck/".$chskin."/btn_arrow_next.gif'></a>";			
				
			}		
				
			$sql = "select SQL_CALC_FOUND_ROWS fjr.*	,
				mem.userid,
				mem.user_name			
				from fm_joincheck_list fjr					
				left join fm_member as mem on mem.member_seq=fjr.member_seq
				where joincheck_seq=".$joincheck_seq." and check_date='".$tdate."'
				order by jclist_seq desc";
			$cosql="select count(*) as cnt from fm_joincheck_list where joincheck_seq=".$joincheck_seq." and check_date='".$tdate."'";
		
		// 내가 쓴글 찾기 버튼 눌렀을때
		}elseif($mode=='mylog'){
			
				$sql= " select SQL_CALC_FOUND_ROWS fjr.*	,
				mem.userid,
				mem.user_name			
				from fm_joincheck_list fjr					
				left join fm_member as mem on mem.member_seq=fjr.member_seq
				where joincheck_seq=".$joincheck_seq." and fjr.member_seq = ".$minfo['member_seq']."
				order by jclist_seq desc";
			$cosql="select count(*) as cnt from fm_joincheck_list where joincheck_seq=".$joincheck_seq." and member_seq = ".$minfo['member_seq'];
		}		
			
		$cntquery = $this->db->query($cosql);
		$allcnt = $cntquery -> row_array();
		$result = select_page($data['comment_list'],$sc['page'],10,$sql,array());
		
		
	$this->template->assign($result);
		
					
	
	//달력형
	}elseif($chskin=='stamp_basic'||$chskin=='stamp_simple'){
		
		$ctdate=substr($tdate,0,7);
		$sdate= $data['start_date'];
		$edate= $data['end_date'];
		$next = date('Y-m',strtotime('+1 month',strtotime($tdate)));
		$prev = date('Y-m',strtotime('-1 month',strtotime($tdate)));
			
			if($ctdate <= $sdate){
				$data['prev_day']=  "<img src='/data/joincheck/".$chskin."/btn_arrow_prev.gif'>";	
			}else{		
				$data['prev_day'] = "<a ".$a_style." href='?seq=".$joincheck_seq."&tdate=".str_replace('.','',$prev)."'><img src='/data/joincheck/".$chskin."/btn_arrow_prev.gif'></a>";
			}
			if($ctdate >= $edate || $next > $today ){
				$data['next_day']= 	"<img src='/data/joincheck/".$chskin."/btn_arrow_next.gif'>";
			}else{
				$data['next_day'] = "<a ".$a_style." href='?seq=".$joincheck_seq."&tdate=".str_replace('.','',$next)."'><img src='/data/joincheck/".$chskin."/btn_arrow_next.gif'></a>";			
				
			}	
							
		$data['tdate']= date('Y'.getAlert("sy006").' m'.getAlert("sy007").'',strtotime($tdate));
		
		//달력 만들기
		$sd = explode("-", $tdate);			
		$year   =  $sd[0];
		$month 	=  $sd[1];
		$day    =  $sd[2];			
		
		$first 	= date("w" , mktime(0, 0, 0, $month, 1, $year));
		$col 	= 0;
		$row 	= 0;
		
		
			for ($i =0; $i < $first; $i ++) {
			 $cal[0][$col ++] = "";
			}
			
			for ($i =1; checkdate($month, $i, $year); $i ++) {
				 $cal[$row][$col ++] = $i;
				 if ($col % 7 == 0) {
				  $row ++;
				  $col = 0;
				 }
			}
		
		
			for (;$col % 7 != 0;   $col ++) {
			  $cal[$row][$col] = "";
			  
			}
			for($i = 0; $i <= $row; $i ++) {
				$cl = $cl."<tr>";	
				for($j = 0; $j < 6; $j ++) {
					 if ($j == 0){
					 	//일요일
					 	 if(1 == strlen($cal[$i][$j])){ $clstr= "0".$cal[$i][$j];
					 	 }else{  $clstr= $cal[$i][$j];}					 	 
					 	 $tiday = $year."-".$month."-".$clstr;	

					 	 
					  	if($today >= $tiday &&  $clstr != "" && $minfo['member_seq'] && $tiday >= $sdate){
					  	  	$ck_img = $this->check_img($joincheck_seq,$minfo['member_seq'],$tiday,$stampskin);	
					 	
					 	 }else{ $ck_img='';}	
				 	 	 				 	 
					 	$cl = $cl."<td width='35px' height='48px' class='stl-sun' align='right' valign='top'>".$clstr."<br/>".$ck_img."</td>";
					 	
					 }else{
					 	//평일
					  if(1 == strlen($cal[$i][$j])){ $clstr= "0".$cal[$i][$j];
					 	 }else{  $clstr= $cal[$i][$j];}
					  $tiday = $year."-".$month."-".$clstr;		
					 	 	 	 
					  if($today >= $tiday &&  $clstr != "" && $minfo['member_seq'] && $tiday >= $sdate){
					  	
					  	$ck_img = $this->check_img($joincheck_seq,$minfo['member_seq'],$tiday,$stampskin);
					 
					 	 }else{ $ck_img='';}
					 	 
					  	$cl = $cl."<td width='35px' height='48px' class='stl-week' align='right' valign='top'>".$clstr."<br/>".$ck_img."</td>";
					 }
				}	
					//토요일	
					if(1 == strlen($cal[$i][$j])){ $clstr= "0".$cal[$i][$j];
					  }else{  $clstr= $cal[$i][$j];}
			 		
					  $tiday = $year."-".$month."-".$clstr;
					  	 	 	 
					  if($today >= $tiday &&  $clstr != "" && $minfo['member_seq'] && $tiday >= $sdate){
					  	  	$ck_img = $this->check_img($joincheck_seq,$minfo['member_seq'],$tiday,$stampskin);	
					 
					 	 }else{ $ck_img='';}
					 	 
				$cl = $cl."<td width='35px' height='48px' class='stl-sat' align='right' valign='top'>".$clstr."<br/>".$ck_img."</td>";
			}	
			
			
	}
	

		//메타테그 치환용 정보
		$add_meta_info['event_title'] = $data['title'];
		$this->template->assign('add_meta_info', $add_meta_info);

		if(isset($data)) $this->template->assign('joincheck',$data);
		if($c==2)$pagin = '<p><a class="on red">1</a><p>';		
		$this->template->assign('cl',$cl);			//달력
		$this->template->assign('pagin',$pagin);	//페이징		
		$this->template->assign('minfo',$minfo);	//회원정보
		$this->template->assign('mdata',$mdata);	//회원결과정보
		$this->template_path = $tpl;
		$this->template->assign(array("template_path"=>$this->template_path));	
		$this->print_layout($this->skin.'/'.$tpl);	
	
	}
	
	//출석, 결석 체크
	function check_img($joincheck_seq,$member_seq,$tiday,$stampskin){
	 	 $sql = "select SQL_CALC_FOUND_ROWS *						
			from fm_joincheck_list
			where joincheck_seq=".$joincheck_seq." and member_seq=".$member_seq." and check_date='".$tiday."'";
	 	 $query = $this -> db -> query($sql);
	 	 $rcl= $query -> row_array();					 	 
		 	if($rcl){ $ck_img = "<center><img src='/data/joincheck/stamp/stamp_".$stampskin."_attend.gif'></center>";
		 	 }else{	$ck_img = "<center><img src='/data/joincheck/stamp/stamp_".$stampskin."_absent.gif'></center>";	 }
	 	 return $ck_img;
	}


}
	