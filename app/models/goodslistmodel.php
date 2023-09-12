<?php
/*
	라이트용 상품 리스팅 모델(front 사용)
	카테고리(goods/catalog), 지역(goods/location), 브랜드(goods/brand), 검색(goods/search), 베스트(goods/best), 신상품(goods/new_arrivals),
	이벤트(promotion/event_view), 사은품이벤트(promotion/gift_view), 미니샵(mshop/index)
*/
class goodslistmodel extends CI_Model
{
	var $aFilterConfig = [
		'category'   => false,
		'location'   => false,
		'brand'      => false,
		'freeship'   => false,
		'abroadship' => false,
		'price'      => false,
		'rekeyword'  => false,
		'color'      => false,
		'seller'     => false,
		'orderby'    => 'ranking',
		'normal'     => true,
    ];
	var $aPageType = [
		'catalog'      => 'category',
		'brand'        => 'brand',
		'location'     => 'location',
		'search'       => 'search_result',
		'event_view'   => 'sales_event',
		'gift_view'    => 'gift_event',
		'mshop'        => 'mshop',
		'best'         => 'bestproduct',
		'new_arrivals' => 'newproduct'
	];
	var $aOrderby = [
		'rank'   => 'ranking',
		'new'    => 'regist',
		'low'    => 'low_price',
		'high'   => 'high_price',
		'review' => 'review',
		'sales'  => 'sale'
    ];
	var $bGoodsCache = false;

	public function __construct()
	{
		parent::__construct();

		//
		$this->load->driver('cache');
		$this->load->model('goodsmodel');
		$this->load->library('sale');
		$this->load->library('goodsList');
		$this->load->model('categorymodel');

		if ($this->config_system['goodsCacheUse'] == 'Y') {
			$this->bGoodsCache = true;
		}
	}

	protected function resetFilterConfig()
	{
		$this->aFilterConfig = [
			'category'   => false,
			'location'   => false,
			'brand'      => false,
			'freeship'   => false,
			'abroadship' => false,
			'price'      => false,
			'rekeyword'  => false,
			'color'      => false,
			'seller'     => false,
			'orderby'    => 'ranking',
			'normal'     => true,
		];
	}

	protected function loadFilterConfig($sPageType)
	{
		$this->load->model('pagemanagermodel');
		$aDefaultSetting = $this->pagemanagermodel->default_filters[$sPageType];
		if (in_array('category', $aDefaultSetting)) {
			if ($sPageType == 'location') {
				$this->aFilterConfig['location'] = true;
			} else {
				$this->aFilterConfig['category'] = true;
			}
		}
		if (in_array('brand', $aDefaultSetting)) {
			$this->aFilterConfig['brand'] = true;
		}
		if (in_array('freeship', $aDefaultSetting)) {
			$this->aFilterConfig['freeship'] = true;
		}
		if (in_array('abroadship', $aDefaultSetting)) {
			$this->aFilterConfig['abroadship'] = true;
		}
		if (in_array('price', $aDefaultSetting)) {
			$this->aFilterConfig['price'] = true;
		}
		if (in_array('rekeyword', $aDefaultSetting)) {
			$this->aFilterConfig['rekeyword'] = true;
		}
		if (in_array('color', $aDefaultSetting)) {
			$this->aFilterConfig['color'] = true;
		}
		if (in_array('seller', $aDefaultSetting)) {
			$this->aFilterConfig['seller'] = true;
		}
	}

	protected function setFilterConfig($sSearchFilter, $sSetMode)
	{
		if (preg_match('/category/', $sSearchFilter) && $sSetMode != 'location') {
			$this->aFilterConfig['category'] = true;
		}
		if (preg_match('/category/', $sSearchFilter) && $sSetMode == 'location') {
			$this->aFilterConfig['location'] = true;
		}
		if (preg_match('/brand/', $sSearchFilter) && $sSetMode != 'brand') {
			$this->aFilterConfig['brand'] = true;
		}
		if (preg_match('/freeship/', $sSearchFilter)) {
			$this->aFilterConfig['freeship'] = true;
		}
		if (preg_match('/abroadship/', $sSearchFilter)) {
			$this->aFilterConfig['abroadship'] = true;
		}
		if (preg_match('/price/', $sSearchFilter)) {
			$this->aFilterConfig['price'] = true;
		}
		if (preg_match('/rekeyword/', $sSearchFilter)) {
			$this->aFilterConfig['rekeyword'] = true;
		}
		if (preg_match('/color/', $sSearchFilter)) {
			$this->aFilterConfig['color'] = true;
		}
		if (preg_match('/seller/', $sSearchFilter) && !preg_match('/event_view|gift_view|mshop/', $sSetMode)) {
			$this->aFilterConfig['seller'] = true;
		}
	}

	protected function getCategoryCodeFromLink($aResultGoodsSeq)
	{
		if (!$aResultGoodsSeq || count($aResultGoodsSeq) == 0) {
			return false;
		}

		//
		$result           = [];
		$$result_category = [];

		//
		$query = $this->db->select('goods_seq, category_code, link')
			->from('fm_category_link')
			->where_in('goods_seq', $aResultGoodsSeq)
			->get();
		foreach ($query->result_array() as $data) {
			foreach ($this->categorymodel->split_category($data['category_code']) as $cate) {
				$tmp_category_arr[$data['goods_seq']][] = $cate;
			}
			$result[$data['goods_seq']]['r_category'] = array_values(array_unique($tmp_category_arr[$data['goods_seq']]));
			if ($data['link']  == 1) {
				$result_category[$data['goods_seq']]['category_code'] = $data['category_code'];
			}
		}
		return [$result, $result_category];
	}

	protected function goodsListInfo($sPlatform, $aGoodsSeqs)
	{
		$result = array();
		$this->db->select("go.goods_seq, go.goods_status, go.provider_seq, go.goods_name, go.summary, go.review_count, go.review_sum, go.color_pick, go.purchase_ea, go.shipping_group_seq, go.reserve_policy, go.wish_count, go.sale_seq, gp.consumer_price, gp.price, gp.reserve_rate, gp.reserve_unit, gp.reserve, gs.brand_code, gs.today_icon, gs.today_solo_start, gs.today_solo_end, gs.brand_title, go.string_price_use, go.string_price,go.string_price_link, go.string_price_link_url, go.member_string_price_use, go.member_string_price, go.member_string_price_link, go.member_string_price_link_url, go.allmember_string_price_use, go.allmember_string_price, go.allmember_string_price_link, go.allmember_string_price_link_url, go.string_price_color, go.member_string_price_color, go.allmember_string_price_color,go.tax,gp.option1,gp.option2,gp.option2,gp.option3,gp.option4,gp.option5");
		$this->db->from("fm_goods go");
		$this->db->join("fm_goods_option gp", "go.goods_seq = gp.goods_seq", "inner");
		$this->db->join("fm_goods_list_summary gs", "go.goods_seq = gs.goods_seq", "inner");
		$this->db->where('gp.default_option', 'y');
		$this->db->where('gs.platform', $sPlatform);
		$this->db->where_in('go.goods_seq', $aGoodsSeqs);
		foreach ($this->db->get()->result_array() as $data) {
			$result[$data['goods_seq']] = $data;
		}
		return $result;
	}

	// image_size
	public function goodsSearch($aParams, $sGoodsQuery, $iTotcount = '')
	{
		$cfg_reserve = $this->reserves ?: config_load('reserve');

		$sSorting = '`go`.`goods_seq` DESC';
		switch ($aParams['sorting']) {
			case 'ranking':
				if ($this->bGoodsCache) {
					$sSorting = '`go`.`ranking_point` DESC';
				} else {
					$sSorting = '`gl`.`ranking_point` DESC';
				}
				break;
			case 'regist':
				$sSorting = '`go`.`goods_seq` DESC';
				break;
			case 'low_price':
				$sSorting = '`go`.`default_price` ASC';
				break;
			case 'high_price':
				$sSorting = '`go`.`default_price` DESC';
				break;
			case 'review':
				$sSorting = '`go`.`review_count` DESC';
				break;
			case 'sale':
				$sSorting = '`go`.`purchase_ea` DESC';
				break;
			case 'category_ranking':
				$sSorting = '`cl`.`sort` ASC';
				break;
			case 'brand_ranking':
				$sSorting = '`bl`.`sort` ASC';
				break;
			case 'location_ranking':
				$sSorting = '`ll`.`sort` ASC';
				break;
		}

		// 카테고리 페이지에서 카테고리 외 검색 조건이 없을 경우 랭킹으로 정렬시 정렬 재정의
		if ($aParams['sorting'] == 'category_ranking' && $this->bGoodsCache) {
			$sGoodsQuery = str_replace("FROM `fm_goods_cache_list` `go`", "FROM `fm_goods_cache_list` `go` INNER JOIN `fm_category_link` `cl` ON `cl`.`goods_seq` = `go`.`goods_seq` AND `cl`.`category_code` = '" . str_replace('c', '', $aParams['category']) . "'", $sGoodsQuery);
		}

		// 브랜드 페이지에서 브랜드 외 검색 조건이 없을 경우 랭킹으로 정렬시 정렬 재정의
		if ($aParams['sorting'] == 'brand_ranking' && $this->bGoodsCache) {
			$sBrands = str_replace('b','', implode("','", $aParams['brand']));
			$sGoodsQuery = str_replace("FROM `fm_goods_cache_list` `go`", "FROM `fm_goods_cache_list` `go` INNER JOIN `fm_brand_link` `bl` ON `bl`.`goods_seq` = `go`.`goods_seq` AND `bl`.`category_code` IN ('" . $sBrands . "')", $sGoodsQuery);
		}

		// 지역 페이지에서 지역 외 검색 조건이 없을 경우 랭킹으로 정렬시 정렬 재정의
		if ($aParams['sorting'] == 'location_ranking' && $this->bGoodsCache) {
			$sGoodsQuery = str_replace("FROM `fm_goods_cache_list` `go`", "FROM `fm_goods_cache_list` `go` INNER JOIN `fm_location_link` `ll` ON `ll`.`goods_seq` = `ll`.`goods_seq` AND `ll`.`location_code` = '" . str_replace('l', '', $aParams['location']) . "'", $sGoodsQuery);
		}

		// 베스트
		if ($aParams['sorting'] == 'sale' && $aParams['searchMode'] == 'best') {
			// 설정 내용 호출
			$this->load->model('pagemanagermodel');
			$page_config = $this->pagemanagermodel->get_page_config('bestproduct', 'responsive');
			$sSorting = '`go`.`purchase_ea` DESC';
			if ($page_config['orderby'][0] == 'monthly') {
				$sSorting = '`go`.`purchase_ea_3mon` DESC, `go`.`purchase_ea` DESC';
			}
		}
		// 2차 정렬은 goods_seq DESC (최신순 노출)
		$sSorting .= ', go.goods_seq DESC';
		
		//
		$sGoodsQuery = substr($sGoodsQuery, 1, -1) . PHP_EOL . 'ORDER BY ' . $sSorting;

		//
		$result = select_page($aParams['perpage'], $aParams['page'], 5, $sGoodsQuery, [], null, null, $iTotcount);

		//
		$aResultGoodsSeq = [];
		$aShippingGroupSeq = [];
		$aShippingGroupSeqForGoods = [];

		foreach ($result['record'] as &$data) {
			$aResultGoodsSeq[]   = $data['goods_seq'];
		}

		// 추가 정보
		$aGoods = $this->goodsListInfo($aParams['platform'], $aResultGoodsSeq);

		// 배송 그룹 정보
		$aShippingGroupSeqForGoods = array_column($aGoods, 'shipping_group_seq', 'goods_seq');
		$aShippingGroupSeq = array_unique(array_values($aShippingGroupSeqForGoods));

		//
		$aImagesGoods      = $this->goodsmodel->get_images($aResultGoodsSeq, $aParams['image_size']);
		$aColorsGoods      = $this->goodsmodel->get_colors($aResultGoodsSeq);
		$aProviderGoods    = $this->goodsmodel->get_provider_names($aResultGoodsSeq);
		$aBrandsGoods      = $this->goodsmodel->get_goods_brands($aResultGoodsSeq);
		$aShippingSummarys = $this->goodsmodel->get_goods_shipping_summary($aShippingGroupSeqForGoods, $aShippingGroupSeq);
		list($aCategoryCodesGoods, $aCategoryCodeGoods) = $this->getCategoryCodeFromLink($aResultGoodsSeq);
		$aCategorysGoods = $this->goodsmodel->get_goods_categorys($aCategoryCodeGoods);
		if (! empty($aParams['member_seq'])) {
			$aWishsGoods = $this->goodsmodel->get_goods_wish($aResultGoodsSeq, $aParams['member_seq']);
		}

		// 추가 정보 조합
		foreach ($result['record'] as &$data) {
			$goods_seq = $data['goods_seq'];

			$data = $aGoods[$goods_seq];
			$data['image']          = $aImagesGoods[$goods_seq]['image1'];
			$data['image2']         = $aImagesGoods[$goods_seq]['image2'];
			$data['image_cnt']      = $aImagesGoods[$goods_seq]['image_cnt'];
			$data['image1_large']   = $aImagesGoods[$goods_seq]['image1_large'];
			$data['image2_large']   = $aImagesGoods[$goods_seq]['image2_large'];
			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
			if ($markingAdultImg) {
				$data['image'] = $data['image2'] = $data['image1_large'] = $data['image2_large'] = $this->goodslist->adultImg;
			}
			$data['colors']         = $aColorsGoods[$goods_seq];
			$data['provider_name']  = $aProviderGoods[$goods_seq]['provider_name'];
			$data['pgroup_icon']    = $aProviderGoods[$goods_seq]['pgroup_icon'];
			$data['pgroup_name']    = $aProviderGoods[$goods_seq]['pgroup_name'];
			$data['category_code']  = $aCategoryCodeGoods[$goods_seq]['category_code'];
			$data['r_category']     = $aCategoryCodesGoods[$goods_seq]['r_category'];
			$data['r_brand']        = $aBrandsGoods[$goods_seq]['r_brand'];
			$data['wish']           = $aWishsGoods[$goods_seq]['wish'];
			$data['shipping_group'] = $aShippingSummarys[$goods_seq]['shipping_group'];
			$data['category']       = $aCategorysGoods[$goods_seq]['title'];

			/**
			 * 무료배송 사용 여부
			 * 2019-07-10
			 * @author Sunha Ryu
			 * @see #36384
			 */
			if (! empty($data['shipping_group']) && $data['shipping_group']['free_shipping_use'] === 'Y') {
				$data['free_delivery'] = true;
			} else {
				$data['free_delivery'] = false;
			}

			/**
			 * 해외배송 사용 여부
			 * 2019-07-10
			 * @author Sunha Ryu
			 * @see #36384
			 */
			if (! empty($data['shipping_group']) && $data['shipping_group']['gl_shipping_yn'] === 'Y') {
				$data['free_overseas'] = true;
			} else {
				$data['free_overseas'] = false;
			}

			//--> sale library 적용
			$aSaleParams = array(
				'consumer_price' => $data['consumer_price'],
				'price'          => $data['price'],
				'total_price'    => $data['price'],
				'ea'             => 1,
				'category_code'  => $data['r_category'],
				'brand_code'     => $data['r_brand'],
				'goods_seq'      => $goods_seq,
				'goods'          => $data,
				'group_seq'      => $this->userInfo['group_seq']
			);
			$this->sale->set_init($aSaleParams);
			$aSales	= $this->sale->calculate_sale_price('list');
			// GA4연동으로 인해 sale_list 전달
			$data['sale_list'] = $aSales['sale_list'];
			$data['sale_price']	= $aSales['result_price'];
			$data['sale_per']	= $aSales['sale_per'];
			$data['eventEnd']	= $aSales['eventEnd'];
			$data['event_text']	= trim($aSales['text_list']['event']);
			$data['event_order_ea']	= $aSales['event_order_ea'];
			$reserve				= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $aSales['result_price'], $cfg_reserve['default_reserve_percent'], $data['reserve_rate'], $data['reserve_unit'], $data['reserve']);
			$data['reserve']		= $reserve + $aSales['tot_reserve'];
			$this->sale->reset_init();
			//<-- sale library 적용

			$data['string_price'] = get_string_price($data);
			$data['string_price_use'] = 0;
			if ($data['string_price'] != '') {
				$data['string_price_use'] = 1;
			}
		}

		//
		return $result;
	}

	public function queryBuild($aParams, $sPageMode = 'search', $bTmpCreate = true)
	{
		$not_in_goods_seq = [];
		$not_in_category_code = [];
		$in_goods_seq = [];
		$in_category_code = [];

		$is_join_category_link  = false;

		//
		if ($aParams['category']) {
			$category = str_replace('c', '', $aParams['category']);
		}
		if ($aParams['location']) {
			$location = str_replace('l', '', $aParams['location']);
		}
		if ($aParams['bcode']) {
			$bcode = str_replace('b', '', $aParams['bcode']);
		}
		if ($aParams['brand']) {
			$brand = array_map(function($value) { return str_replace('b', '', $value); }, $aParams['brand']);
		}

		// 서브쿼리 사용으로 변경
		$bTmpCreate = false;

		//
		$dbGoods = (clone $this->db)->reset_query();
		if ($this->bGoodsCache) {
			$dbGoods->select('go.goods_seq')
				->from('fm_goods_cache_list go');
		} else {
			$dbGoods->select('go.goods_seq')
				->from('fm_goods go')
				->where('go.goods_type', 'goods')
				->where('go.provider_status', '1')
				->group_start()
				->where('go.goods_view', 'look')
				# 노출 예약일 경우
				->or_group_start()
				->or_where('go.display_terms','auto')
					->where('go.display_terms_begin <= date(now())')
					->where('go.display_terms_end >= date(now())')
					->where('go.display_terms_before = "CONCEAL"')
					->where('go.display_terms_after = "CONCEAL"')
				->group_end()
				->group_end();
		}

		// 상품 상태
		$aGoodsStatusWhere = [];
		if ($this->aFilterConfig['normal']) {
			$aGoodsStatusWhere[] = 'normal';
		}
		if ($this->aFilterConfig['runout']) {
			$aGoodsStatusWhere[] = 'runout';
		}
		if ($this->aFilterConfig['purchasing']) {
			$aGoodsStatusWhere[] = 'purchasing';
		}
		if ($this->aFilterConfig['unsold']) {
			$aGoodsStatusWhere[] = 'unsold';
		}

		if (! $aGoodsStatusWhere) {
			$aGoodsStatusWhere[] = 'normal';
		}
		$dbGoods->where_in('go.goods_status', $aGoodsStatusWhere);

		//
		if ($aParams['platform'] && $this->bGoodsCache === false) {
			$dbGoods->join('fm_goods_list_summary gl', "go.goods_seq = gl.goods_seq and gl.platform = '" . $aParams['platform'] . "'", 'straight');
		}

		// 브랜드
		if ($bcode || $brand) {
			if ($this->bGoodsCache) {
				if ($bcode) {
					$dbGoods->like('go.brand_link_code', ',' . $bcode . ',', 'both');
				} elseif ($brand) {
					$sBrand = $this->db->escape(',' . implode(',|,', $brand) . ',');
					$dbGoods->where('`go`.`brand_link_code` REGEXP ', $sBrand, false);
				}
			} else {
				$dbGoods->join('`fm_brand_link` `bl` USE INDEX (`ix_goods_seq_category_code`)', 'bl.goods_seq = go.goods_seq', 'inner');
				if ($bcode) {
					$dbGoods->like('bl.category_code', $bcode, 'after');
				} elseif ($brand) {
					$dbGoods->where_in('bl.category_code', $brand);
				}
			}
		}

		// 배송 그룹
		if ($aParams['delivery']) {
			if ($this->bGoodsCache) {
				$dbGoods->group_start();
				foreach ($aParams['delivery'] as $sDelivery) {
					if ($sDelivery == 'free') {
						$dbGoods->or_where('go.free_shipping_use', 'Y');
					}
					if ($sDelivery == 'overseas') {
						$dbGoods->or_where('go.gl_shipping_yn', 'Y');
					}
				}
				$dbGoods->group_end();
			} else {
				$aTmpDelivery = [];
				foreach ($aParams['delivery'] as $sDelivery) {
					if ($sDelivery == 'free') {
						$aTmpDelivery[] = "`free_shipping_use` = 'Y'";
					} elseif ($sDelivery == 'overseas') {
						$aTmpDelivery[] = "`gl_shipping_yn` = 'Y'";
					}
				}
				if ($aTmpDelivery) {
					$dbGoods->where("EXISTS (SELECT 1 FROM `fm_shipping_group_summary` WHERE `shipping_group_seq` = `go`.`shipping_group_seq` AND (" . implode(' OR ', $aTmpDelivery) . "))", null, false);
				}
			}
		}

		// 카테고리
		if ($category) {
			if ($this->bGoodsCache) {
				$dbGoods->like('go.category_link_code', ',' . $category . ',', 'both');
			} else {
				$is_join_category_link = true;
				$dbGoods->join('`fm_category_link` `cl` USE INDEX (`ix_goods_seq_category_code`)', 'cl.goods_seq = go.goods_seq', 'inner');
				$dbGoods->where('cl.category_code', $category);
			}
		}

		// 지역
		if ($location) {
			if ($this->bGoodsCache) {
				$dbGoods->where('go.location_link_code', ',' . $location . ',', 'both');
			} else {
				$dbGoods->join('`fm_location_link` `ll` USE INDEX (`ix_goods_seq_location_code`)', 'll.goods_seq = go.goods_seq', 'inner');
				$dbGoods->where('ll.location_code', $location);
			}
		}

		// 검색어
		$aKeywords = [];
		if ($aParams['keyword']) {
			if ($this->bGoodsCache) {
				if (trim($aParams['keyword'])) {
					$aKeywords[] = trim($aParams['keyword']);
				}
			} else {
				$keywords = explode(' ', $aParams['keyword']);
				$bind_keyword = '';
				foreach ($keywords as $k) {
					$k = trim($k);
					if (mb_strlen($k) > 1) {
						$bind_keyword .= ' ' . $k;
					}
				}
				$bind_keyword = trim($bind_keyword);
				if ($bind_keyword) {
					$aKeywords[] = $bind_keyword;
				} else {
					pageBack('검색어를 2자 이상 입력해주세요.');
					return false;
				}
			}
		}

		if ($aParams['re_search']) {
			if ($this->bGoodsCache) {
				if (trim($aParams['re_search'])) {
					$aKeywords[] = trim($aParams['re_search']);
				}
			} else {
				$keywords = explode(' ', $aParams['re_search']);
				$bind_keyword = '';
				foreach ($keywords as $k) {
					$k = trim($k);
					if (mb_strlen($k) > 1) {
						$bind_keyword .= ' ' . $k;
					}
				}
				$bind_keyword = trim($bind_keyword);
				if ($bind_keyword) {
					$aKeywords[] = $bind_keyword;
				} else {
					$referer	= $this->input->server('HTTP_REFERER');
                    $url = preg_replace("!re_search(.*?)&!is","",$referer); //query parameter 에서 re_search 제거
					pageRedirect($url, '재검색어를 2자 이상 입력해주세요.');
					return false;
				}
			}
		}

		if ($aKeywords) {
			$this->bGoodsCache = false; // 추후 검색 엔진 사용 설정 추가 시 false 고정 삭제
			if (!$this->bGoodsCache) {
				$dbGoods->group_start();
				foreach ($aKeywords as $sKeyword) {
					$dbGoods->group_start()
						->like('go.goods_name', $sKeyword, 'both')
						->or_like('go.keyword', $sKeyword, 'both')
						->group_end();
				}
				$dbGoods->group_end();
			} else {
				$sBindKeyword = '';
				foreach ($aKeywords as $sKeyword) {
					$sBindKeyword .= ' +"' . $this->db->escape_str($sKeyword) . '"';
				}
				$sBindKeyword = trim($sBindKeyword);

				if ($sBindKeyword) {
					$dbGoods->where("MATCH(`go`.`goods_name`, `go`.`keyword`) AGAINST('" . $sBindKeyword . "' IN BOOLEAN MODE)", null, false);
				}
			}
		}

		// 판매자
		if ($aParams['provider']) {
			$dbGoods->where('go.provider_seq', $aParams['provider']);
		}

		// 최소 금액
		if ($aParams['min_price']) {
			$dbGoods->where('go.default_price >=', $aParams['min_price']);
		}

		// 최대 금액
		if ($aParams['max_price']) {
			$dbGoods->where('go.default_price <=', $aParams['max_price']);
		}

		// 상품 색상
		if ($aParams['color']) {
			$sColor = $this->db->escape(implode('|', $aParams['color']));
			$dbGoods->where('`go`.`color_pick` REGEXP ', $sColor, false);
		}

		// 해당 상품 배송 그룹
		if ($aParams['shop_group']) {
			$dbGoods->where('go.shipping_group_seq', $aParams['shop_group']);
		}

		// 검색 모드
		if ($aParams['searchMode'] == 'best') {
			$rSubQuery = $this->db->select('goods_seq')
				->from('fm_goods')
				->where('goods_type', 'goods')
				->where('provider_status', '1')
				->where('goods_view', 'look')
				->where_in('goods_status', $aGoodsStatusWhere)
				->order_by('purchase_ea', 'desc')
				->limit($aParams['searchLimit'])
				->get();
			foreach ($rSubQuery->result_array() as $aSubData) {
				$in_goods_seq[] = $aSubData['goods_seq'];
			}
		} elseif ($aParams['searchMode'] == 'new_arrivals') {
			$rSubQuery = $this->db->select('goods_seq')
				->from('fm_goods')
				->where('goods_type', 'goods')
				->where('provider_status', '1')
				->where('goods_view', 'look')
				->where_in('goods_status', $aGoodsStatusWhere)
				->order_by('goods_seq', 'desc')
				->limit(100)
				->get();
			foreach ($rSubQuery->result_array() as $aSubData) {
				$in_goods_seq[] = $aSubData['goods_seq'];
			}
		}

		// 이벤트
		if ($aParams['event']) {
			$query = $this->db->select('goods_seq, category_code, choice_type')
				->from('fm_event_choice')
				->where_in('choice_type', ['except_goods','except_category','category','goods'])
				->where('event_seq', $aParams['event'])
				->get();
			foreach ($query->result_array() as $event_choice_data) {
				if ($event_choice_data['choice_type'] == 'except_goods'
						&& ! in_array($event_choice_data['goods_seq'], $not_in_goods_seq)) {
					$not_in_goods_seq[] = $event_choice_data['goods_seq'];
				}
				if ($event_choice_data['choice_type'] == 'goods'
						&& !in_array($event_choice_data['goods_seq'], $in_goods_seq)) {
					$in_goods_seq[]	= $event_choice_data['goods_seq'];
				}
				if ($event_choice_data['choice_type'] == 'except_category'
						&& !in_array($event_choice_data['category_code'], $not_in_category_code)) {
					$not_in_category_code[]	= $event_choice_data['category_code'];
				}
				if ($event_choice_data['choice_type'] == 'category'
						&& !in_array($event_choice_data['category_code'], $in_category_code)) {
					$in_category_code[]	= $event_choice_data['category_code'];
				}
			}

			//
			$query = $this->db->select('provider_list')
				->from('fm_event_benefits')
				->where('event_seq', $aParams['event'])
				->get();
			$aProviderSeq = [];
			foreach ($query->result_array() as $v) {
				$aProviderSeq = array_filter(explode('|', $v['provider_list']));
			}
			if ($aProviderSeq) {
				$dbGoods->where_in('go.provider_seq', $aProviderSeq);
			}
		}

		// 사은품 이벤트
		if ($aParams['gift']) {
			$query = $this->db->select('*')
				->from('fm_gift_choice')
				->where('gift_seq', $aParams['gift'])
				->get();
			foreach ($query->result_array() as $gift_choice_data) {
				if ($gift_choice_data['choice_type'] == 'goods'
						&& !in_array($gift_choice_data['goods_seq'], $in_goods_seq)) {
					$in_goods_seq[] = $gift_choice_data['goods_seq'];
				}
				if ($gift_choice_data['choice_type'] == 'category'
						&& !in_array($gift_choice_data['category_code'], $in_category_code)) {
					$in_category_code[] = $gift_choice_data['category_code'];
				}
			}
		}

		if ($aParams['event'] || $aParams['gift']) {
			// 이벤트 상품, 카테고리 모두 미 설정 시 상품 0개로 노출
			if (count($not_in_goods_seq) == 0 && count($in_goods_seq) == 0 && count($not_in_category_code) == 0 && count($in_category_code) == 0) {
				return;
			}
		}

		//
		if ($not_in_goods_seq) {
			$dbGoods->where_not_in('go.goods_seq', $not_in_goods_seq);
		}
		if ($in_goods_seq) {
			$dbGoods->where_in('go.goods_seq', $in_goods_seq);
		}

		// 이벤트 카테고리로 설정 시 $sQuery를 서브쿼리로 변환 2019-06-18 by hyem
		if ($not_in_category_code || $in_category_code) {
			if ($this->bGoodsCache) {
				if ($not_in_category_code) {
					$category_code = $this->db->escape(',' . implode(',|,', $not_in_category_code) . ',');
					$dbGoods->where('`go`.`category_link_code` NOT REGEXP ', $category_code, false);
				}
				if ($in_category_code) {
					$category_code = $this->db->escape(',' . implode(',|,', $in_category_code) . ',');
					$dbGoods->where('`go`.`category_link_code` REGEXP ', $category_code, false);
				}
			} else {
				if (! $is_join_category_link) {
					$dbGoods->join('`fm_category_link` `cl` USE INDEX (`ix_goods_seq_category_code`)', 'cl.goods_seq = go.goods_seq', 'inner');
				}
				if ($not_in_category_code) {
					$dbGoods->where_not_in('cl.category_code', $not_in_category_code);
				}
				if ($in_category_code) {
					$dbGoods->where_in('cl.category_code', $in_category_code);
				}
			}
		}

		//
		return '(' . $dbGoods->get_compiled_select() . ')';
	}

	public function categorysForFilter($sGoodsQuery, $category = '', $sPageMode = 'search')
	{
		// sGoodsQuery 없는 경우에는 빈 배열로 return
		if (! $sGoodsQuery) {
			return [];
		}

		//
		$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
		$sGoodsQuery = str_replace('SELECT `go`.`goods_seq`', 'SELECT `cl`.`category_code`, COUNT(`go`.`goods_seq`) `cnt`', $sGoodsQuery);
		// 카테고리 링크 테이블에 조인이 없는 경우
		if (strpos($sGoodsQuery, 'INNER JOIN `fm_category_link`') === false) {
			$sGoodsQuery = str_replace('WHERE', 'INNER JOIN `fm_category_link` `cl` USE INDEX (`ix_goods_seq_category_code`) ON `cl`.`goods_seq` = `go`.`goods_seq`' . PHP_EOL . 'WHERE', $sGoodsQuery);
		}
		$sGoodsQuery = substr($sGoodsQuery, 0, -1) . PHP_EOL . 'GROUP BY `cl`.`category_code`)';

		//
		$selectedFields = [
			'fc.position',
			'fc.left',
			'fc.category_code',
			'fc.title category_name',
		];
		$this->db->from('fm_category fc')
			->where('fc.hide', '0');

		//
		if (is_array($category)) {
			$this->db->select($selectedFields)
				->where_in('fc.category_code', $category)
				->order_by('fc.category_code', 'asc');
		} else {
			$selectedFields[] = 'fg.cnt';
			$category_level = intdiv(strlen($category), 4) + 1;

			$this->db->select($selectedFields)
				->where('fg.cnt >', 0)
				->where('fc.level', $category_level + 1)
				->order_by('fc.position asc', 'fc.left asc');
			if ($category) {
				$this->db->like('fc.category_code', $category, 'after');

				//
				$sGoodsQuery = str_replace("`cl`.`category_code` = '" . $category . "'", "`cl`.`category_code` LIKE '" . $category . "%'", $sGoodsQuery);
			}
			$this->db->join($sGoodsQuery . ' `fg`', 'fg.category_code = fc.category_code', 'inner');
		}

		//
		$query = $this->db->get();

		//
		return $query ? $query->result_array() : [];
	}

	public function locationsForFilter($sGoodsQuery, $location = '', $sPageMode = 'search')
	{
		// sGoodsQuery 없는 경우에는 빈 배열로 return
		if (! $sGoodsQuery) {
			return [];
		}

		//
		$selectedFields = [
			'fl.location_code',
			'fl.title location_name',
			'COUNT(`fl`.`location_code`) `cnt`',
		];
		$this->db->select($selectedFields)
			->from('fm_location fl')
			->join('fm_location_link fll', 'fll.location_code = fl.location_code', 'inner')
			->join($sGoodsQuery . ' `fg`', 'fg.goods_seq = fll.goods_seq', 'inner')
			->where('fl.hide', '0')
			->group_by('fl.location_code')
			->having('cnt >', 0);

		if (is_array($location)) {
			$this->db->where_in('fl.location_code', $location);
		} else {
			$location_level = intdiv(strlen($location), 4) + 1;
			$this->db->where_in('fl.level', $location_level + 1);
			if ($location) {
				$this->db->like('fl.location_code', $location, 'after');
			}
		}

		//
		$result_set = $this->db->get();

		//
		return $result_set ? $result_set->result_array() : [];
	}

	public function brandsForFilter($sGoodsQuery, $sPageMode)
	{
		// sGoodsQuery 없는 경우에는 빈 배열로 return
		if (! $sGoodsQuery) {
			return [];
		}

		//
		$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
		$sGoodsQuery = str_replace('SELECT `go`.`goods_seq`', 'SELECT DISTINCT `go`.`goods_seq`', $sGoodsQuery);

		//
		$sGoodsQuery = $this->db->select('1')
			->from($sGoodsQuery . ' fg')
			->where('`fg`.`goods_seq` = `bl`.`goods_seq`', null, false)
			->get_compiled_select();

		//
		$sGoodsQuery = $this->db->select('COUNT(1)')
			->from('fm_brand_link bl')
			->where('`bl`.`category_code` = `fb`.`category_code`', null, false)
			->where('EXISTS (' . $sGoodsQuery . ')', null, false)
			->get_compiled_select();

		//
		$selectedFields = [
			'fb.category_code brand_code',
			'fb.title brand_name',
			'(' . $sGoodsQuery . ') `cnt`',
		];
		$this->db->select($selectedFields)
			->from('fm_brand fb')
			->where('fb.hide', '0')
			->group_by('fb.category_code')
			->having('cnt >', 0)
			->order_by('cnt', 'DESC');

		//
		$iLevel = 2;
		if ($sPageMode == 'brand') {
			$iLevel = 3;
		}
		$this->db->where('fb.level', $iLevel);

		//
		$result_set = $this->db->get();

		//
		return $result_set ? $result_set->result_array() : [];
	}

	public function colorsForFilter($sGoodsQuery)
	{
		$result = [];

		//
		if ($sGoodsQuery) {
			//
			$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
			$sGoodsQuery = str_replace('SELECT `go`.`goods_seq`', 'SELECT DISTINCT `go`.`color_pick`', $sGoodsQuery);
			$sGoodsQuery = substr($sGoodsQuery, 1, -1) . " AND `go`.`color_pick` != ''";
			$query = $this->db->query($sGoodsQuery);
		} else {
			$query = $this->db->select('fg.color_pick')
				->distinct()
				->from('fm_goods fg')
				->where('fg.color_pick != ', '')
				->get();
		}

		foreach ($query->result_array() as $row) {
			$aTmp = explode(',', $row['color_pick']);
			foreach ($aTmp as $sColor) {
				if (! in_array($sColor, $result)) {
					$result[] = $sColor;
				}
			}
		}

		//
		return $result;
	}

	public function providersFilter($sGoodsQuery)
	{
		// sGoodsQuery 없는 경우에는 빈 배열로 return
		if (! $sGoodsQuery) {
			return [];
		}

		//
		$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
		$sGoodsQuery = substr($sGoodsQuery, 0, -1) . PHP_EOL . 'AND `go`.`provider_seq` = `fp`.`provider_seq`)';

		//
		$selectedFields = [
			'fp.provider_seq',
			'fp.provider_name',
		];
		$this->db->select($selectedFields)
			->from('fm_provider fp')
			->where('EXISTS ' . $sGoodsQuery, null, false);

		//
		$result_set = $this->db->get();

		//
		return $result_set ? $result_set->result_array() : [];
	}

	public function deliverysForFilter($sGoodsQuery, $aCodes, $aFilterConfig){
		if( !$aFilterConfig['freeship'] )	unset($aCodes[0]);
		if( !$aFilterConfig['abroadship'] )	unset($aCodes[1]);

		return $aCodes;
	}

	public function maxGoodsPriceFilter($sGoodsQuery)
	{
		$max_price = null;

		// sGoodsQuery 없는 경우에는 빈 배열로 return
		if (! $sGoodsQuery) {
			return $max_price;
		}

		//
		$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
		$sGoodsQuery = str_replace('SELECT `go`.`goods_seq`', 'SELECT MAX(`go`.`default_price`) `max_price`', $sGoodsQuery);
		$sGoodsQuery = substr($sGoodsQuery, 1, -1);

		//
		$query = $this->db->query($sGoodsQuery);
		$aData = $query->row_array();
		$max_price = $aData['max_price'] ?: null;

		//
		return $max_price;
	}

	protected function autoCompleteRecommRowFilter($aResultGoodsSeq, $aResult, $applypage){
		if(!$aResultGoodsSeq) return false;
		$aImagesGoods	= $this->goodsmodel->get_images($aResultGoodsSeq, 'list1');
		list($aCategoryCodesGoods, $aCategoryCodeGoods)	= $this->getCategoryCodeFromLink($aResultGoodsSeq);

		foreach($aResult as $row){
			// 카테고리정보
			$row['r_category']		= $aCategoryCodesGoods[$row['goods_seq']]['r_category'];
			$row['goods_img']		= $aImagesGoods[$row['goods_seq']]['image1'];

			//----> sale library 적용
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $row['consumer_price'];
			$param['price']						= $row['price'];
			$param['total_price']				= $row['price'];
			$param['ea']						= 1;
			$param['category_code']				= $row['r_category'];
			$param['goods_seq']					= $row['goods_seq'];
			$param['goods']						= $row;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);
			$row['price']						= $sales['result_price'];
			$this->sale->reset_init();
			//<---- sale library 적용

			// 회원 등급별 가격대체문구 출력
			$row['string_price'] = get_string_price($row);
			$row['string_price_use']	= 0;
			if	($row['string_price'] != '')	$row['string_price_use']	= 1;

			$temp_price = "";
			if ($row['string_price_use']==1) {
				$temp_price = $row['string_price'];
			} else {
				$temp_price = get_currency_price($sales['sale_price'],2);
			}
			$row['replace_price'] = $temp_price;

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$row['goods_name']	=  get_goods_pre_name($row);

			$result_recomm[] = $row;
		}
		return $result_recomm;
	}

	public function autoCompleteRecomm($sKeyword, $enddate, $member_seq, $group_seq)
	{
		$cfg_reserve = $this->reserves ?: config_load('reserve');

		// ----> sale library 적용
		$applypage = 'list';
		$param = [
			'cal_type' => 'list',
			'reserve_cfg' => $cfg_reserve,
			'member_seq' => $member_seq,
			'group_seq' => $group_seq,
		];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		// <---- sale library 적용

		$selectedFields = [
			'g.goods_seq',
			'g.goods_name',
			'g.sale_seq',
			'g.string_price_use',
			'g.string_price',
			'g.member_string_price_use',
			'g.member_string_price',
			'g.allmember_string_price_use',
			'g.allmember_string_price',
			'go.price',
			'go.consumer_price',
		];
		$query = $this->db->select($selectedFields)
			->distinct()
			->from('fm_goods g')
			->join('fm_goods_option go', 'go.goods_seq = g.goods_seq', 'inner')
			->join('fm_order_item oi', 'oi.goods_seq = g.goods_seq', 'inner')
			->join('fm_goods_export_item gei', 'gei.item_seq = oi.item_seq', 'inner')
			->join('fm_goods_export ge', 'ge.export_code = gei.export_code', 'inner')
			->where('g.goods_view', 'look')
			->where('g.goods_type', 'goods')
			->where('g.goods_seq >= RAND() * (SELECT MAX(`goods_seq`) FROM `fm_goods`)', null, false)
			->like('g.goods_name', $sKeyword, 'both')
			->where('go.default_option', 'y')
			->where('ge.status >=', '75')
			->where('ge.shipping_date >=', $enddate)
			->limit(3)
			->get();

		//
		$aResult = [];
		$aResultGoodsSeq = [];
		$result_recomm = [];
		foreach ($query->result_array() as $row) {
			$aResultGoodsSeq[] = $row['goods_seq'];
			$aResult[] = $row;
		}
		if ($aResult) {
			$result_recomm = $this->autoCompleteRecommRowFilter($aResultGoodsSeq, $aResult, $applypage);
		}

		//
		if (! $result_recomm) {
			$query = $this->db->select($selectedFields)
				->from('fm_goods g')
				->join('fm_goods_option go', 'go.goods_seq = g.goods_seq', 'inner')
				->where('g.goods_view', 'look')
				->where('g.goods_type', 'goods')
				->where('g.goods_seq >= RAND() * (SELECT MAX(`goods_seq`) FROM `fm_goods`)', null, false)
				->where('go.default_option', 'y')
				->limit(3)
				->get();

			//
			$aResult         = [];
			$aResultGoodsSeq = [];

			foreach ($query->result_array() as $row) {
				$aResultGoodsSeq[] = $row['goods_seq'];
				$aResult[] = $row;
			}
			if ($aResult) {
				$result_recomm = $this->autoCompleteRecommRowFilter($aResultGoodsSeq, $aResult, $applypage);
			}
		}
		return $result_recomm;
	}

	public function getFilterConfig($sSetMode, $aParams=array()){

		$this->resetFilterConfig();

		$sPageType = $this->aPageType[$sSetMode];
		if(!$sPageType) return false;

		if( $sPageType == 'mshop' ){
			$aTmp['search_filter']	= $aParams['minishop_search_filter'];
			$aTmp['orderby']		= $aParams['minishop_orderby'];
			$aTmp['status']			= $aParams['minishop_status'];
		}else if( $sPageType == 'sales_event' ){
			$aTmp['search_filter']	= $aParams['search_filter'];
			$aTmp['orderby']		= $aParams['search_orderby'];
			$aTmp['status']			= $aParams['search_status'];
		}else if( $sPageType == 'gift_event' ){
			$aTmp['search_filter']	= $aParams['search_filter'];
			$aTmp['orderby']		= $aParams['search_orderby'];
			$aTmp['status']			= $aParams['search_status'];
		}else{
			$aTmp	= config_load($sPageType);
		}

		if( $aTmp['search_filter'] ){
			$sSearchFilter	= $aTmp['search_filter'];
		}
		if( $aTmp['orderby'] ){
			$sOrderby		= $aTmp['orderby'];
		}
		if( $aTmp['status'] ){
			$sStatus		= $aTmp['status'];
		}
		if( $sPageType == 'bestproduct' ){	// 베스트 정렬
			$sOrderby		= 'sales';
		}
		if( $sPageType == 'newproduct' ){	// 신상품 정렬
			$sOrderby		= 'new';
		}

		if( $sSearchFilter ){
			$this->setFilterConfig($sSearchFilter, $sSetMode);
		}else{
			$this->loadFilterConfig($sPageType);
		}

		if( $sOrderby ){
			$this->aFilterConfig['orderby']	= $this->aOrderby[$sOrderby];
		}

		if( $sStatus ){
			if( preg_match('/runout/', $sStatus) ){
				$this->aFilterConfig['runout']	= true;
			}
			if( preg_match('/purchasing/', $sStatus) ){
				$this->aFilterConfig['purchasing']	= true;
			}
			if( preg_match('/unsold/', $sStatus) ){
				$this->aFilterConfig['unsold']	= true;
			}
		}

		$this->aFilterConfig['searchFilterUse']	= 0;
		if($this->aFilterConfig['freeship'])	$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['abroadship'])	$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['rekeyword'])	$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['price'])		$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['color'])		$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['seller'])		$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['brand'])		$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['location'])	$this->aFilterConfig['searchFilterUse'] = 1;
		if($this->aFilterConfig['category'])	$this->aFilterConfig['searchFilterUse'] = 1;
	}

	public function goodsListTotal($sGoodsQuery)
	{
		$data['cnt'] = 0;

		//
		if ($sGoodsQuery) {
			//
			$sGoodsQuery = str_replace('DISTINCT ', '', $sGoodsQuery);
			$sGoodsQuery = str_replace('SELECT `go`.`goods_seq`', 'SELECT COUNT(`go`.`goods_seq`) `cnt`', $sGoodsQuery);
			$sGoodsQuery = substr($sGoodsQuery, 1, -1);

			//
			$query = $this->db->query($sGoodsQuery);
			$data = $query->row_array();
		}

		//
		return $data['cnt'];
	}
}
