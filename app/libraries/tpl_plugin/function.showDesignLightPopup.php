<?php
function showDesignLightPopup($popup_seq)
{
	$CI =& get_instance();
	$CI->load->helper('javascript');
	$CI->load->model('designmodel');

	// light 형이 아닐 경우 블락
	if($CI->config_system['operation_type'] != 'light') return;

	$template_path	= $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;
	$skin			= $CI->designWorkingSkin;

	$popup_key = "designPopup{$popup_seq}";

	$cache_item_id = sprintf('design_popup_%s', $popup_seq);
	$data = cache_load($cache_item_id);
	if ($data === false) {
		$query = $CI->designmodel->get_design_popup($popup_seq);
		$data = $query->row_array();
		cache_save($cache_item_id, $data);
	}

	$sImagePath	= '/data/popup/' . $data['image'];
	if(preg_match('/http/', $data['image'])){
		$sImagePath	= $data['image'];
	}

	if(!$data) return;
	if($data['popup_condition']){
		$CI->load->model('goodsmodel');
		$goods_seq = $_GET['no'];
		$code = $_GET['code'];
		$popup_condition = unserialize($data['popup_condition']);

		switch($_SERVER['REDIRECT_URL']){
			case "/goods/view" :
				switch($popup_condition['view']){
					case "category":
						$goods_category = $CI->goodsmodel->get_goods_category($goods_seq);
						if(!$goods_category) return;
						$category_view = array();
						foreach($goods_category as $category) $category_view[] = $category['category_code'];
						if(sizeOf(array_intersect($category_view,$popup_condition['issueCategoryViewCode']))<1) return;
					break;
					case "brand":
						$goods_brand = $CI->goodsmodel->get_goods_brand($goods_seq);
						if(!$goods_brand) return;
						$brand_view = array();
						foreach($goods_brand as $brand) $brand_view[] = $brand['category_code'];
						if(sizeOf(array_intersect($brand_view,$popup_condition['issueBrandViewCode']))<1) return;
					break;
					case "goods":
						if(!in_array($goods_seq,$popup_condition['issueGoods'])) return;
					break;
				}
			break;
			case "/goods/catalog" :
				if($popup_condition['category'] != 'all' && !in_array($code,$popup_condition['issueCategoryCode'])) return;
			break;
			case "/goods/brand" :
				if($popup_condition['brand'] != 'all' && !in_array($code,$popup_condition['issueBrandCode'])) return;
			break;
			case "/goods/location" :
				if($popup_condition['location'] != 'all' && !in_array($code,$popup_condition['issueLocationCode'])) return;
			break;
		}
	}


	$now_time = time();
	$flag = $data['status']=='show' || ($data['status']=='period' && $now_time >= strtotime($data['period_s']) && $now_time <= strtotime($data['period_e'])) ? true : false;
	if($CI->input->cookie($popup_key)) {
		if($CI->input->cookie($popup_key)=='1' || time()-$CI->input->cookie($popup_key) < 86400){
			$flag = false;
		}
	}

	if($CI->layout->is_design_mode() && $CI->input->cookie('designEditMode')) {
		if($data['status']=='show' || ($data['status']=='period' && $now_time <= strtotime($data['period_e']))){
			$flag = true;
		}
	}

	if($data['style']=='band' && $flag){
		$ilineCss = "";
		if($data['band_background_image']){
			$ilineCss .= "background-image:url(/data/popup/{$data['band_background_image']});";
			if($data['band_background_image_repeat']) $ilineCss .= "background-repeat:{$data['band_background_image_repeat']};";
			if($data['band_background_image_position']) $ilineCss .= "background-position:{$data['band_background_image_position']};";
		}elseif($data['band_background_color']){
			$ilineCss .= "background-color:{$data['band_background_color']};";
		}

		$html .= "<div class='designPopupBand hide' popupStyle='band' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='{$ilineCss}'>";
		$html .= "<div class='designPopupBody'>";

		if($data['link']){
			$target = "_self";
			if($data['open'] == 1) $target = "_blank";
			$html .= "<a href='{$data['link']}' target='{$target}'>";
		}


		$sImagePath =
		$html .= "<img src='".$sImagePath."' />";
		if($data['link']) $html .= "</a>";

		$html .= "</div>";
		$html .= "<div class='designPopupClose'><img src='/data/icon/common/etc/btn_tbanner_close.png' alt='banner close' /></div>";
		//$html .= "<div class='designPopupClose absolute hand' style='right:{$data['band_right']}px;top:{$data['band_top']}px;'><img src='/data/icon/common/etc/band_btn_close.gif' /></div>";
		$html .= "</div>";

		$openBtn = '';
		if($flag) $openBtn = ' hide';

		$html .= "<div class='designPopupBandBtn absolute center hand{$openBtn}' style='top:0px; left:50%; width:200px; margin-left:-100px;z-index:12'><img src='/data/icon/common/etc/band_btn_open.gif' /></div>";
		echo $html;
	}
	if($flag){
		$cache_item_id = sprintf('design_popup_%s_html', $popup_seq);
		$html = cache_load($cache_item_id);
		if ($html === false) {
			//  2019-01-09 lwh
			$banner_seq	= $data['popup_banner_seq'];
			$query = $CI->designmodel->get_design_popup_banner($banner_seq);
			$banner	= $query->row_array();
			$query = $CI->designmodel->get_design_popup_banner_item($banner_seq);
			$banner_item = $query->result_array();

			//
			if(!$banner && $data['contents_type'] == 'slider') return;

			$html = "";
			if($data['style']=='layer'){

				if($banner['navigation_paging_style']=='custom'){

					@list($customImageWidth, $customImageHeight) = @getimagesize(ROOTPATH."data/popup/{$banner_item[0]['tab_image_inactive']}");
					$banner['navigation_paging_height'] = $customImageHeight;
				}

				$popupHeight	= $banner['height'];
				$popupWidth		= $banner['image_width'];

				if($banner['image_side_margin'] > 0) $popupWidth = $popupWidth + ($banner['image_side_margin']*2);

				if( $data['contents_type'] == 'text' ) {
					$html .= "<div class='designPopup popup_slider sliderC' popupStyle='layer' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='left:{$data['loc_left']}px; top:{$data['loc_top']}px; max-width:{$data['width']}px; '>";
				} elseif( $data['contents_type'] == 'image' ) {
					$html .= "<div class='designPopup' popupStyle='layer' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='left:{$data['loc_left']}px; top:{$data['loc_top']}px; max-width:{$popupWidth}px; '>";
				} else {
					$html .= "<div class='designPopup popup_slider sliderC' popupStyle='layer' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='left:{$data['loc_left']}px; top:{$data['loc_top']}px; max-width:{$popupWidth}px; '>";
				}

				if($data['contents_type']=='image'){
					$html .= "<div class='designPopupBody'>";

					if($data['link']){
						$target = "_self";
						if($data['open'] == 1) $target = "_blank";
						$html .= "<a href='{$data['link']}' target='{$target}'>";
					}

					$html .= "<img src='".$sImagePath."' />";
					if($data['link'])  $html .= "</a>";

					$html .= "</div>";

				}else if($data['contents_type']=='text'){
					if(preg_match_all("/\{[\s]*=[\s]*showDesignFlash[\s]*\([\s]*([0-9]+)[\s]*\)[\s]*\}/",$data['contents'],$matches)){
						$CI->template->include_('showDesignFlash');
						foreach($matches[0] as $idx=>$val){
							$flash_seq = $matches[1][$idx];
							$replaceContents = showDesignFlash($flash_seq,true,'cach');
							$data['contents'] = str_replace($val,$replaceContents,$data['contents']);
						}
					}

					$html .= "<div class='designPopupBody' style='height:{$data['height']}px;'>";
					$html .= $data['contents'];
					$html .= "</div>";

				} else {
					// 팝업 HTML
					$popupHtml	= "";
					// 팝업 슬라이드 html
					$slideHtml = "";
					// 팝업 슬라이드 탭 html
					$slideTabHtml = "";
					// 팝업 첫번째 슬라이드 여부
					$isFirstItem = true;
					foreach ($banner_item as $k => $item) {
						// 팝업 슬라이드 이미지 추가
						$slideHtml .= '
						<div class="sslide' . (!$isFirstItem ? ' hide' : '') .'">
							<a class="slink" href="' . $item['link'] . '" target="' . $item['target'] . '">
								<img class="simg" src="' . $item['image'] . '" alt="' . $item['tab_title'] . '" />
							</a>
						</div>
						';

						// 팝업 슬라이드 탭 버튼 추가
						$slideTabHtml .= "<div class='sslide'><a href='javascript:void(0)'>" . $item['tab_title'] ."</a></div>";

						// 팝업 첫번째 슬라이드 여부 확인
						if ($isFirstItem) $isFirstItem = false;
					}

					// 팝업 슬라이드 HTML id
					$popup_id = "popup_slider_$popup_seq";
					// 팝업 슬라이드 탭 HTML id
					$pop_tab_id = "pop_tab_$popup_seq";
					
					// 팝업 슬라이드 html 코드 추가
					$popupHtml .= "
						<div id='$popup_id' class='popup_slider_view'>
							$slideHtml
						</div>
						<div class='popup_slider_tab'>
							<div id='$pop_tab_id' class='pop_tab_list'>
								$slideTabHtml
							</div>
						</div>
					";
					
					// 팝업 슬라이드 script 코드 추가
					$popupHtml .= "
					<script>
						$(function(){
							$('#$popup_id').slick({
								slidesToShow: 1,
								slidesToScroll: 1,
								fade: true,
								arrows: false,
								asNavFor: '#$pop_tab_id'
							});
							$('#$pop_tab_id').slick({
								slidesToShow: 3,
								responsive: [{
									breakpoint: 480,
									settings: {
										slidesToShow: 2
									}
								}],
								slidesToScroll: 1,
								asNavFor: '#$popup_id',
								dots: false,
								speed: 600,
								centerMode: true,
								centerPadding: '10px',
								arrows: true,
								focusOnSelect: true
							});
						});
					</script>
					";

					$html .= $popupHtml;
				}

				$html .= '	<div class="designPopupBar">';
				$html .= '		<div class="designPopupTodaymsg"><label><input type="checkbox"> ' . $data['bar_msg_today_text'] . '</label></div>';
				$html .= '		<div class="designPopupClose"><a href="javascript:void(0)">' . $data['bar_msg_close_text'] . '</a></div>';
				$html .= '	</div>';
				$html .= '</div>';
			}

			if($data['style']=='mobile_layer'){

				@list($imgWidth, $imgHeight) = @getimagesize(ROOTPATH."data/popup/". $data['image']);

				$html .= "<div class='designPopup' popupStyle='layer' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='top:{$data['loc_top']}px;'>";

				if($data['contents_type']=='image'){
					$html .= "<div class='designPopupBody imageStyle'>";
					if($data['link']){
						$target = "_self";
						if($data['open'] == 1) $target = "_blank";
						$html .= "<a href='{$data['link']}' target='{$target}'>";
					}


					$html .= "<img src='".$sImagePath."' alt='' />";
					if($data['link'])  $html .= "</a>";
				}

				if($data['contents_type']=='text'){

					if(preg_match_all("/\{[\s]*=[\s]*showDesignFlash[\s]*\([\s]*([0-9]+)[\s]*\)[\s]*\}/",$data['contents'],$matches)){
						$CI->template->include_('showDesignFlash');
						foreach($matches[0] as $idx=>$val){
							$flash_seq = $matches[1][$idx];
							$replaceContents = showDesignFlash($flash_seq,true,'cach');
							$data['contents'] = str_replace($val,$replaceContents,$data['contents']);
						}
					}

					$html .= "<div class='designPopupBody textStyle' style='max-width:{$data['width']}px; height:{$data['height']}px;'>";
					$html .= $data['contents'];
				}

				$designPopupTodaymsgCss = font_decoration_attr($data['bar_msg_today_decoration'],'css','style');
				$designPopupCloseCss = font_decoration_attr($data['bar_msg_close_decoration'],'css','style');

				$html .= "	</div>";
				$html .= "	<div class='designPopupBar'>";
				$html .= "		<div class='designPopupTodaymsg'><label><input type='checkbox' /> {$data['bar_msg_today_text']}</label></div>";
				$html .= "		<div class='designPopupClose'><a href='javascript:void(0)'>{$data['bar_msg_close_text']}</a></div>";
				$html .= "	</div>";
				$html .= "</div>";
			}

			if($data['style']=='window'){
				$html .= "<div class='designPopupIcon hide' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='border:3px dashed gray; left:{$data['loc_left']}px;top:{$data['loc_top']}px;'><span class='btn small red'><input type='button' value='�˾�[{$popup_seq}] ����' /></span></div>";

				if($data['contents_type']=='image'){
					$imagePath = ROOTPATH.'/data/popup/'.$data['image'];
					list($imageWidth,$imageHeight) = @getimagesize($imagePath);
					$popupWidth = $imageWidth;
					$popupHeight+=$imageHeight+25;
				}else if($data['contents_type']=='text'){
					$popupWidth = $data['width'];
					$popupHeight = $data['height']+25;
				}else{
					$query  = $CI->db->query("select * from fm_design_popup_banner where banner_seq = ?",array($data['popup_banner_seq']));
					$banner = $query->row_array();
					if(!$banner) return;
					$popupWidth = $banner['image_width'];
					$popupHeight = $banner['height']+25;

					if($banner['image_side_margin'] > 0) $popupWidth = $popupWidth+($banner['image_side_margin']*2);
				}

				$html .= js("window.open('/popup/designpopup?seq={$popup_seq}&popup_key={$popup_key}','{$popup_key}','width={$popupWidth},height={$popupHeight},left={$data['loc_left']},top={$data['loc_top']},resizable=no,toolbar=no,menubar=no,status=no,scrollbars=no');");
			}

			if($data['style']=='mobile_band'){
				$ilineCss = "";
				if($data['band_background_image']){
					$ilineCss .= "background-image:url(/data/popup/{$data['band_background_image']});";
					if($data['band_background_image_repeat']) $ilineCss .= "background-repeat:{$data['band_background_image_repeat']};";
					if($data['band_background_image_position']) $ilineCss .= "background-position:{$data['band_background_image_position']};";
				}elseif($data['band_background_color']){
					$ilineCss .= "background-color:{$data['band_background_color']};";
				}

				$html .= "<div class='designPopupBandMobile hide' popupStyle='band' designElement='popup' templatePath='{$template_path}' popupSeq='{$popup_seq}' style='position:relative; {$ilineCss}'>";
				$html .= "<div class='designPopupBody tBanner'>";

				if($data['link']){
					$target = "_self";
					if($data['open'] == 1) $target = "_blank";
					$html .= "<a href='{$data['link']}' target='{$target}'>";
				}
				$html .= "<img src='".$sImagePath."' style='max-width:100%' />";
				if($data['link']) $html .= "</a>";

				$html .= "</div>";
				$html .= "<div class='designPopupClose tBanner' title='close'><a href='javascript:void(0)'>close</a></div>";
				$html .= "</div>";
			}

			cache_save($cache_item_id, $html);
		}

//		debug($html);
		echo $html;
	}
	return;
}
?>
