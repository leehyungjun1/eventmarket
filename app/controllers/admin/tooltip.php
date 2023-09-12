<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class tooltip extends admin_base {

	public function __construct() {
		parent::__construct();
	}

	// member
	public function member()
	{
		$snssocial = config_load('snssocial');

		$snsinfo = array();

		$snsinfo['카카오싱크'] = array("email"=>1,"name"=>1,"sex"=>1,"birthday"=>1,"nickname"=>1,"cellphone"=>1,"address"=>1);

		if($snssocial['mode_ks'] != 'SYNC' && $snssocial['key_k']) {	
			$snsinfo['카카오로그인'] = array("email"=>2,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>1,"cellphone"=>0,"address"=>0);
		}

		$snsinfo['네이버']	= array("email"=>2,"name"=>2,"sex"=>2,"birthday"=>2,"nickname"=>2,"cellphone"=>0,"address"=>0);
		$snsinfo['페이스북']= array("email"=>2,"name"=>1,"sex"=>1,"birthday"=>1,"nickname"=>0,"cellphone"=>0,"address"=>0);
		$snsinfo['트위터'] = array("email"=>0,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>0,"cellphone"=>0,"address"=>0);
		$snsinfo['애플']	= array("email"=>2,"name"=>1,"sex"=>0,"birthday"=>0,"nickname"=>0,"cellphone"=>0,"address"=>0);
		$codeList = array(
			'쇼핑몰 이용약관'=>'policy_agreement',
			'개인정보처리방침'=>'policy_privacy',
			'[회원가입] 개인정보 수집 및 이용(필수)'=>'policy_joinform',
			'[회원가입] 개인정보 수집 및 이용(선택)'=>'policy_joinform_option',
			'[회원가입] 마케팅 및 광고 활용 동의'=>'policy_marketing',
			'[비회원 주문] 개인정보 수집 및 이용'=>'policy_order',
			'[비회원 게시글 작성] 개인정보 수집 및 이용'=>'policy_board',
			'[비회원 댓글 작성] 개인정보 수집 및 이용'=>'policy_comment',
			'[재입고 알림] 개인정보 수집 및 이용'=>'policy_restock',
			'청약철회 관련 방침'=>'policy_cancellation',
			'[주문] 개인정보 제3자 제공에 대한 동의'=>'policy_third_party',
			'[주문] 개인정보의 취급위탁에 대한 동의'=>'policy_delegation'
		);
		$this->template->assign(array('codeList'=>$codeList));
		$this->template->assign(array('snsinfo'=>$snsinfo));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//bank
	public function bank()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//cache
	public function cache()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//delivery_company
	public function delivery_company()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//goods
	public function goods()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//manager
	public function manager()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//multi
	public function multi()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//operating
	public function operating()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//order
	public function order()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//pg
	public function pg()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//protect
	public function protect()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//reserve
	public function reserve()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//sale
	public function sale()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//search
	public function search()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//seo
	public function seo()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//shipping_group
	public function shipping_group()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//snsconf
	public function snsconf()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//video
	public function video()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//o2o
	public function o2o()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//push_history
	public function push_history()
	{
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function coupon(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function promotion_coupon(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function marketing(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function statistic(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function design(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function provider(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function mobile_app(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function market_connector(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function accountall(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}
	
	public function g_member(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function category(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}
	
	public function page_manager(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function excel(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function scm(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function board(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl"); 
	}

	public function imagestore(){
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl"); 
	}
}

/* End of file main.php */
/* Location: ./app/controllers/admin/tooltip.php */