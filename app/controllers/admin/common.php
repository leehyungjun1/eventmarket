<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class common extends admin_base {

	public function __construct() {
		parent::__construct();
	}

	/* 쿼리로 직접 엑셀 다운로드 */
	public function DirectExcelDownload(){
		$this->load->helper('download');

		//$title		= iconv("utf-8","euc-kr",$_POST['title']);
		$title		= $this->input->post('title');
		$excel_type	= $this->input->post('excel_type');

		parse_str($_POST['param'], $_GET);

		// 방문통계 유입경로 (통계-방문통계-기본-시간별)
		if($excel_type == 'visitor_referer_table'){
			$_GET['year']	= !empty($_GET['year'])		? $_GET['year']		: date('Y');
			$_GET['month']	= !empty($_GET['month'])	? $_GET['month']	: date('m');
			$_GET['day']	= !empty($_GET['day'])		? $_GET['day']		: date('d');

			$stats_date = $_GET['year'] .'-'. $_GET['month'] .'-'. $_GET['day'];
			$this->db->order_by("count desc");
			$query = $this->db->get_where('fm_stats_visitor_referer',array('stats_date'=>$stats_date));
			$loopData = $query->result_array();

			$contents	= '<table border="1" width="800"><tr>';
			$contents	.= '<td>유입경로</td><td>방문자수</td></tr>';
			if($loopData){
				$refererCountSum	= 0;
				foreach($loopData as $val){
					$refererCountSum	+= $val['count'];
					$referer_url		= ($val['referer']) ? $val['referer'] : '직접입력';
					$contents	.= '<tr>';
					$contents	.= '	<td>'.$referer_url.'</td>';
					$contents	.= '	<td>'.number_format($val['count']).'</font></td>';
					$contents	.= '</tr>';
				}
				$contents	.= '<tr><td>합계</td><td>'.number_format($refererCountSum).'</td></tr>';
			}else{
				$contents	.= '<tr><td colspan="2">데이터가 없습니다.</td></tr>';
			}
		}elseif($excel_type == 'sales_goods_day_table'){

			$this->load->model("statsmodel");

			$_GET['year']			= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
			$_GET['month']			= (trim($_GET['month']))	? str_pad(trim($_GET['month']), 2, '0', STR_PAD_LEFT)	: date('m');
			$params['sdate']		= $_GET['year'].'-'.$_GET['month'].'-'.'01';
			$params['edate']		= $_GET['year'].'-'.$_GET['month'].'-'.date('d');
			$params['sort']			= (trim($_GET['sort']))		? trim($_GET['sort'])	: "ord.deposit_date desc";
			$params['sitetype']		= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
			$params['provider_seq']	= (int)$_GET['provider_seq'];
			$params['keyword']		= trim($_GET['keyword']);
			$statsData				= array();
			$statsDataSum			= array();
			$search_mode			= 'order';
			if($_GET['keyword'] || $_GET['provider_seq'] > 0)	$search_mode = 'item';

			// 시작날짜와 끝 날짜의 달이 다른경우 끝 날짜에 맞춰서 정산테이블 가져옴
			$table_name = $this->statsmodel->get_stat_table($params['edate']);
			// 테이블이 없는 경우 초기화면으로
			if($table_name == '') {
				echo "<script>alert('데이터가 없습니다.');history.back();</script>";
				exit;
			}
			$params['table_name'] = $table_name;

			$params['q_type']	= 'list';
			$sumquery	= $this->statsmodel->get_sales_goods_daily_stats($params,'sum');
			list($statsDataSum) = $sumquery->result_array();

			$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
			foreach($query->result_array() as $row) {
				$statsData[] = $row;
			}

			// 전체 갯수 :: 2014-08-20 lwh
			$cnt_query	= $this->statsmodel->get_sales_goods_daily_stats($params,'cnt');
			$cntData	= $cnt_query->result_array();
			$listCnt	= count($cntData);

			$params['q_type']	= 'order';
			$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
			list($orderData) = $query->result_array();

			$params['q_type']	= 'refund';
			$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
			list($refundData) = $query->result_array();

			//환불 금액에서 할인 된 내역을 빼줘야한다 :: 2018-08-08 pjw
			$refundData['refund_sale_price_sum'] = $refundData['event_sale'] + $refundData['multi_sale'] + $refundData['member_sale'] + $refundData['fblike_sale'] + $refundData['mobile_sale'] + $refundData['promotion_code_sale'] + $refundData['referer_sale'] + $refundData['coupon_sale']  + $refundData['refund_emoney_sum'] + $refundData['refund_enuri_sum'];

			//환불 합
			$refundData['refund_sum']		= $refundData['refund_price_sum'];

			//소계2
			$orderData['sub_price_sum1']	= $orderData['shipping_cost_sum'] + $orderData['return_shipping_cost_sum'] - $orderData['shipping_coupon_sale_sum'] - $orderData['shipping_promotion_code_sale_sum'];
			$orderData['sub_price_sum2']	= $refundData['refund_sum'] - $refundData['refund_sale_price_sum'] + $orderData['emoney_use_sum'] + $orderData['enuri_sum'];

			//매출합계
			//배송비합계가 크면 더함
			if( $orderData['sub_price_sum1'] > $orderData['sub_price_sum2'] )
				$orderData['sub_price_sum']		= ($orderData['sub_price_sum1']-$orderData['sub_price_sum2']);
			else
				$orderData['sub_price_sum']		= ($orderData['sub_price_sum2']-$orderData['sub_price_sum1']);

			$orderData['sub_price_sum_txt']	= " - ".number_format($orderData['sub_price_sum']);
			$orderData['sales_sum']			= $statsDataSum['goods_price'] - $orderData['sub_price_sum'] - $statsDataSum['event_sale'] - $statsDataSum['multi_sale'] - $statsDataSum['coupon_sale'] - $statsDataSum['member_sale'] - $statsDataSum['fblike_sale'] - $statsDataSum['mobile_sale'] - $statsDataSum['promotion_code_sale'] - $statsDataSum['referer_sale'];

			$orderData['sales_sum_txt']	= number_format($statsDataSum['goods_price'])."(소계①)";
			$orderData['sales_sum_txt']	.= $orderData['sub_price_sum_txt']."(소계②)";
			$orderData['sales_sum_txt']	.= ($statsDataSum['event_sale'] > 0) ? ' - '.number_format($statsDataSum['event_sale']).'(이벤트)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['multi_sale'] > 0) ? ' - '.number_format($statsDataSum['multi_sale']).'(복수구매)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['coupon_sale'] > 0) ? ' - '.number_format($statsDataSum['coupon_sale']).'(쿠폰)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['member_sale'] > 0) ? ' - '.number_format($statsDataSum['member_sale']).'(등급)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['fblike_sale'] > 0) ? ' - '.number_format($statsDataSum['fblike_sale']).'(좋아요)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['mobile_sale'] > 0) ? ' - '.number_format($statsDataSum['mobile_sale']).'(모바일)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['promotion_code_sale'] > 0) ? ' - '.number_format($statsDataSum['promotion_code_sale']).'(코드)' : '';
			$orderData['sales_sum_txt']	.= ($statsDataSum['referer_sale'] > 0) ? ' - '.number_format($statsDataSum['referer_sale']).'(유입)' : '';

			// 상품 일별 데이터
			$contents = '<table width="100%" style="margin:auto;" border="1" cellpadding="0" cellspacing="0">';
			$contents .= '<tr>';
			$contents .= '	<th rowspan="2" width="40" bgcolor="#F3F3F3">번호</th>';
			$contents .= '	<th rowspan="2" width="80" bgcolor="#F3F3F3">매출일</th>';
			$contents .= '	<th rowspan="2" colspan="2" bgcolor="#F3F3F3">판매상품</th>';
			$contents .= '	<th rowspan="2" width="80" bgcolor="#F3F3F3">매입가</th>';
			$contents .= '	<th rowspan="2" width="80" bgcolor="#F3F3F3">정가</th>';
			$contents .= '	<th rowspan="2" bgcolor="#F3F3F3">할인가</th>';
			$contents .= '	<th rowspan="2" bgcolor="#F3F3F3">수량</th>';
			$contents .= '	<th rowspan="2" bgcolor="#F3F3F3"><b>매출합계</b></th>';
			$contents .= '	<th colspan="6" bgcolor="#F3F3F3">할인</th>';
			$contents .= '</tr>';
			$contents .= '<tr>';
			$contents .= '	<th bgcolor="#F3F3F3">쿠폰</th>';
			$contents .= '	<th bgcolor="#F3F3F3">등급</th>';
			$contents .= '	<th bgcolor="#F3F3F3">좋아요</th>';
			$contents .= '	<th bgcolor="#F3F3F3">모바일</th>';
			$contents .= '	<th bgcolor="#F3F3F3">코드</th>';
			$contents .= '	<th bgcolor="#F3F3F3">유입</th>';
			$contents .= '</tr>';
			$contents .= '<tbody>';
			if($statsData){
				foreach($statsData as $key => $val){
					$contents .= '<tr>';
					$contents .= '	<td align="center">'.((int)$key+1).'</td>';
					$contents .= '	<td align="center">'.$val['deposit_ymd'].'</td>';
					$contents .= '	<td width="180" align="left" >';
					$contents .= '		<div >['.$val['provider_name'].']</div>';
					$contents .= '		'.$val['order_goods_name'];
					$contents .= '	</td>';
					$contents .= '	<td width="70" align="left">';
					if($val['title1'])
						$contents .= '		'.$val['title1'].' : '.$val['option1'].'<br />';
					if($val['title2'])
						$contents .= '		'.$val['title2'].' : '.$val['option2'].'<br />';
					if($val['title3'])
						$contents .= '		'.$val['title3'].' : '.$val['option3'].'<br />';
					if($val['title4'])
						$contents .= '		'.$val['title4'].' : '.$val['option4'].'<br />';
					if($val['title5'])
						$contents .= '		'.$val['title5'].' : '.$val['option5'].'<br />';
					$contents .= '	</td>';
					$contents .= '	<td align="right">'.number_format($val['supply_price']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['consumer_price']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['price']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['ea']).'</td>';
					$contents .= '	<td align="right"><b>'.number_format($val['goods_price']).'</b></td>';
					$contents .= '	<td align="right">'.number_format($val['coupon_sale']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['member_sale']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['fblike_sale']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['mobile_sale']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['promotion_code_sale']).'</td>';
					$contents .= '	<td align="right">'.number_format($val['referer_sale']).'</td>';
					$contents .= '</tr>';
				}
			}else{
				$contents .= '<tr>';
				$contents .= '<td colspan="9" align="center">데이터가 없습니다.</td>';
				$contents .= '<td align="right"></td>';
				$contents .= '<td align="right"></td>';
				$contents .= '<td align="right"></td>';
				$contents .= '<td align="right"></td>';
				$contents .= '<td align="right"></td>';
				$contents .= '<td align="right"></td>';
				$contents .= '</tr>';
			}
			$contents .= '</tbody>';
			$contents .= '</table>';

			// 정산 및 소계
			$contents .= '<table width="100%" style="margin:auto;" border="1" cellpadding="0" cellspacing="0" style="border-top:2px solid black;">';
			$contents .= '<tr>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td>&nbsp;</td>';
			$contents .= '	<td align="right">소계①</td>';
			$contents .= '	<td align="right"><b>'.number_format($statsDataSum['goods_price']).'</b></td>';
			$contents .= '	<td width="50px" align="right"><b>'.number_format($statsDataSum['coupon_sale']).'</b></td>';
			$contents .= '	<td width="50px"  align="right"><b>'.number_format($statsDataSum['member_sale']).'</b></td>';
			$contents .= '	<td width="50px"  align="right"><b>'.number_format($statsDataSum['fblike_sale']).'</b></td>';
			$contents .= '	<td  width="50px" align="right"><b>'.number_format($statsDataSum['mobile_sale']).'</b></td>';
			$contents .= '	<td  width="50px" align="right"><b>'.number_format($statsDataSum['promotion_code_sale']).'</b></td>';
			$contents .= '	<td  width="50px" align="right"><b>'.number_format($statsDataSum['referer_sale']).'</b></td>';
			$contents .= '</tr>';
			if($search_mode=='order'){
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(+)기본배송비</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['shipping_cost_sum']).'</b></td>';
				$contents .= '	<td colspan="6" rowspan="5"></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(+)반품배송비</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['return_shipping_cost_sum']).'</b></td>';
				$contents .= '	<td colspan="6" rowspan="5"></td>';
				$contents .= '</tr>';
				/*
					$contents .= '<tr>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td>&nbsp;</td>';
					$contents .= '	<td colspan="2" align="right">(+)개별배송비</td>';
					$contents .= '	<td align="right"><b>'.number_format($orderData['goods_shipping_cost_sum']).'</b></td>';
					$contents .= '</tr>';
				*/
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(-)배송비쿠폰</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['shipping_coupon_sale_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(-)배송비코드</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['shipping_promotion_code_sale_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(-)마일리지사용</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['emoney_use_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(-)에누리</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['enuri_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(-)환불 금액</td>';
				$contents .= '	<td align="right"><b>'.number_format($refundData['refund_price_sum']).'</b></td>';
				$contents .= '	<td colspan="6" rowspan="3"></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(+)환불 할인액</td>';
				$contents .= '	<td align="right"><b>'.number_format($refundData['refund_sale_price_sum']).'</b></td>';
				$contents .= '	<td colspan="6" rowspan="3"></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">소계②</td>';
				$contents .= '	<td align="right"><b>'.($orderData['sub_price_sum_txt']).'</b></td>';
				$contents .= '	<td colspan="6"></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(외)예치금사용</td>';
				$contents .= '	<td align="right"><b>'.number_format($orderData['cash_use_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td>&nbsp;</td>';
				$contents .= '	<td colspan="2" align="right">(외)환불 : 예치금</td>';
				$contents .= '	<td align="right"><b>'.number_format($refundData['refund_cash_sum']).'</b></td>';
				$contents .= '</tr>';
				$contents .= '<tr>';
				$contents .= '	<td colspan="15" align="center">'.$orderData['sales_sum_txt'].' = '. number_format($orderData['sales_sum']).' (매출합계)</td>';
				$contents .= '	</tr>';
			}//endif
			$contents .= '	</table>';
		}elseif($excel_type == 'sordwhs'){
			$this->load->model('scmmodel');

			$keyword_type		= array(	'goods_name'	=> '상품명',
											'goods_code'	=> '상품코드',
											'goods_seq'		=> '상품번호',
											'option_name'	=> '옵션명'		);
			if	(!$_GET['sc_month'])	$_GET['sc_month']		= date('n');
			if	(!$_GET['sc_currency'])	$_GET['sc_currency']	= 'KRW';

			unset($_GET['page'], $_GET['perpage']);
			$sc				= $_GET;
			$sType				= array_search(trim($_GET['keyword_sType']), $keyword_type);
			if($sType){
				$sc['src_key']	= $sType;
				$sc['keyword']	= trim($_GET['keyword']);
			}else{
				$sc['keyword']	= trim($_GET['keyword']);
			}

			// 전체 소계 추출
			$sc['sql_type']		= 'total';
			$total		= $this->scmmodel->get_sorder($sc);

			// 발주 내역 추출
			$sc['sql_type']		= 'order';
			$res		= $this->scmmodel->get_sorder($sc);

			// 입고 조정 및 입고 추출
			if	($res['page']['totalcount'] > 0){
				$sc['sql_type']			= 'whs';
				foreach($res['record'] as $data){
					$sc['goods_seq']	= $data['goods_seq'];
					$sc['option_type']	= $data['option_type'];
					$sc['option_seq']	= $data['option_seq'];
					$whsRes				= $this->scmmodel->get_sorder($sc);
					$data['whs']		= $whsRes[0];

					$loop[]				= $data;
				}
			}
			$rno		= count($loop);

			$contents = '<table class="simpledata-table-style" border="1" cellspacing="0">';
			$contents .= '<colgroup>';
			$contents .= '	<col width="50" />';
			$contents .= '	<col width="150" />';
			$contents .= '	<col width="*" />';
			$contents .= '	<col width="70" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="70" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '	<col width="100" />';
			$contents .= '</colgroup>';
			$contents .= '<thead class="lth">';
			$contents .= '<tr>';
			$contents .= '	<td align="center" rowspan="4">번호</td>';
			$contents .= '	<td align="center" rowspan="4">상품번호<br/>바코드</td>';
			$contents .= '	<td align="center" rowspan="4">상품/옵션</td>';
			$contents .= '	<td align="center" colspan="8">발주</td>';
			$contents .= '	<td align="center" colspan="8">입고</td>';
			$contents .= '</tr>';
			$contents .= '<tr>';
			$contents .= '	<td align="center" rowspan="3">수량</td>';
			$contents .= '	<td align="center" colspan="6">수입내역</td>';
			$contents .= '	<td align="center" rowspan="3">금액</td>';
			$contents .= '	<td align="center" rowspan="3">수량</td>';
			$contents .= '	<td align="center" colspan="6">수입내역</td>';
			$contents .= '	<td align="center" rowspan="3">금액</td>';
			$contents .= '</tr>';
			$contents .= '<tr>';
			$contents .= '	<td align="center" colspan="3">외화</td>';
			$contents .= '	<td align="center" colspan="3">원화</td>';
			$contents .= '	<td align="center" colspan="3">외화</td>';
			$contents .= '	<td align="center" colspan="3">원화</td>';
			$contents .= '</tr>';
			$contents .= '<tr>';
			$contents .= '	<td align="center">상품</td>';
			$contents .= '	<td align="center">운임</td>';
			$contents .= '	<td align="center">보험</td>';
			$contents .= '	<td align="center">CIF</td>';
			$contents .= '	<td align="center">관세</td>';
			$contents .= '	<td align="center">부대비용</td>';
			$contents .= '	<td align="center">상품</td>';
			$contents .= '	<td align="center">운임</td>';
			$contents .= '	<td align="center">보험</td>';
			$contents .= '	<td align="center">CIF</td>';
			$contents .= '	<td align="center">관세</td>';
			$contents .= '	<td align="center">부대비용</td>';
			$contents .= '</tr>';
			$contents .= '</thead>';
			$contents .= '<tbody class="ltb scm-ajax-list">';
			if($loop){
				foreach($loop as $k => $v){
			$contents .= '<tr class="list-row" style="height:45px;">';
			$contents .= '	<td align="center">' . $rno . '</td>';
			$contents .= '	<td align="left">';
			$contents .= '		<font color="red">' . $v['goods_seq'] . '</font>';
			$contents .= '		' . $v['option_seq'] . '<br/>';
			$contents .= '		' . $v['goods_code'] . '</td>';
			$contents .= '	<td align="left">' . $v['goods_name'] . ' ' . $v['option_name'] . '</td>';
			$contents .= '	<td align="right">' . number_format($v['ea']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['goods_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['freight_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['insurance_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['cif_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['duty_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['accessorial_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['krw_supply_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['ea']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['goods_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['freight_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['insurance_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['cif_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['duty_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['accessorial_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($v['whs']['krw_supply_price']) . '</td>';
			$contents .= '</tr>';

					$rno--;
				}
			}else{
			$contents .= '<tr class="list-row" style="height:50px;">';
			$contents .= '	<td align="center" colspan="19">검색된 발주입고현황이 없습니다.</td>';
			$contents .= '</tr>';
			}
			$contents .= '</tbody>';
			if($loop){
			$contents .= '<tr class="list-row" style="height:50px; background-color:#DBEEF4;">';
			$contents .= '	<td align="center" colspan="3">소계</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['ea']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['goods_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['freight_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['insurance_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['cif_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['duty_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['accessorial_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['order']['krw_supply_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['ea']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['goods_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['freight_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['insurance_price'], 2) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['cif_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['duty_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['accessorial_price']) . '</td>';
			$contents .= '	<td align="right">' . number_format($total['whs']['krw_supply_price']) . '</td>';
			$contents .= '</tr>';
			$contents .= '<tr class="list-row" style="height:50px; background-color:#DBEEF4;">';
			$contents .= '	<td align="center" colspan="3"></td>';
			$contents .= '	<td align="right"></td>';
				if	(($total['order']['goods_price'] + $total['order']['goods_price'] + $total['order']['goods_price']) > 0){
			$contents .= '	<td align="right" colspan="4">원/' . $_GET['sc_currency']. ' : ' . number_format(($total['order']['cif_price'] / ($total['order']['goods_price'] + $total['order']['freight_price'] + $total['order']['insurance_price'])), 2) . '</td>';
				}else{
			$contents .= '	<td align="right" colspan="4">원/' . $_GET['sc_currency']. ' : 0</td>';
				}
			$contents .= '	<td align="right"></td>';
			$contents .= '	<td align="right"></td>';
			$contents .= '	<td align="right"></td>';
			$contents .= '	<td align="right"></td>';
				if	(($total['whs']['goods_price'] + $total['whs']['goods_price'] + $total['whs']['goods_price']) > 0){
			$contents .= '	<td align="right" colspan="4">원/' . $_GET['sc_currency']. ' : ' . number_format(($total['whs']['cif_price'] / ($total['whs']['goods_price'] + $total['whs']['freight_price'] + $total['whs']['insurance_price'])), 2) . '</td>';
				}else{
			$contents .= '	<td align="right" colspan="4">원/' . $_GET['sc_currency']. ' : 0</td>';
				}
			$contents .= '	<td align="right"></td>';
			$contents .= '	<td align="right"></td>';
			$contents .= '	<td align="right"></td>';
			$contents .= '</tr>';
			}
			$contents .= '</table>';
		}elseif($excel_type == 'sordforwhs'){
			$this->load->model('scmmodel');

			// Default Setting
			$sc				= $_GET;
			$sc['page']		= 1;
			$sc['perpage']	= 60000;
			$sc['whs_type']	= ($_GET['whs_type'])	? $_GET['whs_type']	: 'Y';
			$sc['trader_type'] = ($_GET['trader_type'])? $_GET['trader_type'] : 'all';

			// 발주 내역 추출
			$sc['sql_type']	= 'order';
			$res = $this->scmmodel->get_sorder_statgoods($sc);

			// 입고 추출
			if($res['page']['totalcount']>0){
				$sc['sql_type']	= 'whs';
				$pre_sorder_seq = '';

				foreach($res['record'] as $data){
					// 입고 정보 추출
					unset($sc);
					$sc['sc_year']											= $_GET['sc_year'];
					$sc['sc_month']											= $_GET['sc_month'];
					$sc['sc_currency']										= $_GET['sc_currency'];
					$sc['whs_type']											= $_GET['whs_type'];
					$sc['sorder_seq']										= $data['sorder_seq'];
					$sc['goods_seq']										= $data['goods_seq'];
					$sc['option_type']										= $data['option_type'];
					$sc['option_seq']										= $data['option_seq'];
					$whs													= $this->scmmodel->get_sorder_goods_whs($sc);
					$whs													= $whs[0];
					$data['whs']											= $whs;

					$loop[$data['sorder_seq']]['data'][]					= $data;

					// 발주별 소계
					$loop[$data['sorder_seq']]['order']['ea']				+= $data['ea'];
					$loop[$data['sorder_seq']]['order']['supply_price']		+= $data['supply_price'];
					$loop[$data['sorder_seq']]['order']['goods_price']		+= $data['goods_price'];
					$loop[$data['sorder_seq']]['order']['freight_price']	+= $data['freight_price'];
					$loop[$data['sorder_seq']]['order']['insurance_price']	+= $data['insurance_price'];
					$loop[$data['sorder_seq']]['order']['cif_price']		+= $data['cif_price'];
					$loop[$data['sorder_seq']]['order']['duty_price']		+= $data['duty_price'];
					$loop[$data['sorder_seq']]['order']['accessorial_price']+= $data['accessorial_price'];
					$loop[$data['sorder_seq']]['order']['krw_supply_price']	+= $data['krw_supply_price'];
					$loop[$data['sorder_seq']]['order']['supply_tax']		+= $data['supply_tax'];
					$loop[$data['sorder_seq']]['whs']['ea']					+= $whs['ea'];
					$loop[$data['sorder_seq']]['whs']['supply_price']		+= $whs['supply_price'];
					$loop[$data['sorder_seq']]['whs']['goods_price']		+= $whs['goods_price'];
					$loop[$data['sorder_seq']]['whs']['freight_price']		+= $whs['freight_price'];
					$loop[$data['sorder_seq']]['whs']['insurance_price']	+= $whs['insurance_price'];
					$loop[$data['sorder_seq']]['whs']['cif_price']			+= $whs['cif_price'];
					$loop[$data['sorder_seq']]['whs']['duty_price']			+= $whs['duty_price'];
					$loop[$data['sorder_seq']]['whs']['accessorial_price']	+= $whs['accessorial_price'];
					$loop[$data['sorder_seq']]['whs']['krw_supply_price']	+= $whs['krw_supply_price'];
					$loop[$data['sorder_seq']]['whs']['supply_tax']			+= $whs['supply_tax'];
				}
			}
			$contents = '<table class="simpledata-table-style" width="100%" cellspacing="0" border="1">';
			$contents .= '<colgroup>';
			$contents .= '	<col width="50" />';
			$contents .= '	<col width="150" />';
			$contents .= '	<col width="150" />';
			$contents .= '	<col />';
			if	($currency == 'KRW'){
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="150" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="150" />';
			}else{
				$contents .= '	<col width="70" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="70" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
				$contents .= '	<col width="100" />';
			}
			$contents .= '</colgroup>';
			$contents .= '<thead class="lth">';
			if	($currency == 'KRW'){
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td rowspan="2">번호</td>';
				$contents .= '	<td rowspan="2">발주서번호<br/>거래처</td>';
				$contents .= '	<td rowspan="2">상품번호<br/>바코드</td>';
				$contents .= '	<td rowspan="2">상품/옵션</td>';
				$contents .= '	<td colspan="2">발주</td>';
				$contents .= '	<td colspan="2">입고</td>';
				$contents .= '</tr>';
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td class="toptitle">수량</td>';
				$contents .= '	<td class="toptitle rbd">금액</td>';
				$contents .= '	<td class="toptitle">수량</td>';
				$contents .= '	<td class="toptitle">금액</td>';
				$contents .= '</tr>';
			}else{
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td rowspan="4">번호</td>';
				$contents .= '	<td rowspan="4">발주서번호<br/>거래처</td>';
				$contents .= '	<td rowspan="4">상품번호<br/>바코드</td>';
				$contents .= '	<td rowspan="4">상품/옵션</td>';
				$contents .= '	<td colspan="8">발주</td>';
				$contents .= '	<td colspan="8">입고</td>';
				$contents .= '</tr>';
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td rowspan="3">수량</td>';
				$contents .= '	<td colspan="6">수입내역</td>';
				$contents .= '	<td rowspan="3">금액</td>';
				$contents .= '	<td rowspan="3">수량</td>';
				$contents .= '	<td colspan="6">수입내역</td>';
				$contents .= '	<td rowspan="3">금액</td>';
				$contents .= '</tr>';
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td colspan="3">외화</td>';
				$contents .= '	<td colspan="3">원화</td>';
				$contents .= '	<td colspan="3">외화</td>';
				$contents .= '	<td colspan="3">원화</td>';
				$contents .= '</tr>';
				$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
				$contents .= '	<td>상품</td>';
				$contents .= '	<td>운임</td>';
				$contents .= '	<td>보험</td>';
				$contents .= '	<td>CIF</td>';
				$contents .= '	<td>관세</td>';
				$contents .= '	<td>부대비용</td>';
				$contents .= '	<td>상품</td>';
				$contents .= '	<td>운임</td>';
				$contents .= '	<td>보험</td>';
				$contents .= '	<td>CIF</td>';
				$contents .= '	<td>관세</td>';
				$contents .= '	<td>부대비용</td>';
				$contents .= '</tr>';
			}
			$contents .= '</thead>';
			$contents .= '<tbody class="ltb scm-ajax-list">';
			if($loop){
				foreach($loop as $sorder_seq => $pv){
					if	($pv['data']){
						foreach($pv['data'] as $gb => $v){
			$contents .= '<tr class="list-row" style="height:45px;">';
			$contents .= '	<td align="center">'.$v['_rno'].'</td>';
							if( $k == 0 ){
			$contents .= '	<td align="left">';
			$contents .= '		<div>'.$v['sorder_code'].'</div>';
			$contents .= '		<div>'.$v['trader_name'].'('.$currency.')</div>';
			$contents .= '	</td>';
							}else{
			$contents .= '	<td align="left">&nbsp;</td>';
							}
			$contents .= '	<td align="left">';
			$contents .= '		<div><span style="color:red;">'.$v['goods_seq'].'</span>'.$v['option_seq'].'</div>';
							if( $v['goods_code'] ){
			$contents .= '		<div>'.$v['goods_code'].'</div>';
							}
			$contents .= '	</td>';
			$contents .= '	<td align="left">';
			$contents .= '		<div>'.$v['goods_name'].'</div>';
			$contents .= '		<div>'.$v['option_name'].'</div>';
			$contents .= '	</td>';
			$contents .= '	<td align="center">'.number_format($v['ea']).'</td>';
							if	($currency != 'KRW'){
			$contents .= '	<td class="right">'.number_format($v['goods_price'], 2).'</td>';
			$contents .= '	<td class="right">'.number_format($v['freight_price'], 2).'</td>';
			$contents .= '	<td class="right">'.number_format($v['insurance_price'], 2).'</td>';
			$contents .= '	<td class="right">'.number_format($v['cif_price']).'</td>';
			$contents .= '	<td class="right">'.number_format($v['duty_price']).'</td>';
			$contents .= '	<td class="right">'.number_format($v['accessorial_price']).'</td>';
							}
			$contents .= '	<td align="right">'.number_format($v['krw_supply_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['ea']).'</td>';
							if	($currency != 'KRW'){
			$contents .= '	<td align="right">'.number_format($v['whs']['goods_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['freight_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['insurance_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['cif_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['duty_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($v['whs']['accessorial_price']).'</td>';
							}
			$contents .= '	<td align="right">'.number_format($v['whs']['krw_supply_price']).'</td>';
			$contents .= '</tr>';
						}
					}
			$contents .= '<tr class="list-row" style="height:50px; background-color:#DBEEF4;" number="0">';
			$contents .= '	<td align="center" colspan="4">소계</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.ea']).'</td>';
					if	($currency != 'KRW'){
			$contents .= '	<td align="right">'.number_format($pv['order.goods_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.freight_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.insurance_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.cif_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.duty_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['order.accessorial_price']).'</td>';
					}
			$contents .= '	<td align="right">'.number_format($pv['order.krw_supply_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['ea']).'</td>';
					if	($currency != 'KRW'){
			$contents .= '	<td align="right">'.number_format($pv['whs']['goods_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['freight_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['insurance_price'], 2).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['cif_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['duty_price']).'</td>';
			$contents .= '	<td align="right">'.number_format($pv['whs']['accessorial_price']).'</td>';
					}
			$contents .= '	<td align="right">'.number_format($pv['whs']['krw_supply_price']).'</td>';
			$contents .= '</tr>';
				}
			}else{
			$contents .= '<tr class="list-row" style="height:50px;">';
			$contents .= '	<td align="center" colspan="13">검색된 발주대비입고현황이 없습니다.</td>';
			$contents .= '</tr>';
			}
			$contents .= '</tbody>';
			$contents .= '</table>';
		}elseif($excel_type == 'inven'){
			$this->load->model('scmmodel');
			$assign		= $this->scmmodel->ledger_controllers('inven');
			$loop		= $assign['loop'];
			$total		= $assign['total'];
			$unfinished	= $assign['unfinished'];

			$contents = '<table class="simpledata-table-style" width="100%" cellspacing="0" border="1">';
			$contents .= '<colgroup>';
			$contents .= '<col width="40" />';
			$contents .= '<col width="150" />';
			$contents .= '<col />';
			$contents .= '<col />';
			$contents .= '<col width="100" />';
			$contents .= '<col width="100" />';
			$contents .= '<col width="150" />';
			$contents .= '<col width="150" />';
			$contents .= '</colgroup>';
			$contents .= '<thead class="lth">';
			$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
			$contents .= '<td class="toptitle" rowspan="2">번호</td>';
			$contents .= '<td class="toptitle" rowspan="2">상품번호<br/>바코드</td>';
			$contents .= '<td class="toptitle min100" rowspan="2">상품</td>';
			$contents .= '<td class="toptitle min100 rbd" rowspan="2">옵션</td>';
			$contents .= '<td class="toptitle" colspan="4">';
			if	($_GET['sc_wh_seq'] > 0){
			$contents .= $_GET['sc_wh_name'];
			}else{
			$contents .= '전체창고';
			}
			$contents .= '</td>';
			$contents .= '</tr>';
			$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
			$contents .= '<td class="toptitle">로케이션</td>';
			$contents .= '<td class="toptitle">수량</td>';
			$contents .= '<td class="toptitle">단가</td>';
			$contents .= '<td class="toptitle">금액</td>';
			$contents .= '</tr>';
			$contents .= '</thead>';
			$contents .= '<tbody class="ltb scm-ajax-list">';
			if($loop){
				foreach($loop as $k => $v){
					$contents .= '<tr class="list-row" style="height:45px;">';
					$contents .= '<td class="center">' . ($k + 1) . '</td>';
					$contents .= '<td class="left">';
					$contents .= '<font color="red">' . $v['goods_seq'] . '</font>' . $v['option_seq'] . '<br/>' . $v['goods_code'];
					$contents .= '</td>';
					$contents .= '<td class="left">' . $v['goods_name'] . '</td>';
					$contents .= '<td class="left rbd">' . $v['option_name'] . '</td>';
					$contents .= '<td class="center" style="mso-number-format:\'\@\'">';
					if	($_GET['sc_wh_seq'] > 0){
					$contents .= $v['location_code'];
					}else{
					$contents .= '창고미선택';
					}
					$contents .= '</td>';
					$contents .= '<td class="right col_td_ea">' . number_format($v['cur_ea']) . '</td>';
					$contents .= '<td class="right col_td">';
					if	($unfinished){
					$contents .= '<span class="desc">미마감</span>';
					}else{
					$contents .= number_format($v['cur_supply_price'], 1);
					}
					$contents .= '</td>';
					$contents .= '<td class="right col_td_sum">';
					if	($unfinished){
					$contents .= '<span class="desc">미마감</span>';
					}else{
					$contents .= number_format($v['cur_price']);
					}
					$contents .= '</td>';
					$contents .= '</tr>';
				}
			}else{
				$contents .= '<tr class="list-row" style="height:50px;">';
				$contents .= '	<td align="center" colspan="13">검색된 재고자산명세서가 없습니다.</td>';
				$contents .= '</tr>';
			}
			$contents .= '</tbody>';
			if($loop){
				$contents .= '<tr class="list-row" style="height:50px; background-color:#DBEEF4;">';
				$contents .= '<td class="center bold rbd" colspan="5">합 계</td>';
				$contents .= '<td class="right col_td_ea">' . number_format($total['cur_ea']) . '</td>';
				$contents .= '<td class="right col_td">';
				if	($unfinished){
				$contents .= '<span class="desc">미마감</span>';
				}else{
				$contents .= number_format($total['cur_supply_price']);
				}
				$contents .= '</td>';
				$contents .= '<td class="right col_td_sum">';
				if	($unfinished){
				$contents .= '<span class="desc">미마감</span>';
				}else{
				$contents .= number_format($total['cur_price']);
				}
				$contents .= '</td>';
				$contents .= '</tr>';
			}
			$contents .= '</table>';
		}elseif($excel_type == 'ledger'){
			$this->load->model('scmmodel');
			
			$assign		= $this->scmmodel->ledger_controllers();
			$loop		= $assign['loop'];

			$contents = '<table class="simpledata-table-style" width="100%" cellspacing="0" border="1">';
			$contents .= '<thead class="lth">';
			$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
			$contents .= '	<td rowspan="2" width="30">번호</td>';
			$contents .= '	<td rowspan="2" width="30">상품번호</td>';
			$contents .= '	<td rowspan="2" width="130">상품</td>';
			$contents .= '	<td rowspan="2" width="130">옵션</td>	';
			$contents .= '	<td colspan="3">전기(월)재고</td>';
			$contents .= '	<td colspan="3">당기(월)입고</td>';
			$contents .= '	<td colspan="3">당기(월)출고</td>';
			$contents .= '	<td colspan="3">당기(월)재고</td>';
			$contents .= '</tr>';
			$contents .= '<tr style="text-align:center; font-weight:bold; background-color:#eee;">';
			$contents .= '	<td>수량</td>';
			$contents .= '	<td>단가</td>';
			$contents .= '	<td>금액</td>';
			$contents .= '	<td>수량</td>';
			$contents .= '	<td>단가</td>';
			$contents .= '	<td>금액</td>';
			$contents .= '	<td>수량</td>';
			$contents .= '	<td>단가</td>';
			$contents .= '	<td>금액</td>';
			$contents .= '	<td>수량</td>';
			$contents .= '	<td>단가</td>';
			$contents .= '	<td>금액</td>';
			$contents .= '</tr>';
			$contents .= '</thead>';
			$contents .= '<tbody class="ltb scm-ajax-list">';
			if($loop){
				foreach($loop as $k => $v){
			$contents .= '<tr class="list-row" style="height:45px;">';
			$contents .= '	<td align="center" width="30">'.($k+1).'</td>';
			$contents .= '	<td align="center" width="30">'.$v['goods_seq'].'</td>';
			$contents .= '	<td align="center" width="130">'.$v['goods_name'].'</td>';
			$contents .= '	<td align="center" width="130">'.$v['option_name'].'</td>';
			$contents .= '	<td align="center">'.number_format($v['pre_ea']).'</td>';
			$contents .= '	<td align="center" >'.number_format($v['pre_supply_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['pre_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['in_ea']).'</td>';
			$contents .= '	<td align="center">'.number_format($v['in_supply_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['in_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['out_ea']).'</td>';
			$contents .= '	<td align="center">'.number_format($v['out_supply_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['out_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['cur_ea']).'</td>';
			$contents .= '	<td align="center">'.number_format($v['cur_supply_price'],2).'</td>';
			$contents .= '	<td align="center">'.number_format($v['cur_price'],2).'</td>';
			$contents .= '</tr>';
				}
			}else{
			$contents .= '<tr class="list-row" style="height:50px;">';
			$contents .= '	<td align="center" colspan="17">검색된 재고수불부가 없습니다.</td>';
			$contents .= '</tr>';
			}
			$contents .= '</tbody>';
			$contents .= '</table>';
		}else{
			echo "[ERR : The wrong approach.]";
			echo "<script>alert('잘못된 접근입니다.');history.back();</script>";
			exit;
		}

		$contents = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.$contents;
		$fileName = $title."_".date('YmdHis').".xls";

		force_download($fileName, $contents);
	}

	public function divExcelDownload(){

		$this->load->helper('download');

		//$title = iconv("utf-8","euc-kr",$_POST['title']);
		$title		= $_POST['title'];

		$contents = $_POST['contents'];

		if($_POST['encode']){
			$contents	= base64_decode($contents);
			$contents	= rawurldecode($contents);
		}else{
			$contents	= $_POST['contents'];
		}

		$contents = str_replace("\t","",$contents);
		$contents = str_replace("\r","",$contents);
		$contents = str_replace("\n","",$contents);
		//$contents = str_replace("<!--","",$contents);
		//$contents = str_replace("-->","",$contents);
		$contents = str_replace("/<!--(.|\s)*?-->/","",$contents); //주석 이내 컨텐츠 까지 모두 삭제 19.04.24 kmj

		if(get_connet_protocol() != 'http://'){
			$contents = str_replace("http://",get_connet_protocol(),$contents);
		}

		// (숫자) 통계숫자 표시하기로 하여 주석처리함 leewh 2015-03-02
		// $contents = preg_replace("/\(([^\)]*)\)/","",$contents); // 값에 들어간 괄호를 제거하기 위한 코드 :: 상품명의 괄호를 지운다고 해서 임시 삭제 :: 2015-01-19 lwh

		//$contents = strip_tags($contents,"<table><tr><th><td><style>");
		$contents = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.$contents;

		$fileName = "{$title}_".date('YmdHis').".xls";

		force_download($fileName, $contents);
	}

	/* 가비아 출력 패널 (배너,팝업) */
	public function getGabiaPannel(){
		$this->load->helper('readurl');

		$code		= $_GET['code'];
		$version	= $_GET['version'];

		$revision = preg_replace("/[^0-9]/i","",@file_get_contents(ROOTPATH.'revision.txt'));

		$data = array(
			'service_code'	=> SERVICE_CODE,
			'hosting_code'	=> $this->config_system['service']['hosting_code'],
			'subDomain'		=> $this->config_system['subDomain'],
			'domain'		=> $this->config_system['domain'],
			'hostDomain'	=> $_SERVER['HTTP_HOST'],
			'shopSno'		=> $this->config_system['shopSno'],
			'expire_date'	=> $this->config_system['service']['expire_date'],
			'revision'		=> $revision,
			'setting_date'	=> $this->config_system['service']['setting_date'],
			'version'		=> $version
		);

		if($code == 'font_setting' || $code == 'main_right_banner'){
			$data['getdata'] = $_GET['getdata'];
		}

		$res = readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=getGabiaPannel&code={$code}",$data);

		if($this->demoFunctionChk){

			$res = preg_replace('/\"window.open*.+\)/','"servicedemoalert(\'use_f\')"',$res); //기능제한

			$res = str_replace('<a href="https://www.eximbay.com/kr/member/mjoin_partner.do?pcode=GABIACNS" class="btn_contract" target="_blank">온라인 신청하기</a>','<a href="#none" class="btn_contract" onclick="servicedemoalert(\'use_f\');">온라인 신청하기</a>',$res); //엑심페이 기능제한
		}

		$res = replace_connect_protocol($res);

		echo $res;
	}

	/* 가비아 전자결제 패널 출력 */
	public function getGabiaPannelPay(){
		$this->load->helper('readurl');

		$service	= $_GET['service'];
		$group		= $_GET['group'];
		$pageCode	= $_GET['pc'];

		if($group == "p"){
			$res = readurl(get_connet_protocol()."interface.firstmall.kr/static/revised.php?s=".$service."&g=".$group."&pc=".$pageCode."&sc=".SERVICE_CODE."&sn=".$this->config_system['shopSno']."&dn=".base64_encode($this->config_system['domain']));
		}else{
			$res = readurl(get_connet_protocol()."interface.firstmall.kr/static/revised.php?s=".$service."&g=".$group."&pc=".$pageCode);
		}

		$res = replace_connect_protocol($res);

		echo $res;
	}

	/* 가비아 메뉴얼 패널 */
	public function getGabiaManualPannel(){
		$this->load->helper('readurl');

		$code = $_GET['code'];

		$data = array(
			'service_code'	=> SERVICE_CODE,
			'hosting_code'	=> $this->config_system['service']['hosting_code'],
			'subDomain'		=> $this->config_system['subDomain'],
			'domain'		=> $this->config_system['domain'],
			'hostDomain'	=> $_SERVER['HTTP_HOST'],
			'shopSno'		=> $this->config_system['shopSno'],
			'expire_date'	=> $this->config_system['service']['expire_date'],
		);

		$res = readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=getGabiaManualPannel&code={$code}",$data);

		$res = replace_connect_protocol($res);

		echo $res;
	}

	/* 상단메뉴별 카운트 반환 */
	public function getIssueCount(){
		$this->load->helper('noticount');
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('eventmodel');
		$this->load->model('boardmodel');

		$issueCount = array();
		if( $this->managerInfo['manager_seq'] ){
			$wheres['shopSno']			= $this->config_system['shopSno'];
			$wheres['manager_seq']	= $this->managerInfo['manager_seq'];
			$wheres['codecd like']		= '%_priod_%';;
			$orderbys['idx'] 					= 'asc';
			$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
			foreach($query_auth->result_array() as $data){
				$codecd = str_replace('noti_count_priod_','',$data['codecd']);
				$cfg_priod[$codecd]	= $data['value'];
			}
		}
		if(!$cfg_priod['order']) $cfg_priod['order'] = "6개월";
		if(!$cfg_priod['board']) $cfg_priod['board'] = "6개월";
		if(!$cfg_priod['account']) $cfg_priod['account'] = "6개월";
		if(!$cfg_priod['warehousing']) $cfg_priod['warehousing'] = "6개월";

		## 처리해야할 주문수
		$start_date = str_to_priod_for_noti_count($cfg_priod['order']);
		$query = $this->ordermodel->get_issue_count($start_date);
		$result = $query->result_array();
		foreach($result as $row){
			$issueCount['order']['title'] = "처리해야할 주문";
			$issueCount['order']['total'] += $row['cnt'];
			$issueCount['order'][$row['type']] = $row['cnt'];
		}

		## 미처리 1:1문의, 상품문의
		$union_query = array();
		$start_date = str_to_priod_for_noti_count($cfg_priod['board']);
		$query = $this->boardmodel->get_issue_count($start_date);
		$result = $query->result_array();
		foreach($result as $row){
			$issueCount['board']['title'] = "미처리 1:1문의, 상품문의";
			$issueCount['board']['total'] += $row['cnt'];
			$issueCount['board']['mbqna'] += $row['cnt'];
		}

		## 정산대기수
		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$this->load->model('accountmodel');
			$start_date = str_to_priod_for_noti_count($cfg_priod['account']);
			$total_account = 0;
			$arr_account_period = array(1,2,4);
			foreach($arr_account_period as $period){
				$query = $this->accountmodel->get_issue_count($period,$start_date);
				$data = $query->row_array();
				$issueCount['account']['period'.$period] = $data['cnt'];
				$total_account += (int) $data['cnt'];
			}
			$issueCount['account']['total'] = $total_account;
		}

		## 입고대기,발주대기 수
		if( $this->scm_cfg['use'] == 'Y' ){ // 올인원일 경우
			$this->load->model('scmmodel');
			$start_date = str_to_priod_for_noti_count($cfg_priod['warehousing']);
			$sc['regist_date>='] = $start_date;
			$sc['sorder_status'] = '0';
			$query = $this->scmmodel->get_sorder_count($sc);
			$data_sorder = $query->row_array();
			unset($sc);
			$sc['regist_date>='] = $start_date;
			$sc['whs_status'] = '0';
			$query = $this->scmmodel->get_warehousing_count($sc);
			$data_warehousing = $query->row_array();
			$issueCount['scmwarehousing']['sorder'] = $data_sorder['cnt'];
			$issueCount['scmwarehousing']['warehousing'] = $data_warehousing['cnt'];
			$issueCount['scmwarehousing']['total'] = $data_sorder['cnt'] + $data_warehousing['cnt'];
		}

		## 신규회원
		$startDate	= date('Y-m-d') . " 00:00:00";
		$endDate	= date('Y-m-d') . " 23:59:59";
		$query = $this->membermodel->get_issue_count($startDate,$endDate);
		$result = $query->result_array();
		$issueCount['member']['title']		= "오늘 신규회원";
		$issueCount['member']['total']		= $result[0]['cnt'];
		$issueCount['member']['member']		= $result[0]['cnt'];

		### 진행중인 이벤트
		$query = $this->eventmodel->get_issue_count();
		$result = $query->result_array();
		foreach($result as $row){
			$issueCount['coupon']['title']		= "진행중인 이벤트";
			$issueCount['coupon']['total']		+= $row['cnt'];
			$issueCount['coupon'][$row['type']]	= $row['cnt'];
		}

		### 설정 일반정보 여부
		$data_basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		if(!$this->config_system['domain']){
			$issueCount['setting']['basic'] = 1;
		}
		if(!$data_basic['shopName']){
			$issueCount['setting']['basic'] = 1;
		}

		### 설정 전자결제 여부
		$data_pg = config_load($this->config_system['pgCompany']);
		if( count($data_pg['payment']) == 0 ){
			if($this->config_system['not_use_kakao']=='y')
				$issueCount['setting']['pg'] = 1;
		}

		### 설정 무통장 여부
		$data_bank = config_load('bank');
		if($data_bank)foreach($data_bank as $bank){
			if($bank['accountUse'] == 'y') $bank_cnt++;
		}
		if(!$bank_cnt) $issueCount['setting']['bank'] = 1;

		### 설정 택배/배송비
		$arr = array('delivery','quick','direct');
		foreach($arr as $code){
			$scode = "shipping".$code;
			$data = config_load($scode);
		 	if($data['useYn']=='y') $shipping_cnt++;
		}

		if(!$shipping_cnt) $issueCount['setting']['shipping'] = 1;

		## 설정 전체 값 체크
		foreach($issueCount['setting'] as $setVal){
			if($setVal)	$issueCount['setting']['total'] = 1;
		}

		// 오픈마켓 - 주문수집/등록
		$this->load->model('connectormodel');
		$market = $this->connectormodel->getUseAllMarkets();
		$issueCount['market_connector']['title'] = "처리해야할 오픈 마켓 주문";

		// 오픈마켓 - 주문수집/등록
		unset($params);
		$params['withTotalCount']	= true;
		$params['hasFmOrderSeq']	= "N";
		$params['status']			= array("ORD10","ORD20");
		$params['market']			= array_keys($market);
		$params['hasCanceled']		= "N";
		$response		= $this->connectormodel->getMarketOrderList($params, 'forViewList');
		$issueCount['market_connector']['regist'] = $response['totalCount'];

		// 오픈마켓 - 취소관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "CAN";
		$params['status']	= "CAN00";
		$params['market']			= array_keys($market);
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['cancel'] = $response['totalCount'];

		// 오픈마켓 - 반품관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "RTN";
		$params['is_fm_order']	= "Y";		// 등록된 주문
		$params['hasFmClaimCode']	= "N";	// 등록되지 않은 클레임
		$params['status']	= "RTN00";
		$params['market']			= array_keys($market);
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['return'] = $response['totalCount'];

		// 오픈마켓 - 교환관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "EXC";
		$params['is_fm_order']	= "Y";		// 등록된 주문
		$params['hasFmClaimCode']	= "N";	// 등록되지 않은 클레임
		$params['status']	= "EXC00";
		$params['market']			= array_keys($market);
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['exchange'] = $response['totalCount'];


		// 오픈마켓 - 문의
		unset($params);
		$params['withTotalCount']	= true;
		$params['market_cs_yn']	= "Y";		// 답변 가능 문의
		$params['fm_answer_yn']	= "N";	// 답변 안한 문의
		$params['market']			= array_keys($market);
		$response		= $this->connectormodel->getMarketQnaList($params, 'forViewList');
		$issueCount['market_connector']['qna'] = $response['totalCount'];

		// 오픈마켓 - 총 갯수
		foreach($issueCount['market_connector'] as $k=>$v){
			$issueCount['market_connector']['total'] += $v;
		}


		echo json_encode($issueCount);
	}

	public function ajax_volume_check(){
		return $this->volume_check();
	}

	public function category2json(){
		$this->load->model('categorymodel');
		$result = array();
		$code 	= $this->input->get('categoryCode');
		$result = $this->categorymodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function brand2json(){
		$this->load->model('brandmodel');
		$result = array();
		$code 	= $this->input->get('categoryCode');
		$result = $this->brandmodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function location2json(){
		$this->load->model('locationmodel');
		$result = array();
		$code 	= $this->input->get('locationCode');
		$result = $this->locationmodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function event2json(){
		$result 	= array();
		$event_seq 	= $this->input->get('event_seq');
		$query 		= $this->db->query("select * from fm_event_benefits where event_seq=? order by event_benefits_seq asc",$event_seq);
		$result 	= $query->result_array();
		foreach($result as $i=>$row){
			$sale_text	= ($row['target_sale'] == '2') ? "원" : '%';
			$result[$i]['title'] = "[경우".($i+1)."] 할인".number_format($row['event_sale']).$sale_text.",적립".number_format($row['event_reserve'])."%";
		}
		echo json_encode($result);
	}


	/* QR 코드 안내*/
	public function qrcode_guide(){
		$this->template->assign(array('key'=>$this->input->get('key')));
		$this->template->assign(array('value'=>$this->input->get('value')));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 혜택안내 */
	public function benifit(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 상단메뉴 스타일 변경 */
	public function setManagerIconView(){
		$this->db->query("update fm_manager set gnb_icon_view=? where manager_seq=?",array($this->input->get('val'),$this->managerInfo['manager_seq']));
		$this->managerInfo['gnb_icon_view'] = $this->input->get('val');
		$this->session->set_userdata(array('manager'=>$this->managerInfo));
	}

	/* 메뉴얼 레이어 출력 */
	public function showSimpleManual(){
		$this->load->helper('readurl');

		$section = $this->input->get('section');

		$data = array(
			'service_code'	=> SERVICE_CODE,
			'hosting_code'	=> $this->config_system['service']['hosting_code'],
			'subDomain'		=> $this->config_system['subDomain'],
			'domain'		=> $this->config_system['domain'],
			'hostDomain'	=> $_SERVER['HTTP_HOST'],
			'shopSno'		=> $this->config_system['shopSno'],
			'expire_date'	=> $this->config_system['service']['expire_date'],
		);

		$res = readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=getGabiaSimpleManualPannel&section={$section}",$data);

		echo $res;

	}

	public function goods_sort_popup(){

		### 이미지 사이즈
		$goodsImageSize = config_load('goodsImageSize');

		$this->template->assign('goodsImageSize',$goodsImageSize);
		$this->template->assign('r_img_size',$r_img_size);
		$this->template->assign($this->input->get());

		$this->template->define(array('tpl'=>$this->skin.'/common/_goods_sort_popup.html'));
		$this->template->print_("tpl");
	}

	public function goods_sort_popup_process(){

		$this->load->model('goodsmodel');

		$aParamsPost = $this->input->post();
		// 정렬 이동 방식(single: 단일 / multi: 일괄)
		$mode = $aParamsPost['mode'];
		// 정렬 종류(category / brand / location )
		$kind = $aParamsPost['kind'];
		// 정렬 종류의 고유 코드 번호
		$code = $aParamsPost['code'];
		// 페이징 처리를 위한 페이징 번호
		$page = $aParamsPost['page'] ? $aParamsPost['page'] : 1;
		// 가로에 존재하는 상품 개수
		$count_w = $aParamsPost['count_w'] ?  $aParamsPost['count_w'] : 4;
		// 세로에 존재하는 상품 개수
		$count_h = $aParamsPost['count_h'] ? $aParamsPost['count_h'] : 4;
		// 한페이지 전체 상품 수
		$perpage = $count_w * $count_h;
		// 정렬 처리 테이블 명
		$table = "fm_{$kind}_link";
		//모바일 세팅 여부
		$mobile_setting	 = $aParamsPost['mobile_setting'];
		// 상품 번호
		$_list_goodsSeq = $aParamsPost['goods_seqs'];
		// 정렬할 상품의 정렬 번호
		$_list_goodsSort = $aParamsPost['goods_sorts'];
		$totalpage = $aParamsPost['totalpage'];
		$totalcount = $aParamsPost['totalcount'];
		// 모바일 페이지 세팅 값인지 판단하기 위한 변수
		$mobile_flag = false;
		$sort_var = 'sort';
		if($mobile_setting == 'y'){
			$mobile_flag = true;
			$sort_var = 'mobile_sort';
		}

		$code_name = 'category_code';
		if($kind == 'location') $code_name = 'location_code';

		// 단일 선택으로 순서 변경
		if($mode=='single'){
			/* 동일 정렬 순서를 갖는 경우가 존재하는지 확인 by hed
			 * - 최소 정렬 값의 동일 정렬 값이 있는 경우
			 *   => 동일 정렬 값 재정렬
			 * - 최대 정렬 값의 동일 정렬 값이 있는 경우
			 *   => 동일 정렬 값 재정렬
			 * - 최소 정렬 값과 최대 정렬 값의 차이가 정렬 갯수보다 작은 경우(큰 경우일 경우 문제되지 않음)
			 *    => 최대 정렬 값 보다 큰 정렬 값을 차이만큼 증가(공간 확보)
			 */
			unset($refineSortData);
			$refineSortData							= array();
			$refineSortData['code_name']			= $code_name;
			$refineSortData['code']					= $code;
			$refineSortData['sort_var']				= $sort_var;
			$refineSortData['table']				= $table;
			
			// 현재 페이지에서 갖을 수 있는 최소 값 확인
			$moved_goods_seq = 0;
			foreach($_list_goodsSort as $k=>$tmpSort){
				if(min($_list_goodsSort) == $tmpSort){
					if($moved_goods_seq == 0){
						$moved_goods_seq = $_list_goodsSeq[$k];
					}elseif($moved_goods_seq < $_list_goodsSeq[$k]){
						$moved_goods_seq = $_list_goodsSeq[$k];
					}
				}
			}
			$refineSortData['moved_sort']			= min($_list_goodsSort);
			$refineSortData['moved_goods_seq']		= $moved_goods_seq;
			$refine_min_sort						= $this->refine_sort($refineSortData, 'min');

			// 현재 페이지에서 갖을 수 있는 최대 값 확인
			$moved_goods_seq = 0;
			foreach($_list_goodsSort as $k=>$tmpSort){
				if(max($_list_goodsSort) == $tmpSort){
					if($moved_goods_seq == 0){
						$moved_goods_seq = $_list_goodsSeq[$k];
					}elseif($moved_goods_seq > $_list_goodsSeq[$k]){
						$moved_goods_seq = $_list_goodsSeq[$k];
					}
				}
			}
			$refineSortData['moved_sort']			= max($_list_goodsSort);
			$refineSortData['moved_goods_seq']		= $moved_goods_seq;	
			$refine_max_sort						= $this->refine_sort($refineSortData, 'max');
			unset($refineSortData);
			
			// 최소 정렬 값과 최대 정렬 값의 차이가 정렬 갯수보다 작은 경우
			if($refine_max_sort < $refine_min_sort){
				$tmp_refine_max_sort = $refine_max_sort;
				$refine_max_sort = $refine_min_sort;
				$refine_min_sort = $tmp_refine_max_sort;
			}
			if(!$refine_max_sort || $refine_max_sort == null) $refine_max_sort = 0;

			$check_max_min = (count($_list_goodsSort)-1) - ($refine_max_sort-$refine_min_sort);
			if($check_max_min > 0){
				// 공간 확보
				$this->db->where($code_name, $code);
				$this->db->where($sort_var." >", $refine_max_sort);
				$this->db->set($sort_var, $sort_var.' + '.$check_max_min, false);
				$this->db->update($table);
			}
			
			$min_sort = min($_list_goodsSort);

			foreach($_list_goodsSeq as $k=>$goods_seq){
				$sort = $min_sort + $k;
				$this->db->where($code_name,$code);
				$this->db->where("goods_seq",$goods_seq);
				$this->db->update($table,array($sort_var=>$sort));
			}
		}

		// 여러 상품 선택해서 순서변경
		if($mode=='multi' ){

			$sort_target_page		= $_POST['sort_target_page'];		//이동 시킬 페이지
			$sort_target_location	= $_POST['sort_target_location'];	//이동 시킬 위치(맨처음, 맨 끝)

			if($sort_target_page && $sort_target_location){

				$limit_s = (($sort_target_page-1)*$perpage);	// 이동시킨 페이지의 시작 번호

				# 이동하는 페이지가 현재페이지보다 높고 마지막 페이지가 아닐 때
				if($sort_target_page > $page){
					if($sort_target_page != $totalpage){
						$wheres = " and g.goods_seq not in(".implode(",",$_list_goodsSeq).")";
					}else{
						$pagemode = "last";
					}
				}elseif($sort_target_page == $page){
				# 이동하는 페이지가 현재 페이지와 동일 할 경우
					if($sort_target_location == "first"){
						$wheres = " and g.goods_seq not in(".implode(",",$_list_goodsSeq).")";
					}
				}


				//이동 포인트 뽑아오기 1.
				$sql = "
					select
							min(k.{$sort_var}) as min_sort,
							max(k.{$sort_var}) as max_sort
					from (
						select
							l.*
						from
							{$table} as l
							inner join fm_goods g on (l.goods_seq=g.goods_seq and g.goods_view='look' and g.goods_type='goods')
							inner join fm_goods_option o on (g.goods_seq=o.goods_seq and o.default_option='y')
							inner join fm_provider as p on g.provider_seq=p.provider_seq and p.provider_status='Y'
						where
							l.{$code_name}=?
							and g.provider_status ='1'
							and (g.goods_view = 'look'
							or (
								g.display_terms = 'AUTO'
								and g.display_terms_begin <= '".date("Y-m-d")."'
								and g.display_terms_end >= '".date("Y-m-d")."')
							)
							".$wheres."
						group by g.goods_seq
						order by l.{$sort_var} asc, g.goods_seq desc
						limit ".$limit_s.",".$perpage."
					) as k ";
				$query = $this->db->query($sql,$code);
				$row = $query->result_array();

				$min_sort		= $row[0]['min_sort'];
				$max_sort		= $row[0]['max_sort'];

				$move_goods_cnt = count($_list_goodsSeq);	//이동시킬 상품 갯수

				# 이동 포인트 뽑아오기 2.(최종)
				$search_sql	= "select min(k.{$sort_var}) as last_sort from (
							select
								l.*
							from
								{$table} as l
								inner join fm_goods g on (l.goods_seq=g.goods_seq and g.goods_view='look' and g.goods_type='goods')
								inner join fm_goods_option o on (g.goods_seq=o.goods_seq and o.default_option='y')
								inner join fm_provider as p on g.provider_seq=p.provider_seq and p.provider_status='Y'
							where
								l.{$code_name}=?
								and g.provider_status ='1'
								and (g.goods_view = 'look'
								or (
									g.display_terms = 'AUTO'
									and g.display_terms_begin <= '".date("Y-m-d")."'
									and g.display_terms_end >= '".date("Y-m-d")."')
								)
								and l.{$code_name}=? and l.{$sort_var} <= ?
							group by g.goods_seq
							order by l.{$sort_var} desc, g.goods_seq desc limit ?
						) as k";

				## 맨 처음으로 보내기
				#  이동 페이지의 맨 처음 sort 번호 찾기
				#  해당 sort 번호 이후부터 이동 상품 갯수 만큼 + 시키기
				if($sort_target_location == "first"){

					$last_sort	= $min_sort;

					//전체페이지 == 타겟페이지
					if($totalpage == $sort_target_page){
						$now_page_count		= ($sort_target_page-1) * $perpage; // ex) 2  * 16
						$remain_page_count	= ($totalcount - $now_page_count);	//타켓페이지 이후 남은 상품 갯수
						$last_goods_cnt		= $remain_page_count - $move_goods_cnt;
						if($last_goods_cnt <= 0){
							$last_sort		= $max_sort + 1;
							$last_goods_cnt = 0;
						}
					}

					if($last_goods_cnt){
						# 최종 이동포인트 뽑아오기
						$query	= $this->db->query($search_sql,array($code,$code,$max_sort,$last_goods_cnt));
						$row	= $query->result_array();
						$last_sort = $row[0]['last_sort'];
					}

					if($last_sort){
						$sql		= "update {$table} set {$sort_var} = {$sort_var} + {$move_goods_cnt}
										where {$code_name}='".$code."' and {$sort_var} >= ".$last_sort."";
						$query	= $this->db->query($sql);
					}


				}

				## 맨 끝으로 보내기
				if($sort_target_location == "last"){

					## 1. 이동 point 찾기..
					# 마지막 페이지는 아니나 이동하는 상품 갯수가 마지막 페이지 갯수보다 많을 때 예외 처리.
					$now_page_count		= $sort_target_page * $perpage; // ex) 2  * 16
					$remain_page_count	= ($totalcount - $now_page_count);	//타켓페이지 이후 남은 상품 갯수

					# 이동 상품 갯수 > (전체상품갯수 - 이동페이지까지의 상품갯수(이동상품갯수 제외한))
					if(count($_list_goodsSeq) > $remain_page_count){
						$last_goods_cnt = $totalcount - $now_page_count;
					}else{
						$last_goods_cnt = $move_goods_cnt;
					}

					# 마지막 페이지로 이동시 예외처리
					if($pagemode != "last"){

						//현재 페이지 == 타켓페이지
						if($page == $sort_target_page){
							$last_sort = $max_sort + 1;
						}else{
							# 최종 이동포인트 뽑아오기
							//debug(array($code,$max_sort,$last_goods_cnt));
							$query	= $this->db->query($search_sql,array($code,$code,$max_sort,$last_goods_cnt));
							$row	= $query->result_array();
							$last_sort = $row[0]['last_sort'];
						}

						$sql = "update {$table} set {$sort_var} = {$sort_var} + {$move_goods_cnt} where {$code_name}='".$code."' and {$sort_var} >= ".$last_sort."";
						$query	= $this->db->query($sql);
					}else{
						$last_sort = $max_sort + 1;
					}
				}

				$newSort = 0;
				foreach($_list_goodsSeq as $_goods_seq){
					$sort = $last_sort + $newSort;
					$sql = "update {$table} set {$sort_var} = ".$sort." where {$code_name}='".$code."' and goods_seq='".$_goods_seq."'";
					$query	= $this->db->query($sql);
					$newSort++;
				}

			}
		}

		$sc=array();

		switch($kind){
			case 'category': $sc['category'] = $code; break;
			case 'brand': $sc['brand'] = $code; break;
			case 'location': $sc['location'] = $code; break;
		}

		$sc['admin_category']	= true;
		$sc['sort']				= 'popular';
		$sc['page']				= $page;
		$sc['perpage']			= $perpage;
		$sc['image_size']		= 'thumbCart';
		$sc['m_list_use']		= $mobile_setting;
		$list					= $this->goodsmodel->goods_list($sc);

		foreach($list['record'] as $k=>$record){
			$list['record'][$k]['goods_name_chars'] = htmlspecialchars(str_replace(array("'",'"'),'',strip_tags($record['goods_name'])));

			if($kind == 'brand'){
				$record[$sort_var] = $record['bl_'.$sort_var];
			}elseif($kind == 'location'){
				$record[$sort_var] = $record['ll_'.$sort_var];
			}
			$list['record'][$k]['sort'] = $record[$sort_var];

			if($record['goods_status'] == 'normal'){
				$list['record'][$k]['goods_status_char'] = "<span style='color:#1E1EF0'>정상</span>";
			}else if($record['goods_status'] == 'runout'){
				$list['record'][$k]['goods_status_char'] = "<span style='color:#F01E1E'>품절</span>";
			}else if($record['goods_status'] == 'unsold'){
				$list['record'][$k]['goods_status_char'] = "<span style='color:#F01E1E'>판매중지</span>";
			}else if($record['goods_status'] == 'purchasing'){
				$list['record'][$k]['goods_status_char'] = "<span style='color:#F01E1E'>재고확보중</span>";
			}else{
				$list['record'][$k]['goods_status_char'] = "-";
			}
		}

		// 전체 페이지 갯수 재계산
		if($list['page']['totalcount'] > 0 && $sc['perpage'] > 0) {
			$list['page']['totalpage']			= ceil($list['page']['totalcount'] / $sc['perpage']);
		}

		echo json_encode($list);
	}

	public function manager_alert_history(){
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function total_menu(){
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model("admin_menu");

		// 물류관리 미사용 시 물류관리 메뉴 제거
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	(!serviceLimit('S6016') || $this->scm_cfg['use'] != 'Y'){
			$this->admin_menu->except_scm_menu();
		}

		// 구정산 마이그레이션 안했으면 메뉴 제거
		$accountall_setting = config_load('accountall_setting');
		if (!$accountall_setting['old_accountall_display']) {
			$this->admin_menu->except_old_accountall_menu();
		}

		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('adminMenu' => $this->admin_menu->arr_menu));
		$this->template->print_("tpl");
	}

	public function manager_alert_history_ajax_lsit(){
		$page = !empty($_POST['page']) ? $_POST['page'] : 1;
		$keyword = !empty($_POST['keyword']) ? $_POST['keyword'] : '';
		$sdate = !empty($_POST['sdate']) ? $_POST['sdate'] : '';
		$edate = !empty($_POST['edate']) ? $_POST['edate'] : '';

		$wheres = array();
		if($keyword){
			$wheres[] = "b.manager_id like ?";
			$bind[]	=	'%'.$keyword.'%';
		}
		if($sdate){
			$wheres[] = "a.regist_date >= ?";
			$bind[]	=	$sdate.' 00:00:00';
		}
		if($edate){
			$wheres[] = "a.regist_date <= ?";
			$bind[]	=	$edate.' 23:59:59';
		}

		$sql = "select a.*,b.mname,b.manager_id from fm_manager_action_history a
		left join fm_manager b on a.manager_seq=b.manager_seq ";

		if($wheres){
			$sql .= " where ".implode(" and ",$wheres);
		}

		$sql .= " order by a.regist_date desc";
		$result = select_page(6,$page,10,$sql,$bind);

		echo json_encode($result);
	}

	public function scm_select_warehouse(){
		$box		= $_GET['box'];
		if(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$result['scm_cfg']		= $this->scm_cfg;
		$result['scmOptions']	= array('boxName'	=> $box);
		$this->template->assign( $result );
		$this->template->define( array('tpl'	=> $this->template_path()) );
		$this->template->print_("tpl");
	}
	
	/* 동일 sort가 있을 시 동일 sort의 문제를 해결하기 위해 기존 정렬과 같은 정렬 값을 모두 재정리
	 * - $moved_sort 보다 큰 sort를 동일 $moved_sort 의 갯수 -1만큼 증가 (공간 확보)
	 * - 동일 moved_sort를 첫 행을 제외한 goods_seq desc 기준으로 moved_sort + 1씩 순차 증가 (확보된 공간에 순차 저장)
	 * 재정렬이 끝난 후 $moved_sort 재정의
	 */
	protected function refine_sort($data = array(), $type = ''){
		$code_name = $data['code_name'];
		$code = $data['code'];
		$sort_var = $data['sort_var'];
		$table = $data['table'];
		$moved_sort = $data['moved_sort'];
		$moved_goods_seq = $data['moved_goods_seq'];

		// 동일 정렬 값이 있는지 확인
		$this->db->where($code_name, $code);
		$this->db->where('goods_seq', $moved_goods_seq);
		$this->db->from($table);
		$this->db->select('*');
		$this->db->limit(1);
		$query = $this->db->get();
		$check_same = $query->result_array();

		// null 인 경우 에러발생, 기본값 0 으로 세팅
		$check_same_sort = empty($check_same[0][$sort_var]) ? 0 : $check_same[0][$sort_var];

		$this->db->where($code_name, $code);
		$this->db->where($sort_var, $check_same_sort);
		$this->db->from($table);
		$this->db->select('*');
		$this->db->order_by($sort_var, 'ASC');
		$this->db->order_by('goods_seq', 'DESC');
		$query = $this->db->get();
		$same_sort = $query->result_array();
		$same_sort_cnt = count($same_sort);

		if ($same_sort_cnt > 1) {
			// 공간확보
			$this->db->where($code_name, $code);
			$this->db->where($sort_var . ' >', $check_same_sort);
			$this->db->set($sort_var, $sort_var . ' + ' . ($same_sort_cnt - 1), false);
			$this->db->update($table);

			// 확보된 공간에 순차 저장
			unset($make_step_data);
			$make_step_data = [];
			$make_step_sort = $check_same_sort;
			foreach ($same_sort as $same_sort_idx => $row) {
				$make_step_data = [$sort_var => $make_step_sort];
				$this->db->where(['goods_seq' => $row['goods_seq'], 'category_code' => $code]);
				$this->db->update($table, $make_step_data);
				$make_step_sort++;
			}
		}

		$return_sort = $check_same_sort;
		if ($type == 'min') {
		} elseif ($type == 'max') {
			// 재정렬이 끝난 후 $moved_sort 재정의
			$this->db->where($code_name, $code);
			$this->db->where('goods_seq', $moved_goods_seq);
			$this->db->from($table);
			$this->db->select('*');
			$this->db->limit(1);
			$query = $this->db->get();
			$refine = $query->result_array();
			$return_sort = $refine[0][$sort_var];
		}

		return $return_sort;
	}

	// LNB 버튼 설정 저장
	public function saveLnbConf()
	{
		$this->load->library('bookmarklibrary');
		$seq = $this->bookmarklibrary->setLnbConf($this->input->post());
		echo json_encode($seq);
	}

	// 즐겨찾기 추가/삭제
	public function bookmark()
	{
		$this->load->library('bookmarklibrary');
		$this->bookmarklibrary->setBookmark($this->input->post());
	}

	// 즐겨찾기 메뉴 리스트
	public function getBookmarkList()
	{
		$this->load->library('bookmarklibrary');
		$result = $this->bookmarklibrary->getBookmark();
		echo json_encode($result, JSON_UNESCAPED_UNICODE);
	}

}

/* End of file coupon.php */
/* Location: ./app/controllers/admin/coupon.php */