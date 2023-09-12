<?php
/* 스크립트배너 출력*/
function showDesignBanner($banner_seq, $return = false)
{
	$CI =& get_instance();
	$CI->load->model('designmodel');
	$CI->load->model('layout');

	$skin = $CI->layout->get_view_skin();
	if ($return) {
		$skin = $CI->designWorkingSkin;
	}
	$cache_item_id = sprintf('design_banner_%s_%s_html', $skin, $banner_seq);
	$html = cache_load($cache_item_id);
	
	if ($html === false) {
		$template_path = $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;

		$query = $CI->designmodel->get_design_banner($skin, $banner_seq);
		$banner = $query->row_array();
		$query = $CI->designmodel->get_design_banner_item($skin, $banner_seq);
		$banner_item = $query->result_array();
		if ( ! $banner) {
			return;
		}
		// 반응형 슬라이드 배너 :: 2018-12-10 lwh
		if ($banner['platform'] == 'responsive') {
			if ($return) {
				$html = '<link rel="stylesheet" type="text/css" href="/app/javascript/plugin/slick/slick.css">';
				$html .= "<script src=\"/app/javascript/plugin/slick/slick.min.js?v={=date('YmdHis')}\"></script>";
			}
			$banner_slider_key = $banner['style'] . '_' . $banner['banner_seq'];
			$cssSlider = 'sliderA';
			if ($banner['style'] == 'light_style_2') {
				$cssSlider = 'sliderB';
			}
			if ($return) {
				$html .= '<div class="custom_slider ' . $cssSlider . '">';
			}
			// 치환 제공문구 :: START
			$html .= '<!-- 슬라이드 배너 데이터 영역 :: START -->';
			$html .= '	<div class="' . $banner_slider_key . ' designBanner" designElement="banner" templatePath="'.$template_path.'" bannerSeq="'.$banner_seq.'">';
			foreach ($banner_item as $k=>$item) {
				// 이미지 호스팅 사용 여부에 따른 이미지 경로 구분 :: 2022-10-27 @nsg
				if(substr($item['image'], 0,4) == "http"){
					$image = $item['image'];
				}else{
					$image = '/data/skin/' . $skin . '/' . $item['image'];
				}

				if ($banner['style'] == 'light_style_2') {
					$html .= '<div class="sslide">';
					$html .= '	<img class="simg" src="' . $image . '" />';
					$html .= '	<div class="slide_contents">';
					$html .= '		<div class="wrap1">';
					$html .= '			<div class="wrap2">';
					$html .= '				<ul class="text_wrap">';
					$html .= '					' . $item['tag_ctrl'];
					$html .= '				</ul>';
					$html .= '			</div>';
					$html .= '		</div>';
					$html .= '	</div>';
					$html .= '</div>';
				} else {
					$html .= '<div class="sslide"><a class="slink" href="' . $item['link'] . '" target="' . $item['target'] . '"><img class="simg" src="' . $image . '" /></a></div>';
				}
			}
			$html .= '	</div>';
			$html .= '<!-- 슬라이드 배너 데이터 영역 :: END -->';
			if ($return) {
				$html .= '</div>';
			}
			if ($return) {
				$html .= '<script type="text/javascript">';
				$html .= '$(function() {';
				$html .= "	$('." . $banner_slider_key . "').slick({";
				$html .= '		dots: true,';
				$html .= '		autoplay: true,';
				$html .= '		speed: 1000,';
				$html .= '		fade: true,';
				$html .= '		autoplaySpeed: 5000';
				$html .= '	});';
				$html .= '});';
				$html .= '</script>';
			}
		} else {
			// 이미지 호스팅 사용 여부에 따른 이미지 경로 구분 :: 2022-10-27 @nsg
			if(substr($banner_item[0]['image'], 0,4) == "http"){
				$image = $banner_item[0]['image'];
			}else{
				$image = '/data/skin/' . $skin . '/' . $banner_item[0]['image'];
			}

			// Heavy 형 슬라이드 배너
			$html = "";
			$html .= "<script type='text/javascript' src='/app/javascript/jquery/jquery.ui.touch-punch.min.js'></script>";
			$html .= "<script type='text/javascript' src='/app/javascript/plugin/anibanner/jquery.anibanner.js?v=20140808'></script>";
			$html .= "<link rel='stylesheet' type='text/css' href='/app/javascript/plugin/anibanner/anibanner.css' />";
			if ($banner['navigation_paging_style']=='custom') {
				/* 이미지가로세로 크기 */
				@list($customImageWidth, $customImageHeight) = @getimagesize(ROOTPATH."data/skin/{$skin}/images/banner/{$banner_seq}/{$banner_item[0]['tab_image_inactive']}");
				$banner['navigation_paging_height'] = $customImageHeight;
			}
			if ($banner['image_height'] > $banner['height']) $banner['height']	= $banner['image_height'];
			if ($banner['navigation_paging_position'] == 'bottom') {
				$banner['bottom_paging_area_size']	= $banner['navigation_paging_margin'];
				switch ($banner['navigation_paging_style']){
					case 'paging_style_1':	$banner['bottom_paging_area_size']	+= 13;	break;
					case 'paging_style_2':	$banner['bottom_paging_area_size']	+= 12;	break;
					case 'paging_style_3':	$banner['bottom_paging_area_size']	+= 5;	break;
					case 'paging_style_4':	$banner['bottom_paging_area_size']	+= 20;	break;
					case 'paging_style_5':	$banner['bottom_paging_area_size']	+= 30;	break;
					case 'custom':			$banner['bottom_paging_area_size']	+= $banner['navigation_paging_height'];	break;
				}
			}
			switch ($banner['style']) {
				case 'pc_style_1':
					$imgHTML	= '<div style="width:100%;height:100%;background-color:' . $banner['background_color'] . '"></div>';
				break;
				case 'pc_style_2':
					$imgHTML	= '<img src="' .$image. '" width="100%" height="' . $banner['image_height'] . '" />';
				break;
				case 'pc_style_3':
					$tmpPadding		= 'padding-bottom:0;';
					if	($banner['navigation_paging_position'] == 'bottom' && $banner['bottom_paging_area_size'] > 0){
						$tmpPadding		= 'padding-bottom:' . $banner['bottom_paging_area_size'] . 'px;';
					}
					$imgHTML		= '<div style="height:' . $banner['image_height'] . 'px;' . $tmpPadding . '"><div style="width:' . $banner['image_width'] . 'px;height:' . $banner['image_height'] . 'px;background-color:' . $banner['background_color'] . ';padding:' . $banner['image_top_margin'] . 'px ' . $banner['image_side_margin'] . 'px;"><img src="' .$image. '" width="' . $banner['image_width'] . '" height="' . $banner['image_height'] . '" /></div></div>';
				break;
				case 'pc_style_4':
					$imgHTML	= '<div style="width:100%;position:relative;"><img src="' .$image. '" width="' . $banner['image_width'] . '" height="' . $banner['image_height'] . '" style="position:absolute;top:0;left:0;" /></div>';
				break;
				case 'pc_style_5':
					$tmpPadding		= 'padding-bottom:0;';
					if	($banner['navigation_paging_position'] == 'bottom' && $banner['bottom_paging_area_size'] > 0){
						$tmpPadding		= 'padding-bottom:' . $banner['bottom_paging_area_size'] . 'px;';
					}
					$imgHTML		= '<div style="height:' . $banner['image_height'] . 'px;' . $tmpPadding . '"><div style="width:' . $banner['image_width'] . 'px;height:' . $banner['image_height'] . 'px;background-color:' . $banner['background_color'] . ';padding:' . $banner['image_top_margin'] . 'px ' . $banner['image_side_margin'] . 'px;"><img src="' .$image. '" width="' . $banner['image_width'] . '" height="' . $banner['image_height'] . '" /></div></div>';
				break;
				default:
					$imgHTML	= '';
				break;
			}
			$html .= "<div class='designBanner' designElement='banner' templatePath='{$template_path}' bannerSeq='{$banner_seq}' style='height:{$banner['height']}px;'>" . $imgHTML . "</div>";
			$html .= "<script>";
			$html .= "$(function(){";
			$html .= "var bannerTimer".$banner_seq.";";
			$html .= "var settings".$banner_seq."= {";
			$html .= "'platform' : '{$banner['platform']}',";
			$html .= "'modtime' : '{$banner['modtime']}',";
			$html .= "'style' : '{$banner['style']}',";
			$html .= "'height' : '{$banner['height']}',";
			$html .= "'background_color' : '{$banner['background_color']}',";
			// 값이 없는데 호출 되면, 웹브라우저 네트워크 302 에러 출력 됩니다.
			if (isset($banner['background_image']) && strlen($banner['background_image']) > 0) {
				$html .= "'background_image' : '/data/skin/{$skin}/{$banner['background_image']}',";
			}
			$html .= "'background_repeat' : '{$banner['background_repeat']}',";
			$html .= "'background_position' : '{$banner['background_position']}',";
			$html .= "'image_border_use' : '{$banner['image_border_use']}',";
			$html .= "'image_border_width' : '{$banner['image_border_width']}',";
			$html .= "'image_border_color' : '{$banner['image_border_color']}',";
			$html .= "'image_opacity_use' : '{$banner['image_opacity_use']}',";
			$html .= "'image_opacity_percent' : '{$banner['image_opacity_percent']}',";
			$html .= "'image_top_margin' : '{$banner['image_top_margin']}',";
			$html .= "'image_side_margin' : '{$banner['image_side_margin']}',";
			$html .= "'image_width' : '{$banner['image_width']}',";
			$html .= "'image_height' : '{$banner['image_height']}',";
			$html .= "'navigation_btn_style' : '{$banner['navigation_btn_style']}',";
			$html .= "'navigation_btn_visible' : '{$banner['navigation_btn_visible']}',";
			$html .= "'navigation_paging_style' : '{$banner['navigation_paging_style']}',";
			$html .= "'navigation_paging_height' : '{$banner['navigation_paging_height']}',";
			$html .= "'navigation_paging_align' : '{$banner['navigation_paging_align']}',";
			$html .= "'navigation_paging_position' : '{$banner['navigation_paging_position']}',";
			$html .= "'navigation_paging_margin' : '{$banner['navigation_paging_margin']}',";
			$html .= "'navigation_paging_spacing' : '{$banner['navigation_paging_spacing']}',";
			$html .= "'slide_event' : '{$banner['slide_event']}',";
			$html .= "'images' : [";
			foreach ($banner_item as $k=>$item) {
				// 이미지 호스팅 사용 여부에 따른 이미지 경로 구분 :: 2022-10-27 @nsg
				if(substr($item['image'], 0,4) == "http"){
					$image = $item['image'];
				}else{
					$image = '/data/skin/' . $skin . '/' . $item['image'];
				}
				if($k) $html .= ",";
				$html .= "{'link':'{$item['link']}','target':'{$item['target']}','image':'{$image}'}";
			}
			$html .= "],";
			$html .= "'navigation_paging_custom_images' : [";
			foreach ($banner_item as $k=>$item) {
				if ($k) $html .= ",";
				$html .= "{'active':'/data/skin/{$skin}/images/banner/{$banner_seq}/{$item['tab_image_active']}','inactive':'/data/skin/{$skin}/images/banner/{$banner_seq}/{$item['tab_image_inactive']}'}";
			}
			$html .= "]";
			$html .= "};";
			$html .= "
					if (typeof(callAnibanner".$banner_seq.") != 'function'){
						function callAnibanner".$banner_seq."() {
							if (typeof ($.custom.anibanner) != 'undefined') {
								$('.designBanner[bannerSeq=\"".$banner_seq."\"]').anibanner(settings".$banner_seq.");
							}
							clearInterval(bannerTimer".$banner_seq.");
						}
					}
					if (typeof($.custom.anibanner) == 'undefined'){
						clearInterval(bannerTimer".$banner_seq.");
						bannerTimer".$banner_seq." = setInterval(callAnibanner".$banner_seq.",100);
					} else {
						$('.designBanner[bannerSeq=\"{$banner_seq}\"]').anibanner(settings".$banner_seq.");
					}
			";

			$html .= "})";
			$html .= "</script>";
		}
		cache_save($cache_item_id, $html);
	}
	if ($return) return $html;
	else echo $html;
	return;
}
?>
