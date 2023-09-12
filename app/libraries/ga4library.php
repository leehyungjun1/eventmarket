<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * GA4 연동 라이브러리
 * 2021-08-13
 */
class ga4library
{
    public $allow_exit = true;
    public $ga4;
    public $script;

    function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->helper('common');
        $this->ga4_auth = $this->CI->ga4_auth;
        $this->currency = $this->CI->config_system['basic_currency'];
    }

    function check_base_validation(){

        return (!preg_match('/admin\//',$CI->uri->uri_string));
    }

    /**
     * 공통 패치
     */ 
    function common_fetch($html='',$script_name='',$assign_data=array()){

        $script = '';
        
        // 원본 경로
        $ori_dir = [
            'template_dir' => $this->CI->template->template_dir,
            'compile_dir' => $this->CI->template->compile_dir
        ];
        
        // GA4 경로
        $ga4_dir = [
            'template_dir' => BASEPATH."../partner/ga4",
            'compile_dir' => BASEPATH."../_compile/"
        ];

        $is_file_exist = file_exists($ga4_dir['template_dir'].'/'.$html);

        try {

            if ($html == '' && $script_name == ''){
                return '';
            }   

            if ($is_file_exist) { // 해당 파일이 존재하는지 체크 
                    
                // GA4일때만 경로를 변경
                $this->CI->template->template_dir = $ga4_dir['template_dir'];
                $this->CI->template->compile_dir = $ga4_dir['compile_dir'];
                $this->CI->template->define(array($script_name => $html ));
                    
                // 데이터가 있을때 전달
                if (!empty($assign_data)){
                    $this->CI->template->assign('data', $assign_data);
                }

                // GA4 스크립트 string 출력
                $script	= $this->CI->template->fetch($script_name);

                //string 변환 후 경로 원복
                $this->CI->template->template_dir = $ori_dir['template_dir'];
                $this->CI->template->compile_dir = $ori_dir['compile_dir'];
                    
            }
            return $script;
        } catch (Exception $e) { 
            $script = '';
            return $script;
        }
        return $script;
    }

    // 공통 파라미터
    function common_parameters($goods_seq=0,$options='',$product=array(),$result_price=0,$tax='tax',$quality=0 ) {
        
        $item = '';
        $discount = 0; // 할인금액

        // 옵션 생성    
        $goods_option = '';
        if (is_array($options) && !empty($options)){
            foreach($options as $option){
                for($i=1;$i<5;$i++){
                    if($goods_option &&  $option['option'.$i])  $goods_option .= "/";
                        $goods_option .=  $option['option'.$i];
                }   
            }
        } else {
            for($i=1;$i<5;$i++){
                if($goods_option &&  $product['option'.$i])  $goods_option .= "/";
                    $goods_option .=  $product['option'.$i];
            }     
        }         
        
        // 브랜드 && 카테고리 생성
        if ($goods_seq != 0 || $goods_seq != null){
            $brandCategory = get_brand_category_arr($goods_seq);
        }
        // 만약 과세가 되는 상품일 경우
        if ($result_price != '' && $result_price != 0 && $tax == 'tax'){
            $price = $this->tax_calculator($result_price,$quality,$tax);
        } else {
            $price = $result_price;
        }

        // 반환할 세부 항목
        $item = [
            'item_id'   =>  $goods_seq,
            'item_name' =>  $product['goods_name'],
            'affiliation'   =>  ($product['provider_name'] == null) ? '' : $product['provider_name'],
            'currency'  =>  $this->currency,
            'discount'  =>  (int) $discount,
            'item_brand'    =>  ($brandCategory['brandData'] == null) ? '': $brandCategory['brandData'],
            'item_category' =>  ($brandCategory['categoryData'] == null) ? '': $brandCategory['categoryData'],
            'item_variant'  =>  $goods_option,
            'price' =>  (int) $price,
            'quantity'  =>  (int) $quality,
        ];
        return $item;

    }

    // 공통 과세제외하고 금액 계산
    function tax_calculator($original_price=0,$ea=1,$tax='') {

        $price = 0;   

        //할인된 개별 가격 합계
        $op_price = 0;
        $op_price = $original_price;
        $price = floor($op_price/$ea);

        // 과세
        if ($tax == 'tax') {
            
            //세금빼고 개별가격
            $price = floor(round(($op_price/$ea)/1.1));
        }

        return $price;

    }

    // 공통 스크립트
    function init($ret){
  
        // 초기값 선언
        $script = ($ret) ? $ret : '';
    
        $assign_data = [];
        $init_script = '';

        // 추가 스크립트 
        $extend_script = implode($this->script, "");

        if ($this->ga4_auth['ga4_visit'] == 'Y') {
            $assign_data['id'] = $this->ga4_auth['ga4_id'];

            $init_script = $this->common_fetch('ga4_init.html','init', $assign_data);
        }

        if ($init_script == '') {
            return $ret;
        }

        $script = $ret.$init_script.$extend_script;
        
        return $script;

    }

    // 결제하기 버튼 클릭시 ( 결제 직전 단계에서 전송 )
    function begin_checkout($order=array(),$cart=array()){
        
        // 반환 스크립트 선언
        $this->script['begin_checkout']= '';

        if ($this->check_base_validation()){
            try {
                
                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];
                $items_list = [];

                // 데이터가 없을 경우 예외 처리
                if (empty($cart) || empty($order)) {
                    return '';
                }
                foreach($cart as $cart_list){

                    $item = $this->common_parameters($cart_list['goods_seq'],'',$cart_list,$cart_list['tot_result_price'],$cart_list['tax'],$cart_list['ea']);
                    
                    $item['discount'] = (int) $cart_list['cart_sale']; // 할인금액
                    array_push($items_list,$item);
                }; 
                $assign_data = [
                    'currency'  =>  $this->currency,
                    'items' =>  json_encode($items_list,JSON_UNESCAPED_UNICODE),
                    'value' =>  $order['settle_price']
                ];

                $this->script['begin_checkout'] = $this->common_fetch('ga4_begin_checkout.html','begin_checkout',$assign_data); 
                return $this->script['begin_checkout'];

            } catch(Exception $e) {
                $this->script['begin_checkout'] = '';
                return $this->script['begin_checkout'];
            }
        }
        return $this->script['begin_checkout'];
    }


    // 결제 완료시 (사용자 결제 처리완료시) 
    function purchase($shipping_group_items = array(), $orders = array()){
        
        // 반환 스크립트 선언
        $this->script['purchase'] = '';

        if ($this->check_base_validation()) {
            
            try {
                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];
                $items_list = [];
                
                if (!empty($shipping_group_items) && !empty($orders)) {
    
                    if ($orders['settleprice'] == ''){
                        $orders['settleprice'] = 0;
                    } 
                        
                    $total_tax = 0;

                    foreach($shipping_group_items as $shipping_group){
                        foreach($shipping_group['items'] as $item){

                            //할인된 개별 가격 합계
                            $op_price = 0;
                            foreach($item['options'] as $option){

                                // 주문 상품별 가격
                                $op_price += $option["tot_sale_price"];
                                // 결제 완료 이벤트 발생시 할인값
                                if (!empty($option['tot_event_sale'])) {
                                    if (!empty($option['tot_member_sale'])) {
                                        $total_event_discount = $option['tot_event_sale'] + $option['tot_member_sale'];
                                        $discount += (int) ($total_event_discount/count($item['options']));
                                    } else {
                                        $discount = (int) ($option['tot_event_sale']/count($item['options']));
                                    }
                                
                                }
                            }
                            
                            //총 결제 과세
                            if($item["tax"] == "tax"){
                                //세금빼고 개별가격
                                $price = floor(round(($op_price/$item['tot_ea'])/1.1));
                                $total_tax += ($op_price-($price*$item['tot_ea']));
                            }

                            $items = $this->common_parameters($item['goods_seq'],$item['options'],$item,$op_price,$item['tax'],$item['tot_ea']);
                            $items['discount'] = $discount;
                            array_push($items_list,$items);
                                    
                        }
                    }

                    $order_price = $orders['tot_shipping_cost'];
                    
                    //과세상품이 하나라도 있을 경우 배송비도 과세
                    if($total_tax > 0){
                        $order_price = round($orders['tot_shipping_cost']/1.1);
                        $total_tax += ($orders['tot_shipping_cost']-$order_price);
                    }
                    
                    $assign_data['currency'] = $this->currency;
                    $assign_data['affiliation'] = $orders['referer_name'];
                    $assign_data['transaction_id'] = $orders['order_seq'];
                    $assign_data['value'] = $orders['original_settleprice'];    
                    $assign_data['shipping'] = $order_price;
                    $assign_data['tax'] = $total_tax;
                    $assign_data['items'] = json_encode($items_list,JSON_UNESCAPED_UNICODE);
                    
                    $this->script['purchase'] = $this->common_fetch('ga4_purchase.html','purchase',$assign_data);  
                    return $this->script['purchase'];
                } else {
                    throw new Exception ('주문 완료 오류');
                }

                
            } catch(Exception $e) {
                $this->script['purchase'] = '';
                return $this->script['purchase'];
            }
        }
        
    }

    // 제품 상세페이지 보기
    function view_item($product = array()){

        // 반환 스크립트 선언
        $this->script['view_item'] = '';

        if ($this->check_base_validation()){
            try {

                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];                
                
                // 데이터가 없을 경우 예외 처리
                if (empty($product)) {
                    return '';
                }
                
                $product['goods']['provider_name'] = $product['provider_name'];
                $item = $this->common_parameters($product['goods']['goods_seq'],$product['option'],$product['goods'],$product['goods']['sales']['result_price'],$product['goods']['tax'],1);
                $item['discount'] = (int) $product['goods']['sales']['sale_list']['event'] + $product['goods']['sales']['sale_list']['member']; // 할인금액
                $item['item_name']  =   addslashes($item['item_name']);
                $assign_data = [
                    'items' => json_encode($item,JSON_UNESCAPED_UNICODE),
                    'currency' => $this->currency,
                    'value' => $product['goods']['sales']['result_price']
                ];

                $this->script['view_item'] = $this->common_fetch('ga4_view_item.html','view_item',$assign_data);  
                return $this->script['view_item'];

            } catch(Exception $e) {
                $this->script['view_item'] = '';
                return $this->script['view_item'];
            }
        }
        return $this->script['view_item'];
    }

    // 제품 목록 조회시
    function view_item_list($product = array()){

        // 반환 스크립트 선언
        $this->script['list'] = '';

        if ($this->check_base_validation()){
            try {
                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];
                $items_list = [];

                // 데이터가 없을 경우 예외 처리
                if (empty($product)){
                    return '';
                };
                foreach($product as $goods) {
                    $item = $this->common_parameters($goods['goods_seq'],'',$goods,$goods['sale_price'],$goods['tax'],1);
                    $item['discount']   =   (int) $goods['sale_list']['event'] + $goods['sale_list']['member']; // 할인금액
                    $item['item_name']  =   addslashes($item['item_name']);
                    array_push($items_list,$item);
                };
                $assign_data['items'] = json_encode($items_list,JSON_UNESCAPED_UNICODE);
                $assign_data['items'] = str_replace("\\\\'","\\'", $assign_data['items']);
                $this->script['list'] = $this->common_fetch('ga4_list.html','list', $assign_data);  
                return $this->script['list'];

            } catch(Exception $e) {
                $this->script['list'] = '';
                return $this->script['list'];
            }


        }
        
    }

    // 상품 클릭시
    function select_item(){

        // 반환 스크립트 선언
        $this->script['select_item'] = '';
        
        if ($this->check_base_validation()){
            try {
                $this->script['select_item'] = $this->common_fetch('ga4_select_item.html','select_item');  
                return  $this->script['select_item'];    
            } catch(Exception $e) {
                $this->script['select_item'] = '';
                return $this->script['select_item'];
            }
        }

        return $this->script['select_item'];
    }
    

    // 장바구니에 상품 추가시
    function add_to_cart($product = array()){

         // 반환 스크립트 선언
        $this->script['add_to_cart'] = '';

        if ($this->check_base_validation()) {
            
            try {

                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];

                // 데이터가 없을 경우 예외 처리
                if (empty($product)) {
                    return '';
                }

                $product['goods']['provider_name'] = $product['provider_name'];
                $item = $this->common_parameters($product['goods']['goods_seq'],$product['option'],$product['goods'],$product['goods']['sales']['result_price'],$product['goods']['tax'],1);                
                $item['discount'] = (int) $product['goods']['sales']['sale_list']['event'] + $product['goods']['sales']['sale_list']['member']; // 할인금액
                $item['item_name']  =   addslashes($item['item_name']);
                $assign_data = [
                    'tax'   =>  ($product['goods']['tax'] == null) ? '' : $product['goods']['tax'],
                    'items' =>  json_encode($item,JSON_UNESCAPED_UNICODE),
                    'currency'  =>  $this->currency,
                    'value' =>  $product['goods']['sales']['result_price']
                ];
                $this->script['add_to_cart'] = $this->common_fetch('ga4_add_to_cart.html','add_to_cart',$assign_data);
                return $this->script['add_to_cart']; 

            } catch(Exception $e) {
                $this->script['add_to_cart'] = '';
                return $this->script['add_to_cart'];
            }
        }
        return $this->script['add_to_cart'];
    }

    // 프로모션 클릭시
    function select_promotion($product=array()){

        // 반환 스크립트 선언
        $this->script['select_promotion'] = "";
                
        if ($this->check_base_validation()){

            try {

                // 스크립트내에서 사용될 데이터값 선언
                $assign_data =[];
                $event_info = [];
    
                // 전용스킨 상세페이지에서 클릭시
                if (!empty($product)) {
    
                    // 이벤트 구분
                    $event_info = [
                        'promotion_id'  =>  $product['event_seq'],
                        'creative_slot' =>  '관련이벤트',
                        'promotion_name'    =>  $product['title'],
                        'creative_name' =>  $product['tpl_path']
                    ];
                    
                    $assign_data['event'] = json_encode($event_info,JSON_UNESCAPED_UNICODE);
                }
                
                $this->script['select_promotion'] = $this->common_fetch('ga4_select_promotion.html','select_promotion',$assign_data);  
                return $this->script['select_promotion'];
            
            } catch(Exception $e) {
                $this->script['select_promotion'] = '';
                return $this->script['select_promotion'];
            }            
        }
        return $this->script['select_promotion'];
    }

    // 프로모션 노출시
    // event_type 어디로 이벤트를 접근했는지
    function view_promotion($creative_slot='',$event=array(),$product=array(),$event_type=''){
        // 반환 스크립트 선언
        $this->script['view_promotion'] = "";
        
        if ($this->check_base_validation()) {
            
            try {

                // 스크립트내에서 사용될 데이터값 선언
                $assign_data = [];
                $event_info = [];

                // 데이터가 없을 경우 예외 처리
                if (empty($event) && empty($product)) {
                    return '';
                }
        
                // 관련이벤트 && 단독 이벤트가 아닐때 수집 (리스트로 호출할때)
                if ($creative_slot == '관련이벤트') {
                            
                    $event_list = [];
                    foreach($event as $event_item) {
                        $event_info = [
                            'promotion_id'  =>  $event_item[$event_item['event_type'].'_seq'],
                            'promotion_name'    =>  $event_item['title'],
                            'creative_slot' =>  $creative_slot, 
                            'creative_name' =>  $event_item['tpl_path']
                        ];
                        array_push($event_list,$event_info);
                    }
                    $item = $this->common_parameters($product['goods']['goods_seq'],$product['option'],$product['goods'],$product['goods']['sales']['result_price'],$product['goods']['tax'],1);
                    $item['discount'] = (int) $product['goods']['sales']['sale_list']['event'] + $product['goods']['sales']['sale_list']['member']; // 할인금액
                    $item['affiliation'] = $product['provider_name'];    
                    $item['item_name']  =   addslashes($item['item_name']);
                    $assign_data = [
                        'event' =>  json_encode($event_list,JSON_UNESCAPED_UNICODE),
                        'items' =>  json_encode($item ,JSON_UNESCAPED_UNICODE)
                    ];
                    
                } else { // 직접이벤트
        
                    $items_list = [];
                    $event_info = [
                        'promotion_id'  =>  $event[$event_type.'_seq'],
                        'promotion_name'    =>  $event['title'],
                        'creative_slot' =>  $event_type.'_view', 
                        'creative_name' =>  $event['tpl_path']
                    ];
                    // 상품리스트
                    foreach($product as $goods) {
                        $item = $this->common_parameters($goods['goods_seq'],'',$goods,$goods['sale_price'],$goods['tax'],1);  
                        $item['discount'] = (int) $goods['sale_list']['event'] + $goods['sale_list']['member']; // 할인금액
                        $item['item_name']  =   addslashes($item['item_name']);
                        array_push($items_list,$item);
                    };
                        
                    $assign_data = [
                        'event' =>  json_encode($event_info,JSON_UNESCAPED_UNICODE),
                        'items' =>  json_encode($items_list ,JSON_UNESCAPED_UNICODE)
                    ];
                };
        
                $assign_data['page_type'] = $creative_slot;
                $this->script['view_promotion'] = $this->common_fetch('ga4_view_promotion.html','view_promotion',$assign_data);
                return $this->script['view_promotion']; 

            } catch(Exception $e) {
                $this->script['view_promotion'] = '';
                return $this->script['view_promotion'];
            }
 
        }
        return $this->script['view_promotion'];
    }
}
?>