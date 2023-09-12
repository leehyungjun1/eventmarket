<?php

/*
	이미지호스팅 기능별 저장소 설정 fm_imagestore 정의
*/

$config['imagestore_division'] = array( 
    'goods_headquaters' => '상품등록',
    'goods_provider' => '상품등록(입점사)',
    'goods_page_view' => '상품페이지조회</br>(카테고리/브랜드/지역)',
    'design' => '디자인 편집',
    'promotion' => '프로모션'
);

// 구분 rowspan 되어야하는 갯수
$config['imagestore_division_rowspan'] = array(
    'goods_headquaters' => '3',
    'goods_provider' => '3',
    'goods_page_view' => '3',
    'design' => '2',
    'promotion' => '2'
);

$config['imagestore_item'] = array(
    'goods_image' => '상품 사진',
    'contents' => '상품 설명',
    'common_info' => '공용 정보',
    'category_banner' => '배너 관리',
    'category_navigation' => '카테고리네비게이션',
    'all_navigation' => '전체네비게이션',
    'image' => '이미지 넣기',
    'slide_banner' => '슬라이드배너 넣기',
    'event_banner' => '할인이벤트(이벤트배너)',
    'event_thumnail' => '할인이벤트(이벤트썸네일)'
);

// 구분 기준 값(rowspan 되는) imagestore_item
$config['imagestore_item_default'] = array(
    'goods_image',
    'category_banner',
    'image',
    'event_banner',
);

$config['imagestore_directory'] = array(
    'goods_image' => '/data/goods/%입점사번호%/%이미지 등록날짜(년)%/%이미지 등록날짜(월)%/',
    'contents' => '/data/editor/goods/%입점사번호%/%상품 등록날짜(년)%/%상품 등록날짜(월)%/',
    'common_info' => '/data/editor/goods/%입점사번호%/%상품 등록날짜(년)%/%상품 등록날짜(월)%/',
    'category_banner' => '/data/editor/',
    'category_navigation' => '/data/editor/',
    'all_navigation_light' => '/data/editor/',
    'all_navigation_heavy' => '/data/category/',
    'image' => '/data/skin/%스킨명%/images/',
    'slide_banner_light' => '/data/skin/%스킨명%/images/banner/%배너번호%/',
    'slide_banner_heavy' => '/data/skin/%스킨명%/images/banner/%배너번호%/',
    'event_banner_light' => '/data/editor/',
    'event_banner_heavy' => '/data/event/',
    'event_thumnail' => '/data/event/'
);


# 기능별 저장소별 DB 업데이트 정의
// 상품 사진
$goods_image['update_type'] = 'directory';
$goods_image['table'] = 'fm_goods_image';
$goods_image['select']['key'] = ['image'];
$goods_image['where']['goods_headquaters'] = 'goods_seq in (select goods_seq from fm_goods where provider_seq = 1 )';
$goods_image['where']['goods_provider'] = 'goods_seq in (select goods_seq from fm_goods where provider_seq != 1 )';
$config['imagestore_db']['goods_image'][] = $goods_image;

// 상품 설명
$contents['update_type'] = 'editor';
$contents['table'] = 'fm_goods';
$contents['select']['key'] = ['contents', 'mobile_contents'];
$contents['where']['goods_headquaters'] = 'provider_seq = 1 ';
$contents['where']['goods_provider'] = 'provider_seq != 1 ';
$config['imagestore_db']['contents'][] = $contents;

// 공통 정보
$common_info['update_type'] = 'editor';
$common_info['table'] = 'fm_goods_info';
$common_info['select']['key'] = ['info_value'];
$common_info['where']['goods_headquaters'] = 'info_provider_seq = 1 ';
$common_info['where']['goods_provider'] = 'info_provider_seq != 1 ';
$config['imagestore_db']['common_info'][] = $common_info;


// 상품페이지조회-배너관리
$category_banner['update_type'] = 'editor';
$category_banner['table'] = 'fm_category';
$category_banner['select']['key'] = ['top_html'];
$config['imagestore_db']['category_banner'][] = $category_banner;

$brand_banner['update_type'] = 'editor';
$brand_banner['table'] = 'fm_brand';
$brand_banner['select']['key'] = ['top_html'];
$config['imagestore_db']['category_banner'][] = $brand_banner;

$location_banner['update_type'] = 'editor';
$location_banner['table'] = 'fm_location';
$location_banner['select']['key'] = ['top_html'];
$config['imagestore_db']['category_banner'][] = $location_banner;

// 상품페이지조회-카테고리 네비게이션
$category_navigation['update_type'] = 'editor';
$category_navigation['table'] = 'fm_category';
$category_navigation['select']['key'] = ['node_banner'];
$config['imagestore_db']['category_navigation'][] = $category_navigation;

$brand_navigation['update_type'] = 'editor';
$brand_navigation['table'] = 'fm_brand';
$brand_navigation['select']['key'] = ['node_banner'];
$config['imagestore_db']['category_navigation'][] = $brand_navigation;

$location_navigation['update_type'] = 'editor';
$location_navigation['table'] = 'fm_location';
$location_navigation['select']['key'] = ['node_banner'];
$config['imagestore_db']['category_navigation'][] = $location_navigation;

// (반응형)상품페이지조회-전체 네비게이션
$all_navigation_category_light['update_type'] = 'editor';
$all_navigation_category_light['table'] = 'fm_category';
$all_navigation_category_light['select']['key'] = ['node_gnb_banner'];
$config['imagestore_db']['all_navigation_light'][] = $all_navigation_category_light;

$all_navigation_brand_light['update_type'] = 'editor';
$all_navigation_brand_light['table'] = 'fm_brand';
$all_navigation_brand_light['select']['key'] = ['node_gnb_banner'];
$config['imagestore_db']['all_navigation_light'][] = $all_navigation_brand_light;

$all_navigation_location_light['update_type'] = 'editor';
$all_navigation_location_light['table'] = 'fm_location';
$all_navigation_location_light['select']['key'] = ['node_gnb_banner'];
$config['imagestore_db']['all_navigation_light'][] = $all_navigation_location_light;

// (전용)상품페이지조회-전체 네비게이션
$all_navigation_category_heavy['update_type'] = 'directory';
$all_navigation_category_heavy['table'] = 'fm_category';
$all_navigation_category_heavy['select']['key'] = ['node_gnb_image_normal', 'node_gnb_image_over'];
$config['imagestore_db']['all_navigation_heavy'][] = $all_navigation_category_heavy;

$all_navigation_brand['update_type'] = 'directory';
$all_navigation_brand['table'] = 'fm_brand';
$all_navigation_brand['select']['key'] = ['node_gnb_image_normal', 'node_gnb_image_over'];
$config['imagestore_db']['all_navigation_heavy'][] = $all_navigation_brand;

$all_navigation_location['update_type'] = 'directory';
$all_navigation_location['table'] = 'fm_location';
$all_navigation_location['select']['key'] = ['node_gnb_image_normal', 'node_gnb_image_over'];
$config['imagestore_db']['all_navigation_heavy'][] = $all_navigation_location;


// (반응형)디자인 편집-슬라이드 배너 넣기
$slide_banner_light['update_type'] = 'file';
$slide_banner_light['table'] = 'fm_design_banner_item';
$slide_banner_light['select']['key'] = ['image'];
$slide_banner_light['select']['local_diractory'] = ["'data/skin/', skin, '/'"];
$config['imagestore_db']['slide_banner_light'][] = $slide_banner_light;

// (전용)디자인 편집-슬라이드 배너 넣기
$slide_banner_heavy['update_type'] = 'file';
$slide_banner_heavy['table'] = 'fm_design_banner_item';
$slide_banner_heavy['select']['key'] = ['image'];
$slide_banner_heavy['select']['local_diractory'] = ["'data/skin/', skin, '/'"];
$config['imagestore_db']['slide_banner_heavy'][] = $slide_banner_heavy;


// (반응형)프로모션-할인이벤트(이벤트배너)
$event_banner_light['update_type'] = 'editor';
$event_banner_light['table'] = 'fm_event';
$event_banner_light['select']['key'] = ['event_page_banner'];
$config['imagestore_db']['event_banner_light'][] = $event_banner_light;

// (전용)프로모션-할인이벤트(이벤트배너)
$event_banner_heavy['update_type'] = 'file';
$event_banner_heavy['table'] = 'fm_event';
$event_banner_heavy['select']['key'] = ['banner_filename'];
$event_banner_heavy['select']['local_diractory'] = ["'data/event/'"];
$config['imagestore_db']['event_banner_heavy'][] = $event_banner_heavy;

// 프로모션-할인이벤트(이벤트 썸네일)
$event_thumnail['update_type'] = 'file';
$event_thumnail['table'] = 'fm_event';
$event_thumnail['select']['key'] = ['event_banner', 'm_event_banner'];
$event_thumnail['select']['local_diractory'] = ["'data/event/'", "'data/event/'"];
$config['imagestore_db']['event_thumnail'][] = $event_thumnail;


?>