<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class link extends front_base {

	/* 추가페이지 뷰 */
	public function index()
	{
		$tpl = $this->uri->segments[2] . '/' . $this->uri->segments[3];
		$layoutPath = check_display_skin_file($this->skin, $tpl);

		if ($layoutPath === false) {
			show_error(getAlert('et001'));
		}

		$this->template_path = $tpl;
		$this->template->assign(array("template_path"=>$this->template_path));

		$data = $this->check_event();

		//메타테그 치환용 정보
		$add_meta_info['event_title']		= $data['title'];
		$this->template->assign('add_meta_info',$add_meta_info);

		$this->print_layout($layoutPath);
	}

	/* 이벤트페이지 체크 */
	public function check_event(){
		$this->load->model('eventmodel');

		$event_type = "event";
		if($this->eventmodel->is_gift_template_file($this->template_path)){
			$event_type = "gift";
			$query = $this->db->query("select *, if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status from fm_".$event_type." where tpl_path=?",array($this->template_path));
		}else{//
			$query = $this->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_".$event_type." where tpl_path=?",array($this->template_path));
		}

		$data = $query->row_array();

		$this->template->assign(array($event_type."_seq"=>$data[$event_type.'_seq']));

		if($data){

			// 관리자가 아닌경우
			if(!defined('__ISADMIN__')) {

				$this->load->helper("javascript");
				$_redirect	= '/';
				if(preg_match('/promotion\/event/', $_SERVER['HTTP_REFERER'])){
					$_redirect	= $_SERVER['HTTP_REFERER'];
				}

				// 페이지뷰 증가
				$this->db->query("update fm_".$event_type." set pageview=pageview+1 where tpl_path=?",array($this->template_path));

				// 이벤트 노출 체크
				if($data['display']=='n'){
					//공개되지 않은 이벤트입니다.
					pageRedirect($_redirect,getAlert('et050'));
					exit;
				}

				// 이벤트 종료 체크
				switch($data['status']){
					case "시작 전":
						pageRedirect($_redirect,getAlert('et051')); //이벤트 시작 전입니다.
						exit;
					break;
					case "종료":
						pageRedirect($_redirect,getAlert('et052')); //종료된  이벤트입니다.
						exit;
					break;
				}

			}

		}
		return $data;
	}

	// 캐시를 사용하지 않는 css 호출용
	public function css(){

		session_write_close();
		header("Content-type: text/css", true);

		// css 키값을 받아와서 해당 키값에 맞는 css 경로로 파일 읽음
		$css_key	= $this->input->get('k');
		// 디자인모드에 따라 로드되는 skin 달라짐
		$skin_kind = $this->designMode ? $this->workingMobileSkin : $this->realMobileSkin;

		$css_files	= array(
			'quickdesign' => ROOTPATH.'data/skin/'.$skin_kind.'/css/quick_design.css',
		);

		// 해당 키값에 경로가 있는 경우만 읽어서 노출
		if(!empty($css_files[$css_key])){
			$css_contents	= file_get_contents($css_files[$css_key]);

			echo $css_contents;
		}
	}
}

