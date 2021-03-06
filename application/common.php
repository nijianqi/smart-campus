<?php

// +----------------------------------------------------------------------
// | Think.Admin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/Think.Admin
// +----------------------------------------------------------------------

use service\DataService;
use service\NodeService;
use Wechat\Loader;
use think\Db;
use think\Log;
use think\Request;

/**
 * 打印输出数据到文件
 * @param mixed $data
 * @param bool $replace
 * @param string|null $pathname
 */
function p($data, $replace = false, $pathname = NULL)
{
    is_null($pathname) && $pathname = RUNTIME_PATH . date('Ymd') . '.txt';
    $str = (is_string($data) ? $data : (is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true)) . "\n";
    $replace ? file_put_contents($pathname, $str) : file_put_contents($pathname, $str, FILE_APPEND);
}

/**
 * 获取微信操作对象
 * @param string $type
 * @return \Wechat\WechatReceive|\Wechat\WechatUser|\Wechat\WechatPay|\Wechat\WechatScript|\Wechat\WechatOauth|\Wechat\WechatMenu
 */
function & load_wechat($type = '')
{
    static $wechat = array();
    $index = md5(strtolower($type));
    if (!isset($wechat[$index])) {
        $config = [
            'token' => sysconf('wechat_token'),
            'appid' => sysconf('wechat_appid'),
            'appsecret' => sysconf('wechat_appsecret'),
            'encodingaeskey' => sysconf('wechat_encodingaeskey'),
            'mch_id' => sysconf('wechat_mch_id'),
            'partnerkey' => sysconf('wechat_partnerkey'),
            'ssl_cer' => sysconf('wechat_cert_cert'),
            'ssl_key' => sysconf('wechat_cert_key'),
            'cachepath' => CACHE_PATH . 'wxpay' . DS,
        ];
        $wechat[$index] = Loader::get($type, $config);
    }
    return $wechat[$index];
}

/**
 * 安全URL编码
 * @param array|string $data
 * @return string
 */
function encode($data)
{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(serialize($data)));
}

/**
 * 安全URL解码
 * @param string $string
 * @return string
 */
function decode($string)
{
    $data = str_replace(['-', '_'], ['+', '/'], $string);
    $mod4 = strlen($data) % 4;
    !!$mod4 && $data .= substr('====', $mod4);
    return unserialize(base64_decode($data));
}

/**
 * RBAC节点权限验证
 * @param string $node
 * @return bool
 */
function auth($node)
{
    return NodeService::checkAuthNode($node);
}

/**
 * 设备或配置系统参数
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function sysconf($name, $value = false)
{
    static $config = [];
    if ($value !== false) {
        $config = [];
        $data = ['name' => $name, 'value' => $value, 'company_id' => session('company_id')];
        return DataService::save('SystemConfig', $data, 'name', ['company_id' => session('company_id')]);
    }
    if (empty($config)) {
        foreach (Db::name('SystemConfig')->where(['company_id' => session('company_id')])->select() as $vo) {
            $config[$vo['name']] = $vo['value'];
        }
    }
    return isset($config[$name]) ? $config[$name] : '';
}

/**
 * 设备或配置系统参数  默认
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function sysconf_v($name, $value = false)
{
    static $config = [];
    if ($value !== false) {
        $config = [];
        $data = ['name' => $name, 'value' => $value, 'company_id' => '0'];
        return DataService::save('SystemConfig', $data, 'name', ['company_id' => '0']);
    }
    if (empty($config)) {
        foreach (Db::name('SystemConfig')->where(['company_id' => '0'])->select() as $vo) {
            $config[$vo['name']] = $vo['value'];
        }
    }
    return isset($config[$name]) ? $config[$name] : '';
}

/**
 * array_column 函数兼容
 */
if (!function_exists("array_column")) {

    function array_column(array &$rows, $column_key, $index_key = null)
    {
        $data = [];
        foreach ($rows as $row) {
            if (empty($index_key)) {
                $data[] = $row[$column_key];
            } else {
                $data[$row[$index_key]] = $row[$column_key];
            }
        }
        return $data;
    }

}

/**
 * 设备或配置系统参数
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function sysconf_name()
{
    $info = Db::name('Company_list')->where(['company_id' => session('company_id')])->find();
    if(empty($info)){
        return sysconf_v('app_name');
    }else{
        return $info['Company_Name'];
    }
}

/**
 * 设备或配置公司参数
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function companyConf($name, $value = false)
{
    static $config = [];
    if ($value !== false) {
        $config = [];
        $data = [$name => $value, 'company_id' => session('company_id')];
        return DataService::save('Company_list', $data, 'company_id', ['company_id' => session('company_id')]);
    }
    if (empty($config)) {
        $list = Db::name('Company_list')->where(['company_id' => session('company_id')])->find();
        if(!empty($list)){
            foreach ($list as $vo => $val) {
                $config[$vo] = $val;
            }
        }
    }
    return isset($config[$name]) ? $config[$name] : '';
}

/**
 * 设备或配置支付参数
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function payConf($name, $value = false)
{
    static $config = [];
    if ($value !== false) {
        $config = [];
        $data = ['param_value'=> $value];
        return Db::name('company_third_interface_param_list')->where(['company_id' => session('pay_company_id'),'param_name'=>$name])->update($data);
    }
    if (empty($config)) {
        $list = Db::name('company_third_interface_param_list')->where(['company_id' => session('pay_company_id')])->select();
        if(!empty($list)){
            foreach ($list as $vo => $val) {
                $config[$val['param_name']] = $val['param_value'];
            }
        }
    }
    return isset($config[$name]) ? $config[$name] : '';
}

/**
 * 设备或配置支付参数
 * @param string $name 参数名称
 * @param bool $value 默认是false为获取值，否则为更新
 * @return string|bool
 */
function paysConf($name, $value = false)
{
    static $config = [];
    if ($value !== false) {
        $config = [];
        $data = ['param_value'=> $value];
        return Db::name('company_third_interface_param_list')->where(['company_id' => session('company_id'),'param_name'=>$name])->update($data);
    }
    if (empty($config)) {
        $list = Db::name('company_third_interface_param_list')->where(['company_id' => session('company_id')])->select();
        if(!empty($list)){
            foreach ($list as $vo => $val) {
                $config[$val['param_name']] = $val['param_value'];
            }
        }
    }
    return isset($config[$name]) ? $config[$name] : '';
}


/**
 * POST请求https接口返回内容
 * @param  string $url [请求的URL地址]
 * @param  string $post [请求的参数]
 * @return  string
 */
function post_curls($url, $post)
{
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // Post提交的数据包
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    $res = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);//捕抓异常
    }
    curl_close($curl); // 关闭CURL会话
    return $res; // 返回数据，json格式
}

function xml_to_json($source) {
    if(is_file($source)){ //传的是文件，还是xml的string的判断
        $xml_array=simplexml_load_file($source);
    }else{
        $xml_array=simplexml_load_string($source);
    }
    $json = json_encode($xml_array);
    return $json;
}

function request_post($url = '', $post_data = array()) {
    if (empty($url) || empty($post_data)) {
        return false;
    }

    $o = "";
    foreach ( $post_data as $k => $v )
    {
        $o.= "$k=" . urlencode( $v ). "&" ;
    }
    $post_data = substr($o,0,-1);

    $postUrl = $url;
    $curlPost = $post_data;
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
    $data = curl_exec($ch);//运行curl
    curl_close($ch);

    return $data;
}

/**
 * 浙农信查询单笔交易
 */
function paySearch($TransId = 'IQSR',$MerchantId,$SubMerchantId,$MerSeqNo,$MerTransDate,$TransAmt){
    $data = [];
    $data['TransId'] = $TransId;
    $data['MerchantId'] = $MerchantId;
    $data['SubMerchantId'] = $SubMerchantId;
    $data['MerSeqNo'] = $MerSeqNo;
    $data['MerTransDate'] = $MerTransDate;
    $data['MerTransAmt'] = $TransAmt;
    $html = request_post(paysConf('InterFaceURL'),$data);
    $arr = json_decode($html,true);
    return $arr;
}

/**
 * 浙农信对账数据查询
 */
function payOrder($TransId = 'QDZF',$MerchantId,$SubMerchantId,$Field='1',$StartTime,$EndTime,$Type='3',$PageNo='1',$PageSize='40'){
    $data = [];
    $data['TransId'] = $TransId;
    $data['MerchantId'] = $MerchantId;
    $data['SubMerchantId'] = $SubMerchantId;
    $data['Field'] = $Field;
    $data['StartTime'] = $StartTime;
    $data['EndTime'] = $EndTime;
    $data['Type'] = $Type;
    $data['PageNo'] = $PageNo;
    $data['PageSize'] = $PageSize;
    $html = request_post(paysConf('InterFaceURL'),$data);
    $arr = json_decode($html,true);
    return $arr;
}

/**
 * 发送模板消息
 * @param $userOpenId
 * @param null $shortId
 * @param null $data
 * @param null $url
 */
function sendMSC($userOpenId,$shortId=null,$data=null,$url=null){
    $template=Db::table("wechat_notice_template")->where("shortId",$shortId)->where('company_id',session('company_id'))->find();
    if($template) {
        $template_id = $template['templateId'];
    }else{
        $template_id = load_wechat("Message")->addTemplateMessage($shortId);
        if(empty($template_id)){
            return false;
        }
        Db::table("wechat_notice_template")->insert(['shortId'=>$shortId,'templateId'=>$template_id,'company_id'=>session('company_id')]);
    }
    try {
        $content = [];
        $content['touser'] = $userOpenId;
        $content['template_id'] = $template_id;
        $content['url'] = $url;
        $content['data'] = $data;
        load_wechat("Message")->sendTemplateMessage($content);
    }catch (Exception $e) {
        Log::error($e->getMessage());
        Log::error($e->getTraceAsString());
    }
}

/**
 * 生成退餐审核记录发送模板信息
 * @param $openid
 */
function refundMSC($openid)
{
    sendMSC($openid,'OPENTM407446439',array(
        'first'=>array('value'=>'您好！有新发布的退餐需要您审核','color'=>'#173177'),
        'keyword1'=>array('value'=>'管理员审核'),
        'keyword2'=>array('value'=>date('Y-m-d H:m:i')),
        'remark'=>array('value'=>'点击本条信息进行操作。')
    ),Request::instance()->domain().'/index/index/checkList.html?company_id='.session('company_id'));
}

/**
 * 退餐审核记录解过发送模板信息
 * @param $openid
 */
function returnMSC($openid)
{
    sendMSC($openid,'OPENTM407446439',array(
        'first'=>array('value'=>'您好！您申请的退餐审核已有结果','color'=>'#173177'),
        'keyword1'=>array('value'=>'申请结果通知'),
        'keyword2'=>array('value'=>date('Y-m-d H:m:i')),
        'remark'=>array('value'=>'点击本条信息进行操作。')
    ),Request::instance()->domain().'/index/index/refund.html?company_id='.session('company_id'));
}

/**
 * 补餐审核记录解过发送模板信息
 * @param $openid
 */
function patchMSC($openid)
{
    sendMSC($openid,'OPENTM407446439',array(
        'first'=>array('value'=>'您好！您申请的补餐审核已有结果','color'=>'#173177'),
        'keyword1'=>array('value'=>'申请结果通知'),
        'keyword2'=>array('value'=>date('Y-m-d H:m:i')),
        'remark'=>array('value'=>'点击本条信息进行操作。')
    ),Request::instance()->domain().'/index/index/patch.html?company_id='.session('company_id'));
}

/**
 * 拒绝退餐发送模板信息
 * @param $openid
 */
function refundNoMSC($openid)
{
    sendMSC($openid,'OPENTM407446439',array(
        'first'=>array('value'=>'您好！您申请的退餐请求已被管理员拒绝','color'=>'#173177'),
        'keyword1'=>array('value'=>'申请结果通知'),
        'keyword2'=>array('value'=>date('Y-m-d H:m:i')),
        'remark'=>array('value'=>'点击本条信息进行操作。')
    ),Request::instance()->domain().'/index/index/checkList.html?company_id='.session('company_id'));
}

/**
 * 向上一个绑定的微信号发送异常消息
 * @param $openid
 */
function loginMSC($openid)
{
    sendMSC($openid,'OPENTM204805905',array(
        'first'=>array('value'=>'您好！您登录的账号在其他手机微信上登录!','color'=>'#173177'),
        'keyword1'=>array('value'=>'无'),
        'keyword2'=>array('value'=>'微信账号'),
        'keyword3'=>array('value'=>'异常登录通知'),
        'keyword4'=>array('value'=>date('Y-m-d H:m:i')),
        'remark'=>array('value'=>'请确认是否本人操作，如有疑问，请联系食堂管理员。')
    ),Request::instance()->domain().'/index/index/me.html?company_id='.session('company_id'));
}


function search_food($name)
{
    $name = substr($name, 0, 20);
    $url = "http://food.boohee.com/fb/v1/search?page=1&order_asc=desc&q=$name";  //查询
    $html = file_get_contents($url);
    $arr_search = json_decode($html, true);
    $arr = [];
    foreach($arr_search['items'] as $val){
        $food_url = 'http://food.boohee.com/fb/v1/foods/'.$val['code'].'/mode_show'; //具体食品
        $html = file_get_contents($food_url);
        $arr_img = json_decode($html, true);
        $arr[] = $arr_img['large_image_url'];
    }
    return $arr;
}
