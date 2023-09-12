<?php
function assignBrandMenuData()
{
	$CI =& get_instance();
	$CI->load->model('brandmodel');
	setlocale(LC_CTYPE, C);
	$cache_item_id = 'brand_menu_data';
	$best_icon_yn = false;
	$data = false;
	$data = cache_load($cache_item_id);
	if ($data === false) {
		$arr = array(
			'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
			'ㄱ','ㄴ','ㄷ','ㄹ','ㅁ','ㅂ','ㅅ','ㅇ','ㅈ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ','기타'
		);
		$arr2 = array(
			'ㄱ' => array('가','나'),
			'ㄴ' => array('나','다'),
			'ㄷ' => array('다','라'),
			'ㄹ' => array('라','마'),
			'ㅁ' => array('마','바'),
			'ㅂ' => array('바','사'),
			'ㅅ' => array('사','아'),
			'ㅇ' => array('아','자'),
			'ㅈ' => array('자','차'),
			'ㅊ' => array('차','카'),
			'ㅋ' => array('카','타'),
			'ㅌ' => array('타','파'),
			'ㅍ' => array('파','하'),
			'ㅎ' => array('하','힣')
		);
		$data = array();
		$chks = array();
		foreach ($arr as $v) {
			$data[$v] = array();
		}
		$query = $CI->brandmodel->get_brand_menu();
		foreach ($query->result_array() as $row) {
			if ($best_icon_yn === false && $row['best'] == 'Y') {
				$best_icon_yn = true;
			}
			foreach ($arr as $key=>$prefix) {
				if ($row['title_eng']) {
					if ($prefix == "기타") {
						if (preg_match('/[^\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}a-zA-Z]/u',substr($row['title_eng'], 0, 1))) {
							$row['prn_title'] = $row['title_eng'];
							if (in_array($row['prn_title'],$chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					} elseif('a' <= $prefix && $prefix <= 'z') {
						if (strtolower(substr($row['title_eng'],0,1))==$prefix) {
							$row['prn_title'] = $row['title_eng'];
							if (in_array($row['prn_title'],$chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					} elseif (in_array($prefix,array_keys($arr2))) {
						if ($arr2[$prefix][0]<=substr($row['title_eng'],0,3) && substr($row['title_eng'],0,3)<$arr2[$prefix][1]) {
							$row['prn_title'] = $row['title_eng'];
							if (in_array($row['prn_title'],$chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					}
				}
				if ($row['title']) {
					if ($prefix=="기타") {
						if (preg_match('/[^\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}a-zA-Z]/u',substr($row['title'], 0, 1))) {
							$row['prn_title'] = $row['title'];
							if (in_array($row['prn_title'],$chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					}elseif ('a' <= $prefix && $prefix <= 'z') {
						if (strtolower(substr($row['title'], 0, 1)) == $prefix) {
							$row['prn_title'] = $row['title'];
							if (in_array($row['prn_title'], $chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					}elseif (in_array($prefix, array_keys($arr2))) {
						if ($arr2[$prefix][0] <= substr($row['title'], 0, 3) && substr($row['title'], 0, 3) < $arr2[$prefix][1]) {
							$row['prn_title'] = $row['title'];
							if (in_array($row['prn_title'], $chks)) continue;
							$chks[] = $row['prn_title'];
							$data[$prefix][] = $row;
						}
					}
				}
			}
		}
		cache_save($cache_item_id, $data);
	}

	
	foreach ($data as $tmp) {
		foreach ($tmp as $row) {
			if ($row['title_eng']) {
				$row['prn_title'] = $row['title']."(".$row['title_eng'].")";
			} else {
				$row['prn_title'] = $row['title'];
			}
			if (in_array($row['category_code'], array_keys($all))==false) {
				$all[$row['category_code']] = $row;
			}
			if ($best_icon_yn === false && $row['best'] == 'Y') {
				$best_icon_yn = true;
			}
		}
	}

	// 아이콘 로드 :: 2018-12-26 lwh
	if ($best_icon_yn) {
		$config_icon = config_load('brand_main', 'best_icon');
		$brand_best_icon = $config_icon['best_icon'];
		if ($brand_best_icon) $CI->template->assign(array('brand_best_icon' => $brand_best_icon));
	}

	$CI->template->assign(array('brandMenuData'=>$data));
	$CI->template->assign(array('ALL'=>$all));
}
?>
