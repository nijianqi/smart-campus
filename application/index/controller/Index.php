<?phpnamespace app\index\controller;use controller\ApiBase;use think\Db;class Index extends ApiBase{    protected $checkAuth = true;    /**     * 首页     */    public function index()    {        return $this->fetch();    }    /**     * 今日菜谱     */    public function menu()    {        return $this->fetch();    }    /**     * 个人中心     */    public function me()    {        return $this->fetch();    }    /**     * 下单     */    public function cart($day)    {        if($day <= date("Y-m-d", strtotime('+'.companyConf('dinner_before_days').'day'))){ //暂时不增加提前订单            $param['day'] = $day;            $this->redirect('@index/index/orderInfo', $param);        }        return $this->fetch();    }    /**     * 用餐记录     */    public function orderList()    {        return $this->fetch();    }    /**     * 订单详情     */    public function orderInfo()    {        return $this->fetch();    }    /**     * 个人信息     */    public function myInfo()    {        return $this->fetch();    }    /**     * 密码修改     */    public function changePw()    {        return $this->fetch();    }    /**     * 我的余额     */    public function money()    {        return $this->fetch();    }    /**     * 账户充值类型列表     */    public function rechargeList()    {        return $this->fetch();    }    /**     * 浙农账户充值     */    public function rechargeZhenong()    {        $user_info = $this->getClientUserInfo();        $TransId = 'IPEM';  //交易代码        $MerchantId = paysConf('MerSeqNo');  //商户代码        $SubMerchantId = paysConf('SubMerSeqNo'); //二级商户编号        $sqlstr = "exec [up_get_max_id] ?,?,?,?";        $max_id = Db::query($sqlstr, ['', 'Order', 'OD', 1]);        $MerDateTime = date('YmdHis',time());//交易时间        $OrderId =  'AS'.$max_id; //订单号        $MerSeqNo = 'DS'.$max_id; //商户总交易流水号        $SubMerSeqNo1 = 'wlsas'.$MerDateTime; //订单明细 商户交易流水号        $MerURL = paysConf('MerURL'); //用于后台通知商户        $MerURL1 = paysConf('MerURL1');        $AppointedPayType = paysConf('AppointedType'); //0：以支付平台默认支付方式为准（默认值）1：丰收e支付为指定的支付方式 2：银行卡支付为指定的支付方式        $this->assign('TransId', $TransId);        $this->assign('MerURL', $MerURL);        $this->assign('MerURL1', $MerURL1);        $this->assign('MerchantId', $MerchantId);        $this->assign('SubMerchantId', $SubMerchantId);        $this->assign('MerDateTime',$MerDateTime);        $this->assign('OrderId', $OrderId);        $this->assign('MerSeqNo', $MerSeqNo);        $this->assign('SubMerSeqNo1', $SubMerSeqNo1);        $this->assign('AppointedPayType', $AppointedPayType);        $this->assign('tel',$user_info['Default_Tel']);        return $this->fetch();    }    /**     * 部门管理     */    public function dept()    {        return $this->fetch();    }    /**     * 部门未订餐人员管理     */    public function deptMember()    {        return $this->fetch();    }    /**     * 部门未订餐人员下单     */    public function deptCart($day)    {        if($day <= date("Y-m-d",  strtotime('+'.companyConf('dinner_before_days').'day'))){ //暂时不增加提前订单            $this->error("无法查看部门人员超过当前时间的订单！");            $param['day'] = $day;            $this->redirect('@index/index/orderInfo', $param);        }        return $this->fetch();    }    /**     * 营养报表     */    public function report()    {        return $this->fetch();    }    /**     * weixin     */    public function wechat()    {        return $this->fetch();    }    /**     * 意见反馈     */    public function opinion()    {        return $this->fetch();    }    /**     * 系统通知列表     */    public function messageList()    {        return $this->fetch();    }    /**     * 系统通知详情     */    public function messageInfo()    {        return $this->fetch();    }}