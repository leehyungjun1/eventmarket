<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class main extends selleradmin_base {

	public function __construct() {
		parent::__construct();

		$this->cach_file_path	= $_SERVER['DOCUMENT_ROOT'] . '/data/cach/';
		$this->cach_file_url		= '../../data/cach/';
		$this->cach_file_name	= 'admin_main_index.html';

		// 운영자별 페이지 생성
		$this->cach_stat_file	= 'admin_selleradmin_stats_'.$this->providerInfo['provider_seq'].'.html';

	}

	public function main_index()
	{
		redirect("/selleradmin/main/index");
	}

	// 메인화면
	public function index()
	{
		/* 출고예약량 */
		$cfg_reservation = config_load('reservation');

		$this->load->helper('board');//
		$this->load->model('Boardmanager');
		$this->load->model('providermodel');
		$this->load->model('Myminishopmodel');
		$data_provider							= $this->providermodel->get_provider($this->providerInfo['provider_seq']);
		$data_cnt 									= $this->Myminishopmodel->get_provider_minishop_count(array('provider_seq'=>$this->providerInfo['provider_seq']))->row_array();
		$data_provider['minishop_count']	= $data_cnt['cnt'];

		$this->template->assign('realboardurl',$this->Boardmanager->realboardurl);
		$this->template->assign('realboardwriteurl',$this->Boardmanager->realboardwriteurl);
		$this->template->assign('realboardviewurl',$this->Boardmanager->realboardviewurl);

		$this->_print_main_goods_summary();
		$this->chk_stats_caching();
		$this->_print_main_qna_summary();
		$this->_print_main_seller_summary();

		$this->admin_menu();
		$this->tempate_modules();
		$this->template->assign(
			array(
				'cfg_reservation'	=> $cfg_reservation,
				'data_provider'		=> $data_provider,
				'main'				=> true
			)
		);

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 주문처리 (최근 100일) */
	public function ajax_main_order_summary($type=""){
		$fromDay	=  date('Y-m-d 00:00:00',strtotime('-100day'));
		$orderSummary	= array();
		$step_arr			= array('15'=>'주문접수', '25'=>'결제확인', '35'=>'상품준비', '40'=>'부분출고준비', '45'=>'출고준비', '50'=>'부분출고완료', '55'=>'출고완료', '60'=>'부분배송중', '65'=>'배송중', '70'=>'부분배송완료');

		$sql = "SELECT count(*) as cnt ,
						CASE
						WHEN MIN(itmo.step) <> MAX(itmo.step) AND MIN(itmo.step) > '15' AND MIN(itmo.step) < '75' AND MAX(itmo.step) > '45' AND MAX(itmo.step) < '85' AND MAX(itmo.step) NOT IN (40,50,60,70) THEN MAX(itmo.step - 5)
						WHEN MAX(itmo.step) > '75' THEN MIN(itmo.step)
						ELSE MAX(itmo.step)
					END AS step
				FROM `fm_order` o
					INNER JOIN `fm_order_item` oi ON o.order_seq=oi.order_seq AND oi.provider_seq=?
					INNER JOIN fm_order_item_option itmo ON (oi.item_seq = itmo.item_seq)
					INNER JOIN fm_order_shipping spi ON (spi.shipping_seq = itmo.shipping_seq AND spi.provider_seq = ?)
				WHERE o.hidden = 'N' AND o.regist_date>=? AND itmo.step>=? AND itmo.step<=? 
				AND (label IS NULL OR (label='present' AND recipient_zipcode !='')) 
				GROUP BY o.order_seq";
		$query = $this->db->query($sql, [$this->providerInfo['provider_seq'], $this->providerInfo['provider_seq'], $fromDay, '25', '75']);
		foreach ($query->result_array() as $row) {
			$result[$row['step']] += 1;
		}

		$prevDate = date('Y-m-d', strtotime('-100day'));
		$nowDate  = date('Y-m-d');
		foreach ($step_arr as $key => $val){
			$orderSummary[$key] = array(
					'count'			=> ($result[$key]) ? $result[$key] : 0,
					'name'			=> $val,
					'link'			=> "../order/catalog?chk_step[".$key."]=1&regist_date[]=".$prevDate."&regist_date[]=".$nowDate
			);
			if($key == '45' || $key == '55' || $key == '65')
			{
				$orderSummary[$key]['link_export'] = "../export/catalog?export_status[".$key."]=1";
			}
		}

		/* 반품 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_return r
				INNER JOIN fm_order_return_item ri ON r.return_code=ri.return_code
				INNER JOIN `fm_order_item` oi ON ri.item_seq=oi.item_seq AND oi.provider_seq=? where r.regist_date>=? AND r.`status` = ?",array($this->providerInfo['provider_seq'],$fromDay,'request'));
		$result = $query->row_array();
		$orderSummary['101'] = array(
				'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
				'name'		=> '반품접수',
				'link'		=> '../returns/catalog?return_status[]=request&sdate='.$prevDate.'&edate='.$nowDate,
		);

		/* 환불 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_refund r
				INNER JOIN fm_order_refund_item ri ON r.refund_code=ri.refund_code
				INNER JOIN `fm_order_item` oi ON ri.item_seq=oi.item_seq AND oi.provider_seq=? where r.regist_date>=? AND r.`status` = ?",array($this->providerInfo['provider_seq'],$fromDay,'request'));
		$result = $query->row_array();
		$orderSummary['102'] = array(
				'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
				'name'		=> '환불접수',
				'link'		=> '../refund/catalog?refund_status[]=request&sdate='.$prevDate.'&edate='.$nowDate,
		);
		// debug($orderSummary);
		echo json_encode($orderSummary);
	}

	/* 상품현황 요약 :: 2014-10-21 lwh */
	public function _print_main_goods_summary($type=""){
		// alter table fm_goods add safe_stock_status varchar(7) null after goods_view;
		//## 판매중의 의미 : 내가 보유한 판매가 가능한 상품의 수 (노출의 여부는 중요 X)
		$goodsSummary = array(
				'safe_stock'=>array(
						'goods'		=>array('count'=>0,'link'=>'../goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'../goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'../goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'normal'=>array(
						'goods'		=>array('count'=>0,'link'=>'../goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&goodsView%5B%5D=look&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'../goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&goodsView%5B%5D=look&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'../goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&goodsView%5B%5D=look&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'runout'=>array(
						'goods'		=>array('count'=>0,'link'=>'../goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'../goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'../goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'unsold'=>array(
						'goods'		=>array('count'=>0,'link'=>'../goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'../goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'../goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				)
		);
		$query = "SELECT goods_status, goods_kind, package_yn, safe_stock_status, count(*) as cnt FROM fm_goods
				WHERE goods_type =? and provider_seq=?
				GROUP BY goods_status, goods_kind, package_yn, safe_stock_status";
		$query = $this->db->query($query, array( 'goods', $this->providerInfo['provider_seq']));
		foreach($query->result_array() as $data)
		{
			if($data['package_yn']=='y') $data['goods_kind'] = 'package';
			if($data['safe_stock_status']=='y')
			{
				$goodsSummary['safe_stock'][$data['goods_kind']]	['count']	+= (int) $data['cnt'];
			}
			if($data['goods_status']=='normal')
			{
				$goodsSummary['normal'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
			if(in_array($data['goods_status'],array('runout','purchasing')))
			{
				$goodsSummary['runout'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
			if(in_array($data['goods_status'],array('unsold')))
			{
				$goodsSummary['unsold'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
		}
		$this->template->assign(array('goodsSummary'=>$goodsSummary));
	}

	/* 메인 페이지 통계 캐쉬 생성 시간 체크 */
	public function chk_stats_caching()
	{
		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;
		//admin과 동일하게 2020-03-12 
		//if (!file_exists($cache_file_path) || strtotime('-4 hour') > filemtime($cache_file_path))
		$this->main_stats_caching();

		return filemtime($cache_file_path);
	}

	/* 메인 페이지 통계 캐쉬 처리 */
	public function main_stats_caching()
	{
		ob_start();
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('advanced_statistic');
		if(!$result['type']){
			$this->advanced_statistic_limit	= 'y';
		}
		$this->load->model('statsmodel');
		$result	= $this->statsmodel->get_selleradmin_statistic_json($this->providerInfo['provider_seq']);
		echo json_encode($result);

		$cach_stats	= ob_get_contents();
		ob_end_clean();

		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;

		$file_obj	= fopen($cache_file_path, 'w+');
		if	(!$file_obj){
			$dir_name	= dirname($cache_file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name,0777);
			$file_obj	= fopen($cache_file_path, 'w+');
		}

		fwrite($file_obj, $cach_stats);
		fclose($file_obj);
	}

	/* 메인 페이지 통계 캐쉬 제거 */
	public function main_stats_cach_delete()
	{
		// 운영자별 페이지 생성 체크
		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;

		if	(file_exists($cache_file_path)){
			@unlink($cache_file_path);
		}
		echo json_encode(array('result'=>'OK'));
	}

	public function json_main_stats()
	{
		$filePath	= $this->cach_file_path . $this->cach_stat_file;
		if(file_exists($filePath)) {
			$handle			= fopen($filePath, "r");
			$fileContents	= fread($handle, filesize($filePath));
			fclose($handle);

			if($_GET['mode'] == 'debug'){
				debug(json_decode($fileContents));
				exit;
			}

			if(!empty($fileContents)) {
				echo $fileContents;
			}
		}
	}

	/* 문의 요약 */
	public function _print_main_qna_summary(){
		$this->load->helper('board');//

		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');

		$this->template->assign('realboardurl',$this->Boardmanager->realboardurl);
		$this->template->assign('realboardwriteurl',$this->Boardmanager->realboardwriteurl);
		$this->template->assign('realboardviewurl',$this->Boardmanager->realboardviewurl);

		$limit = 4;

		unset($bdwidget, $widgetloop,$boardurl);
		$bdwidget['boardid']	= 'goods_qna';
		$bdwidget['limit']		= $limit;//
		$this->getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('goodsqnaname'=>$name,'goodsqnatotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('goodsqnaloop',$widgetloop);
	}


	/* 입점사문의/공지 요약 */
	public function _print_main_seller_summary(){
		$this->load->helper('board');//

		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');

		$this->template->assign('realboardurl',$this->Boardmanager->realboardurl);
		$this->template->assign('realboardwriteurl',$this->Boardmanager->realboardwriteurl);
		$this->template->assign('realboardviewurl',$this->Boardmanager->realboardviewurl);

		$limit = 4;

		unset($bdwidget, $widgetloop,$boardurl);
		$bdwidget['boardid']	= 'gs_seller_notice';
		$bdwidget['limit']		= $limit;//
		$this->getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('sellernoticename'=>$name,'sellernoticetotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('sellernoticeloop',$widgetloop);

		unset($bdwidget, $widgetloop,$boardurl);
		$auth = $this->authmodel->manager_limit_act('board_view');
		if ($auth) {
			$bdwidget['boardid']	= 'gs_seller_notice';
			$bdwidget['onlypopup']	= true;//팝업여부
			$this->getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		}
		if(isset($widgetloop)) $this->template->assign('popupsellernoticeloop',$widgetloop);
	}


	// 관리자 > 메인화면 추출용
	function getAdminBoardWidgets($bdwidget, & $widgetloop, & $name, & $totalcount)
	{

		$boardid = $bdwidget['boardid'];

		$this->load->helper('text');//strcut
		$this->load->model('Boardmanager');
		if( $boardid == 'goods_qna' ) {
			$this->load->model('Goodsqna','qnaBoardmodel');
		}elseif( $boardid == 'goods_review' ) {
			$this->load->model('Goodsreview','reviewBoardmodel');
		}elseif( $boardid == 'bulkorder' ) {
			$this->load->model('Boardbulkorder','Boardmodel');
		}else{
			$this->load->model('Boardmodel');
		}
		$this->load->model('Boardindex');


		$querystr = '';
		$sc['whereis']	= ' and id= "'.$boardid.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$name = $this->manager['name'];
		$totalcount = $this->manager['totalnum'];

		$this->boardurl->lists		= $this->Boardmanager->realboarduserurl.$boardid.$querystr;				//게시물관리

		$this->boardurl->write	= $this->Boardmanager->realboardwriteurl.$boardid.$querystr;				//게시물등록
		$this->boardurl->modify	= $this->Boardmanager->realboardwriteurl.$boardid.$querystr.'&seq=';	//게시물수정
		$this->boardurl->view		= $this->Boardmanager->realboardviewurl.$boardid.$querystr.'&seq=';	//게시물보기
		$this->boardurl->reply		= $this->Boardmanager->realboardwriteurl.$boardid.$querystr.'&reply=y&seq=';	//게시물답변

		$this->boardurl->perm		= $this->Boardmanager->realboardpermurl.$boardid.'&returnurl=';						//접근권한
		$this->boardurl->pw			= $this->Boardmanager->realboardpwurl.$boardid.'&returnurl=';						//접근권한

		$this->icon_file_img			= $this->Boardmanager->file_icon_src;//첨부파일icon
		$this->icon_hidden_img		= $this->Boardmanager->hidden_icon_src;//비밀글icon
		$this->notice_img				= $this->Boardmanager->notice_icon_src;//공지글icon
		$this->re_img						= $this->Boardmanager->re_icon_src;//답변글icon
		$this->blank_img				= $this->Boardmanager->blank_icon_src;//blank
		$this->print_img					= $this->Boardmanager->print_icon_src;//print

		$boardurl = $this->boardurl;

		/**
		 * icon setting
		**/
		$this->icon_new_img			= ($this->manager['icon_new_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.$boardid.'/'.$this->manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($this->manager['icon_hot_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.$boardid.'/'.$this->manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon

		$this->icon_review_img			= ($this->manager['icon_review_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_review_img']) ) ? $this->Boardmanager->board_data_src.$boardid.'/'.$this->manager['icon_review_img'].'?'.time():$this->Boardmanager->review_icon_src;//hoticon

		get_auth($this->manager, '', 'read', $isperm);//접근권한체크
		$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';
		$this->manager['fileperm_read']= (isset($isperm['fileperm_read']))?$isperm['fileperm_read']:'';

		get_auth($this->manager, '', 'write', $isperm);//접근권한체크
		$this->manager['isperm_write'] = ($isperm['isperm_write'] === true)?'':'_no';


		if( $boardid == 'goods_qna') {
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			$widgetsql['provider_seq']	= $this->providerInfo['provider_seq'];//해당입점상품문의인경우
			$wdata = $this->qnaBoardmodel->data_list($widgetsql);//게시판목록
		}elseif( $boardid == 'goods_review') {
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			//$widgetsql['provider_seq']	= $this->providerInfo['provider_seq'];//해당입점상품문의인경우
			$wdata = $this->reviewBoardmodel->data_list($widgetsql);//게시판목록
		}else{
			//입점사문의글인경우
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['boardid']			= $boardid;
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			if($bdwidget['notice']) $widgetsql['notice']	= $bdwidget['notice'];
			if($bdwidget['onlypopup']) $widgetsql['onlypopup']	= $bdwidget['onlypopup'];
			if($boardid == 'gs_seller_qna' ) {
				$widgetsql['mseq']				= '-'.$this->providerInfo['provider_seq'];
			}
			$wdata = $this->Boardmodel->data_list($widgetsql);//게시판목록
		}
		$idx = ($bdwidget['limit']<$this->manager['totalnum']) ? $bdwidget['limit']:$this->manager['totalnum'];
		foreach($wdata['result'] as $widget){
			if(isset($widget['seq'])) {

				$widget['number'] = $idx;//번호
				$widget['category'] = (!empty($widget['category']) )? ' <span class="cat">['.$widget['category'].']</span>':'';

				getminfo($this->manager, $widget, $mdata, $boardname);//회원정보
				$widget['name'] = $boardname;
				$widget['reply_title']		= ($widget['re_contents'])?'<span class="blue" >답변완료</span>':'<span class="gray" >답변대기</span>';//상태 답변완료 답변대기

				if($this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$widget['m_date']),0,8))) >= date("Ymd") ) {//new
					$widget['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" > ';
				}

				if($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $widget['hit'] ) {//조회수
					$widget['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" > ';
				}

				if( getBoardFileck($widget['upload'], $widget['contents']) ) {//첨부파일
					$widget['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" > ';
				}


				if($widget['display'] == 1 ){//삭제시
					$widget['iconnew']	= '';
					$widget['iconhot']		= '';
					$widget['iconfile']		= '';
					$widget['iconhidden'] = '';
					$widget['blank']			= ($widget['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($widget['depth']-1)*13).'" ><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($widget['comment']>0) ? ' <span class="comment">('.number_format($widget['comment']).')</span>':'';
					$widget['subject']		= $widget['blank'].' <span class="hand gray boad_view_btn'.$this->manager['isperm_read'].'" viewlink="'.$this->boardurl->view.$widget['seq'].'"  fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$widget['seq'].'"  board_id="'.$boardid.'" ><a>삭제되었습니다 ['.substr($widget['r_date'],0,16).']</a></span>'.$commentcnt;
					$widget['date']			= substr($widget['m_date'],0,16);//삭제일

					if($widget['replyor'] == 0 && $widget['comment'] == 0) {//삭제후 답변이나  댓글이 없는 경우 삭제가능
						$widget['deletebtn'] = '<span class="btn small  valign-middle"><input type="button" name="boad_delete_btn" board_seq="'.$widget['seq'].'"  board_id="'.$boardid.'" value="삭제" /></span>';
					}
				}else{
					if( $this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$widget['m_date']),0,8))) >= date("Ymd") ) {//new
						$widget['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" > ';
					}else{
						$widget['iconnew'] ='';
					}

					$widget['subject']		= strip_tags($widget['subject']);//제목추출시 tag 제거

					$widget['iconhot']		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $widget['hit']) ? ' <img src="'.$this->icon_hot_img.'" title="hot" > ':'';//조회수
					$widget['iconfile']		= ( getBoardFileck($widget['upload'], $widget['contents']) ) ?' <img src="'.$this->icon_file_img.'" title="첨부파일" > ':'';//첨부파일
					$widget['iconhidden'] = ($widget['hidden'] == 1 ) ? ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

					$widget['date']			= substr($widget['m_date'],0,16);//등록일
					$widget['blank']			= ($widget['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($widget['depth']-1)*53).'" ><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($widget['comment']>0) ? ' <span class="comment">('.number_format($widget['comment']).')</span>':'';
					$widget['subject']		= $widget['blank'].$widget['category'].' '.$widget['subject'].'</span>'.$commentcnt;
				}

				$widget['subject']		= $widget['blank'].' <span class="hand '.$boardid.'_boad_view_btn" viewlink="'.$this->boardurl->view.$widget['seq'].'" board_seq="'.$widget['seq'].'"  board_id="'.BOARDID.'" >'.$widget['subject'].'</span>';

				if(  $boardid == 'goods_review' ) {
					$widget['scorelay'] = getGoodsScore($widget['score'], $this->manager);
					$widget['emoneylay']	=  getBoardEmoneybtn($widget, $this->manager,'view');
				}

				if(!empty($widget['goods_seq']) && $widget['depth'] == 0 ){
					$widget['goodsview']	= getGoodsinfo($widget, $widget['goods_seq'], 'view');
				}else{
					$widget['goodsview']	= '';
				}

				$widgetloop[] = $widget;
			}
			$idx--;
			unset($widget);
		}
	}

	/* 업그레이드 영역 Define */
	public function _print_main_news_upgrade_area(){
		$this->load->helper('text');
		$this->load->library('SofeeXmlParser');
echo "https://gapi.firstmall.kr/rss?channel=upgrade&solution=firstmall_plus&shopSno={$this->config_system['shopSno']}&service_type=".SERVICE_CODE."&limit=8";
exit;
		$xmlParser = new SofeeXmlParser();
		$xmlParser->parseFile("https://gapi.firstmall.kr/rss?channel=upgrade&solution=firstmall_plus&shopSno={$this->config_system['shopSno']}&service_type=".SERVICE_CODE."&limit=8");
		$tree = $xmlParser->getTree();

		$mainNewsUpgradeList = $tree['rss']['channel']['item'];

		$this->template->assign(array('mainNewsUpgradeList'=>$mainNewsUpgradeList));
		$this->template->define(array('main_news_upgrade_area'=>$this->skin."/main/_main_news_upgrade_area.html"));
		$this->template->print_("main_news_upgrade_area");
	}

	public function login(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function popup_change_pass()
	{
		$this->template->define(array('tpl'=>$this->skin."/main/popup_change_pass.html"));
		$this->template->print_("tpl");
	}
}

/* End of file main.php */
/* Location: ./app/controllers/selleradmin/main.php */