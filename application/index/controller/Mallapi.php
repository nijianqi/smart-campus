<?php

namespace app\index\controller;

use controller\ApiBase;
use think\Db;

class MallApi extends ApiBase
{

    protected $checkAuth = true;

    /**
     * 获取首页数据
     */
    function getHomeData($time)
    {
        if (empty($time))
            $this->error("数据查询繁忙，请稍后重试");
        $firstDay = date('Y-m-d', strtotime($time));
        $sqlStr = "exec [up_emp_dinner_calendar] ?,?,?";
        $return = Db::query($sqlStr, [$this->getClientCompanyId(), $this->getClientUserId(), $firstDay]);
        $data = [];
        foreach ($return[0] as $key => $val) {
            $data[$val['day_str']][] = $val;
        }
        return json($data);
    }

    /**
     * 获取餐次信息
     */
    function getDinnerBase()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $where = [];
        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            $dinner_flag = implode(",", $dinner_flag);
            $where['dinner_flag'] = ['in', $dinner_flag];
        }

        $data['dinnerBaseList'] = Db::name('dinner_base_info')->where(['company_id' => $company_id])->where($where)->order('dinner_flag asc')->select();
        $count = Db::name('dinner_base_info')->where(['company_id' => $company_id])->where($where)->count();
        $data['dinnerBaseCount'] = $this->canteen_W($count);
        if (empty($data))
            $this->error("餐次信息获取失败，请稍候再试！");
        return json($data);
    }

    /**
     * 获取菜谱CLASS数据
     */
    function cartProductList($day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $user_info = $this->getClientUserInfo();

        if (empty($day))
            $this->error("数据查询繁忙，请稍后重试");
        $weekArray = array("日", "一", "二", "三", "四", "五", "六");
        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w", strtotime($day))], strtotime($day));

        $where = [];
        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            $dinner_flag = implode(",", $dinner_flag);
            $where['dinner_flag'] = ['in', $dinner_flag];
        }

        $dinnerBaseList = Db::name('dinner_base_info')->where($where)->where(['company_id' => $company_id])->order('dinner_flag asc')->select();
        foreach ($dinnerBaseList as $key=>$val){
            $list = Db::name('canteen_cookbook_price')->where(['company_id'=>$company_id,'dinner_flag'=>$val['dinner_flag'],'sale_datetime'=>$day])->select();
            if(!empty($list)){
                $data['dinnerBaseList'][] = $dinnerBaseList[$key];
            }
        }
        $count = count($data['dinnerBaseList']);
        $data['dinnerBaseCount'] = $this->canteen_W($count);
        if (empty($data['dinnerBaseList']))
            $this->error("餐次信息获取失败，请稍候再试！");

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])
            ->select();

        $data['price_total'] = number_format($price_total['0']['price_total'], 2);

        $data['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])
            ->count();
        $data['emp_name'] = $user_info['Emp_MircoMsg_Uid'];
        $data['user_name'] = $user_info['Emp_Name'];
        return json($data);
    }

    /**
     * 获取菜谱CART数据
     */
    function cartList($dinner, $day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $user_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('employee_list e', 'u.u_id = e.dept_id and u.company_id = e.company_id and e.emp_id = :emp_id')
            ->bind(['emp_id' => $user_id])
            ->where(['u.dept_type' => 'deptcanteen', 'u.company_id' => $company_id])
            ->field('u.dept_id')
            ->select();
        if (!empty($user_manager)) {
            foreach ($user_manager as $key => $v) {
                $canteen_no[] = $v['dept_id'];
            }
            $canteen_no = implode(",", $canteen_no);
            $where['p.canteen_no'] = ['in', $canteen_no];
        }

        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            if (!in_array($dinner, $dinner_flag)) {
                $data['cartList'] = [];
                $data['cartCount'] = '0';
                return json($data);
            }
        }

        $where['p.company_id'] = $company_id;
        $where['p.dinner_flag'] = $dinner;
        $where['p.sale_datetime'] = $day;
        $where['p.status'] = '1';
        $where['p.cookbook_remain_quantity'] = array('neq', '0');
        $data['cartList'] = Db::name('canteen_cookbook_price')//单选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=0')->select();
        foreach ($data['cartList'] as $key => $val) {
            $data['cartList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }
        $data['cartChoiceList'] = Db::name('canteen_cookbook_price')//多选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=1')->select();
        foreach ($data['cartChoiceList'] as $key => $val) {
            $data['cartChoiceList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartChoiceList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }

        $data['cartCount'] = Db::name('canteen_cookbook_price')
            ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->where($where)->count();
        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no and b.company_id = d.company_id', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag and i.company_id = d.company_id', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $day, 'd.dinner_flag' => $dinner])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $data['cookbook_name'] = $select_cookbook['cookbook_name'];
        $data['dinner_name'] = $select_cookbook['dinner_name'];

        return json($data);
    }

    /**
     * 存储商品session
     */
    function addCart()
    {
        $param = $this->request->post();
        if (empty($param))
            $this->error('商品添加失败');
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $sqlStr = "exec [up_get_remain_money] ?,?,?";
        $money = Db::query($sqlStr, [$company_id, $user_id, '10']);
        if (empty($money))
            $this->error("没有可用余额!");
        if (floatval($param['cookbook_price']) > floatval($money[0][0]['account_remain_money']))
            $this->error("余额不足!");

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        }

        $price_info = Db::name('canteen_cookbook_price')
            ->where(['price_id' => $param['price_id'], 'sale_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag'],'company_id' => $company_id])
            ->find();
        if (!empty($price_info)) {
            if (floatval($price_info['cookbook_remain_quantity']) < 1)
                $this->error("该商品已售罄!");

            $data['emp_id'] = $user_id;
            $data['company_id'] = $company_id;
            $data['canteen_no'] = $price_info['canteen_no'];
            $data['cookbook_no'] = $price_info['cookbook_no'];
            $data['dinner_flag'] = $price_info['dinner_flag'];
            $data['dinner_quantity'] = 1;
            $data['cook_money'] = $price_info['cookbook_price'];
            $data['dinner_datetime'] = $param['day'];
            $data['dinner_createtime'] = date("Y-m-d H:i:s");
            $position = Db::name('canteen_cookbook_window_position')
                ->where(['sale_datetime' => $param['day'], 'canteen_no' => $price_info['canteen_no'], 'cookbook_no' => $price_info['cookbook_no'], 'dinner_flag' => $price_info['dinner_flag'], 'company_id' => $company_id])->find();
            $data['dinner_machine_no'] = $position['windows_no'];  //窗口号
            $data['dinner_from_type'] = '微信';
            $data['dinner_status'] = 1;
            $data['dinner_choice'] = $param['is_choice'];
            Db::name('emp_cookbook_dinner_info')->insert($data);
        } else {
            $this->error("菜品信息不存在,添加订单失败!");
        }

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->select();

        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $price_info['dinner_flag']])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品添加成功', '', $return);
    }

    /**
     * 删除商品session
     */
    function delCart()
    {
        $param = $this->request->post();
        if (empty($param))
            $this->error('商品删除失败');

        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        } else {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '1', 'cookbook_no' => $param['cookbook_no'], 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        }

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->select();
        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no and b.company_id = d.comoany_id', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag and i.company_id = d.comoany_id', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $param['dinner_flag']])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品删除成功', '', $return);
    }

    /**
     * 用餐纪录
     */
    function orderList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $data['orderList'] = Db::name('emp_cookbook_dinner_info')
            ->alias('e')
            ->join('canteen_base_info b', 'e.canteen_no = b.canteen_no and e.company_id = b.company_id', 'left')
            ->join('dinner_base_info d', 'e.dinner_flag = d.dinner_flag and e.company_id = d.company_id', 'left')
            ->where(['e.emp_id' => $user_id, 'e.company_id' => $company_id, 'e.dinner_status' => '2'])
            ->order('dinner_real_datetime', 'desc')
            ->field('e.*,canteen_name,dinner_name')
            ->select();
        foreach ($data['orderList'] as $key => $val) {
            $data['orderList'][$key]['dinner_real_datetime'] = date('Y年m月d日', strtotime($val['dinner_real_datetime']));
            $data['orderList'][$key]['dinner_datetime'] = date('Y-m-d h:i:s', strtotime($val['dinner_datetime']));
        }
        $data['orderCount'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '2'])
            ->count();
        return json($data);
    }

    /**
     * 获取菜谱订单CLASS数据
     */
    function orderProductList($day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $user_info = $this->getClientUserInfo();

        if (empty($day))
            $this->error("数据查询繁忙，请稍后重试");
        $weekArray = array("日", "一", "二", "三", "四", "五", "六");
        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w", strtotime($day))], strtotime($day));

        $data['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_datetime' => $day])
            ->count();
        $data['price_total'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_datetime' => $day])
            ->sum('cook_money');
        $data['price_total'] = number_format($data['price_total'], 2);

        if ($day == date("Y-m-d")) {
            $data['dinner_status'] = 0; //可以取餐
        } elseif ($day < date("Y-m-d")) {
            $data['dinner_status'] = 1;//历史订餐
        } else {
            $data['dinner_status'] = 2;//等待取餐
        }
        $data['emp_name'] = $user_info['Emp_MircoMsg_Uid'];
        $data['user_name'] = $user_info['Emp_Name'];

        return json($data);
    }

    /**
     * 获取菜谱订单CART数据
     */
    function orderCartList($day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $where['p.company_id'] = $company_id;
        $where['p.emp_id'] = $user_id;
        $where['p.dinner_datetime'] = $day;
        $data['cartList'] = Db::name('emp_cookbook_dinner_info')
            ->alias('p')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('dinner_base_info i', 'p.dinner_flag = i.dinner_flag and p.company_id = i.company_id', 'left')
            ->join('cookbook_meal_type e', 'b.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->field('p.*,meal_name,cookbook_name,cookbook_info,cookbook_image,canteen_name,dinner_name')
            ->order('dinner_flag asc')
            ->where($where)->select();
        $data['cartCount'] = Db::name('emp_cookbook_dinner_info')
            ->alias('p')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('dinner_base_info i', 'p.dinner_flag = i.dinner_flag and p.company_id = i.company_id', 'left')
            ->field('p.*,cookbook_name,b.cookbook_info,cookbook_image,canteen_name,dinner_name')
            ->where($where)->count();
        foreach ($data['cartList'] as $key => $val) {
            $data['cartList'][$key]['cook_money'] = number_format($val['cook_money'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartList'][$key]['cookbook_image'] = $cookbook_img;
            }

        }
        return json($data);
    }

    /**
     * 个人中心
     */
    function getMyInfo()
    {
        $user_info = Db::table("Employee_List")
            ->alias('a')
            ->join('dept_info c', 'a.Dept_Id = c.dept_no', 'left')
            ->join('t_user_manager_dept_id u', 'a.emp_id = u.u_id', 'left')
            ->join('company_list_third_interface i', 'a.company_id = i.company_id and i.third_type = 2 and i.flag = 1', 'left')
            ->field('a.Emp_Name,a.Emp_image,a.Default_Tel,a.Ic_Card,dept_name,dept_type,third_type')
            ->where('a.Emp_Id=:Emp_Id and a.Emp_MircoMsg_Id=:open_id and a.Emp_Status!=0',
                ['Emp_Id' => $this->getClientUserId(),
                    'open_id' => $this->getOpenId()])
            ->find();
        return json($user_info);
    }

    /**
     * 我的余额
     * @param $count
     * @return string
     */
    function getMyMoney()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_get_remain_money] ?,?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id, '00']);
        return json($data[0][0]);
    }

    /**
     * 浙农账户充值
     * @param $count
     * @return string
     */
    function rechargeZhenong()  //浙农充值
    {
        if (empty($_POST)) {
            $this->error('参数为空', '/index/index/me');
        }
        $info = Db::table("recharge_order_list")->where(['OrderId'=>$_POST['OrderId'],'company_id'=>$this->getClientCompanyId()])->find();
        if ($info) {
            $this->error('请不要重复提交!', '/index/index/me');
        }
        $_POST['SubTransAmt1'] = $_POST['TransAmt'];
        $html = request_post(paysConf('InterFaceURL'), $_POST);
        preg_match_all('|value="(.*)"|isU', $html, $arr); //匹配到数组$arr中；
        if (empty($arr[1][0]) || empty($arr[1][1])) {
            $this->error('系统错误，请稍后再试!', '/index/index/me');
        }
        $data = [];
        $data['Emp_id'] = $this->getClientUserId();
        $data['company_id'] = $this->getClientCompanyId();
        $data['TransAmt'] = $_POST['TransAmt'];
        $data['TransId'] = $_POST['TransId'];
        $data['MerchantId'] = $_POST['MerchantId'];
        $data['SubMerchantId'] = $_POST['SubMerchantId'];
        $data['MerDateTime'] = $_POST['MerDateTime'];
        $data['OrderId'] = $_POST['OrderId'];
        $data['MerSeqNo'] = $_POST['MerSeqNo'];
        $data['SubMerSeqNo1'] = $_POST['SubMerSeqNo1'];
        $data['SubTransAmt1'] = $_POST['SubTransAmt1']; //订单明细 金额
        $data['AppointedPayType'] = $_POST['AppointedPayType'];
        $data['recharge_type'] = 'PT001'; //浙农编号
        $data['MobileNo'] = $_POST['MobileNo']; //手机号码
        Db::table("recharge_order_list")->insert($data);
        $this->assign('shop_url', paysConf('PayInterFaceURL'));
        $this->assign('Plain', $arr[1][0]);
        $this->assign('Signature', $arr[1][1]);
        $this->assign('money', $_POST['TransAmt']);
        $this->assign('tel', $_POST['MobileNo']);
        return $this->fetch();
    }

    /**
     * 账户充值列表
     * @param $count
     * @return string
     */
    function rechargeTypeList()
    {
        $company_id = $this->getClientCompanyId();
        $where['i.company_id'] = $company_id;
        $where['i.third_type'] = '2';
        $where['i.flag'] = '1';
        $type_list = Db::name('company_list_third_interface')
            ->alias('i')
            ->join('v_third_pay_list p', 'i.third_interface_id = p.third_ic_id', 'left')
            ->field('i.*,third_company,third_url')
            ->where($where)->select();
        return json($type_list);
    }

    /**
     * 充值记录
     */
    function getRechargeList()
    {

        $data['list'] = Db::table("emp_account_get_money_detail")
            ->join('cookbook_meal_type m', 'emp_account_get_money_detail.account_typeid = m.meal_id and emp_account_get_money_detail.company_id = m.company_id', 'left')
            ->field('get_account_degree,get_account_money,case_date,meal_name')
            ->where('emp_account_get_money_detail.Emp_Id=:Emp_Id and emp_account_get_money_detail.company_id=:company_id',
                ['Emp_Id' => $this->getClientUserId(),
                    'company_id' => $this->getClientCompanyId()])
            ->order('case_date', 'desc')
            ->select();
        if (empty($data['list']))
            $this->error("充值记录为空，请稍候再试！");
        foreach ($data['list'] as $key => $val) {
            $data['list'][$key]['case_date'] = date('Y年m月d日', strtotime($val['case_date']));
            $data['list'][$key]['create_date'] = date('Y-m-d H:i', strtotime($val['case_date']));
        }
        $data['count'] = Db::table("emp_account_get_money_detail")
            ->where('Emp_Id=:Emp_Id and company_id=:company_id',
                ['Emp_Id' => $this->getClientUserId(),
                    'company_id' => $this->getClientCompanyId()])
            ->count();
        return json($data);

    }

    /**
     * 消费记录
     */
    function getRecordList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $data['list'] = Db::table("emp_cookbook_dinner_info")
            ->alias('e')
            ->join('canteen_base_info b', 'e.canteen_no = b.canteen_no and e.company_id = b.company_id', 'left')
            ->join('dinner_base_info d', 'e.dinner_flag = d.dinner_flag and e.company_id = d.company_id', 'left')
            ->join('cookbook_base_info cb', 'e.cookbook_no = cb.cookbook_no and e.company_id = cb.company_id', 'left')
            ->where(['e.emp_id' => $user_id, 'e.company_id' => $company_id])
            ->order('dinner_datetime', 'desc')
            ->field('e.*,canteen_name,dinner_name,cookbook_name')
            ->select();
        if (empty($data['list']))
            $this->error("消费记录为空，请稍候再试！");
        foreach ($data['list'] as $key => $val) {
            $data['list'][$key]['dinner_datetime'] = date('Y年m月d日', strtotime($val['dinner_datetime']));
            $data['list'][$key]['dinner_createtime'] = date('Y-m-d H:i', strtotime($val['dinner_createtime']));
            $data['list'][$key]['day'] = date('Y-m-d', strtotime($val['dinner_datetime']));
            $data['list'][$key]['cook_money'] = number_format($val['cook_money'], 2);
            if ($val['dinner_status'] == 0) {
                $data['list'][$key]['dinner_status'] = '未付款';
            } elseif ($val['dinner_status'] == 1) {
                $data['list'][$key]['dinner_status'] = '已付款';
            } else {
                $data['list'][$key]['dinner_status'] = '已取餐';
            }
        }
        $data['count'] = Db::table("emp_cookbook_dinner_info")
            ->where('emp_Id=:Emp_Id and company_id=:company_id',
                ['Emp_Id' => $this->getClientUserId(),
                    'company_id' => $this->getClientCompanyId()])
            ->count();
        return json($data);
    }

    /**
     * 部门订餐管理
     * @param $count
     * @return string
     */
    function getmydept()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_count] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        foreach ($data[0] as $key => $val) {
            $data[0][$key]['datetime'] = substr($val['sale_datetime'], 2);
        }
        return json($data[0]);
    }

    /**
     * 部门未订餐人员列表
     * @param $count
     * @return string
     */
    function getDeptEmpList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_no_detail] ?,?,?,?,?";
        $detail = Db::query($sqlStr, [$company_id, $_POST['sale_datetime'], $_POST['dept_id'], $_POST['dinner_flag'], $user_id]);
        if (empty($detail)) {
            $this->error('数据为空', '@index/index');
        }
        $data = [];
        $dept = Db::table("dept_info")->where(['company_id' => $company_id, 'dept_no' => $_POST['dept_id']])->find();
        $dinner = Db::table("dinner_base_info")->where(['company_id' => $company_id, 'dinner_flag' => $_POST['dinner_flag']])->find();
        $data['dept_name'] = $dept['dept_name'];
        $data['dinner_name'] = $dinner['dinner_name'];
        $data['memberDeptList'] = $detail[0];
        return json($data);
    }

    /**
     * 获取菜谱CLASS数据
     */
    function deptCartProductList($day, $emp_id)
    {
        if (empty($emp_id)) {
            $this->error('人员信息不正确');
        }

        if (empty($day))
            $this->error("数据查询繁忙，请稍后重试");
        $weekArray = array("日", "一", "二", "三", "四", "五", "六");
        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w", strtotime($day))], strtotime($day));

        $user_id = $emp_id; //部门人员
        $company_id = $this->getClientCompanyId();

        $where = [];
        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            $dinner_flag = implode(",", $dinner_flag);
            $where['dinner_flag'] = ['in', $dinner_flag];
        }

        $dinnerBaseList = Db::name('dinner_base_info')->where($where)->where(['company_id' => $company_id])->order('dinner_flag asc')->select();
        foreach ($dinnerBaseList as $key=>$val){
            $list = Db::name('canteen_cookbook_price')->where(['company_id'=>$company_id,'dinner_flag'=>$val['dinner_flag'],'sale_datetime'=>$day])->select();
            if(!empty($list)){
                $data['dinnerBaseList'][] = $dinnerBaseList[$key];
            }
        }
        $count = count($data['dinnerBaseList']);
        $data['dinnerBaseCount'] = $this->canteen_W($count);
        if (empty($data['dinnerBaseList']))
            $this->error("餐次信息获取失败，请稍候再试！");

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])
            ->select();

        $data['price_total'] = number_format($price_total['0']['price_total'], 2);

        $data['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])
            ->count();

        $user_info = Db::name('employee_list')->where(['emp_id' => $user_id,'company_id'=>$company_id])->find();
        $data['emp_name'] = $user_info['Emp_MircoMsg_Uid'];
        $data['user_name'] = $user_info['Emp_Name'];

        return json($data);
    }

    /**
     * 获取部门人员菜谱CART数据
     */
    function deptCartList($dinner, $day, $emp_id)
    {
        if (empty($emp_id)) {
            $this->error('人员信息不正确');
        }

        $user_id = $emp_id;
        $company_id = $this->getClientCompanyId();

        $user_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('employee_list e', 'u.u_id = e.dept_id and u.company_id = e.company_id and e.emp_id = :emp_id')
            ->bind(['emp_id' => $user_id])
            ->where(['u.dept_type' => 'deptcanteen', 'u.company_id' => $company_id])
            ->field('u.dept_id')
            ->select();
        if (!empty($user_manager)) {
            foreach ($user_manager as $key => $v) {
                $canteen_no[] = $v['dept_id'];
            }
            $canteen_no = implode(",", $canteen_no);
            $where['p.canteen_no'] = ['in', $canteen_no];
        }

        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            if (!in_array($dinner, $dinner_flag)) {
                $data['cartList'] = [];
                $data['cartCount'] = '0';
                return json($data);
            }
        }

        $where['p.company_id'] = $company_id;
        $where['p.dinner_flag'] = $dinner;
        $where['p.sale_datetime'] = $day;
        $where['p.status'] = '1';
        $where['p.cookbook_remain_quantity'] = array('neq', '0');
        $data['cartList'] = Db::name('canteen_cookbook_price')//单选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=0')->select();
        foreach ($data['cartList'] as $key => $val) {
            $data['cartList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }

        $data['cartChoiceList'] = Db::name('canteen_cookbook_price')//多选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=1')->select();
        foreach ($data['cartChoiceList'] as $key => $val) {
            $data['cartChoiceList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartChoiceList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }

        $data['cartCount'] = Db::name('canteen_cookbook_price')
            ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->where($where)->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no and b.company_id = d.company_id', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag and i.company_id = d.company_id', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $day, 'd.dinner_flag' => $dinner])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $data['cookbook_name'] = $select_cookbook['cookbook_name'];
        $data['dinner_name'] = $select_cookbook['dinner_name'];

        return json($data);
    }

    /**
     * 存储部门人员商品session
     */
    function deptAddCart($emp_id)
    {
        if (empty($emp_id)) {
            $this->error('人员信息不正确');
        }
        $param = $this->request->post();
        if (empty($param))
            $this->error('商品添加失败');
        $user_id = $emp_id;
        $company_id = $this->getClientCompanyId();

        $sqlStr = "exec [up_get_remain_money] ?,?,?";
        $money = Db::query($sqlStr, [$company_id, $user_id, '10']);
        if (empty($money))
            $this->error("没有可用余额!");
        if (floatval($param['cookbook_price']) > floatval($money[0][0]['account_remain_money']))
            $this->error("余额不足!");

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        }
        $price_info = Db::name('canteen_cookbook_price')
            ->where(['price_id' => $param['price_id'], 'sale_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag'],'company_id'=>$company_id])
            ->find();
        if (!empty($price_info)) {

            if (floatval($price_info['cookbook_remain_quantity']) < 1)
                $this->error("该商品已售罄!");

            $data['emp_id'] = $user_id;
            $data['company_id'] = $company_id;
            $data['canteen_no'] = $price_info['canteen_no'];
            $data['cookbook_no'] = $price_info['cookbook_no'];
            $data['dinner_flag'] = $price_info['dinner_flag'];
            $data['dinner_quantity'] = 1;
            $data['cook_money'] = $price_info['cookbook_price'];
            $data['dinner_datetime'] = $param['day'];
            $data['dinner_createtime'] = date("Y-m-d H:i:s");
            $position = Db::name('canteen_cookbook_window_position')
                ->where(['sale_datetime' => $param['day'], 'canteen_no' => $price_info['canteen_no'], 'cookbook_no' => $price_info['cookbook_no'], 'dinner_flag' => $price_info['dinner_flag'], 'company_id' => $company_id])->find();
            $data['dinner_machine_no'] = $position['windows_no'];  //窗口号
            $data['dinner_from_type'] = '微信';
            $data['dinner_status'] = 1;
            $data['dinner_start_type'] = 2; //部门指定
            $data['dinner_choice'] = $param['is_choice'];
            Db::name('emp_cookbook_dinner_info')->insert($data);
        } else {
            $this->error("菜品信息不存在,添加订单失败!");
        }

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->select();

        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $price_info['dinner_flag']])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品添加成功', '', $return);
    }

    /**
     * 删除部门人员商品session
     */
    function deptDelCart($emp_id)
    {
        if (empty($emp_id)) {
            $this->error('人员信息不正确');
        }

        $param = $this->request->post();
        if (empty($param))
            $this->error('商品删除失败');

        $user_id = $emp_id;
        $company_id = $this->getClientCompanyId();

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        } else {
            Db::name('emp_cookbook_dinner_info')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '1', 'cookbook_no' => $param['cookbook_no'], 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
                ->delete();
        }

        $price_total = Db::name('emp_cookbook_dinner_info')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->select();

        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no and b.company_id = d.company_id', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag and i.company_id = d.company_id', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $param['dinner_flag']])
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品删除成功', '', $return);
    }


    /**
     * 餐厅宽度比
     */
    function canteen_W($count)
    {
        switch ($count) {
            case 1:
                $count = 'W';
                return $count;
            case 2:
                $count = 'C6';
                return $count;
            case 3:
                $count = 'C4';
                return $count;
            case 4:
                $count = 'C3';
                return $count;
            default:
                $count = 'W2';
                return $count;
        }
    }

    /**
     * 修改密码
     */
    public function changePw()
    {
        $opassword = $this->request->post('opassword', '', 'trim');
        $npassword = $this->request->post('npassword', '', 'trim');
        $qpassword = $this->request->post('qpassword', '', 'trim');
        (empty($npassword) || strlen($npassword) < 4) && $this->error('登录密码长度不能少于4位有效字符!');
        (empty($qpassword) || strlen($qpassword) < 4) && $this->error('登录密码长度不能少于4位有效字符!');
        if ($npassword != $qpassword)
            $this->error('新密码和确认密码不相同!');
        if ($opassword == $npassword)
            $this->error('新密码和旧密码不能相同!');
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $employee = Db::name('Employee_list')->where(['Emp_Id' => $user_id, 'company_id' => $company_id, 'Emp_MircoMsg_Upwd' => md5($opassword)])->find();  //用户登录表
        if (empty($employee))
            $this->error('旧密码不正确!');
        Db::name('Employee_list')->where(['Emp_Id' => $user_id, 'company_id' => $company_id])->update(['Emp_MircoMsg_Upwd' => md5($qpassword)]);  //用户登录表
        $this->success('密码修改成功!', 'index/index/me');
    }

    /**
     * 注销登录
     */
    function logout()
    {
        //注销本站

        //删除数据库中的openid

        $userinfo = Db::table("Employee_list")->where('Emp_MircoMsg_Id=:custopenid', ['custopenid' => $this->getOpenId()])->find();
        if ($userinfo) {
            $openid['Emp_MircoMsg_Id'] = '';
            Db::name('Employee_list')->where('Emp_MircoMsg_Id', $this->getOpenId())->update($openid);
        }
        $data['company_id'] = $this->getClientCompanyId();
        $this->clearLoginSession();
        $this->success('退出登录成功！', '@index/login', $data);
    }

    /**
     * boohee_food
     */
    function boohee_food($name)
    {
        $name = substr($name, 0, 20);
        $url = "http://food.boohee.com/fb/v1/search?page=1&order_asc=desc&q=$name";  //查询
        $html = file_get_contents($url);
        $arr_search = json_decode($html, true);
        $food = $arr_search['items'][0]['code'];
        $url = "http://food.boohee.com/fb/v1/foods/$food/mode_show"; //具体食品
        $html = file_get_contents($url);
        $arr = json_decode($html, true);
        $url = "http://food.boohee.com/fb/v1/foods/sort_types"; //营养元素名称
        $html = file_get_contents($url);
        $arr_name = json_decode($html, true);
        foreach ($arr_name['types'] as $key => $val) {
            $data[$val['code']] = $val['name'];
        }
        $arr['types_name'] = $data;
        $arr['food_img'] = $arr_search['items'][0]['thumb_image_url'];
        return json($arr);
    }

    /**
     * 菜品图片
     */
    function boohee_food_name($name)
    {
        $name = substr($name, 0, 20);
        $url = "http://food.boohee.com/fb/v1/search?page=1&order_asc=desc&q=$name";  //查询
        $html = file_get_contents($url);
        $arr_search = json_decode($html, true);
        $food = $arr_search['items'][0]['code'];
        $url = "http://food.boohee.com/fb/v1/foods/$food/mode_show"; //具体食品
        $html = file_get_contents($url);
        $arr = json_decode($html, true);
        if (!empty($arr)) {
            $img_url = $arr['large_image_url'];
        } else {
            $img_url = '';
        }
        return $img_url;
    }

    /**
     * 意见反馈
     */
    function submit_opinion()
    {
        $data = $this->getPostData();
        $data['emp_id'] = $this->getClientUserId();
        $data['company_id'] = $this->getClientCompanyId();
        $data['create_time'] = date("Y-m-d H:i:s", intval(time()));
        Db::name('opinion_list')->insert($data);
        $this->success('提交成功!', 'index/index/me');
    }

    /**
     * 最新系统通知
     */
    function getMessage()
    {
        $data = Db::name('system_notification')->where('company_id', $this->getClientCompanyId())->order('create_time', 'desc')->find();
        return $data;
    }

    /**
     * 系统通知列表
     */
    function messageList()
    {
        $message_list = Db::name('system_notification')->where('company_id', $this->getClientCompanyId())->order('create_time', 'desc')->select();
        $message_count = Db::name('system_notification')->where('company_id', $this->getClientCompanyId())->count();
        $data = [];
        $data['messageList'] = $message_list;
        $data['messageCount'] = $message_count;
        return $data;
    }

    /**
     * 系统通知详情
     */
    function messageInfo($id)
    {
        $info = Db::name('system_notification')->where('id', $id)->find();
        $data['messageInfo'] = $info;
        return $data;
    }

    /**
     * 绑定卡号
     */
    function cardBind($card)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_bind_ic_card_check] ?,?,?";
        $return = Db::query($sqlStr, [$company_id, $user_id, $card]);
        if ($return[0][0]['retvalue'] == '-200' || $return[0][0]['retvalue'] == '-100') {
            $this->error($return[0][0]['ret_msg']);
        }
        $this->success('绑定成功!', 'index/index/me');
    }

    /**
     * 退餐管理
     * @param $count
     * @return string
     */
    function refundList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_detail_for_back] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        return json($data[0]);
    }

    /**
     * 退餐申请
     * @param $id
     * @return string
     */
    function refundOrder($id, $input)
    {
        $company_id = $this->getClientCompanyId();
        $user_info = $this->getClientUserInfo();
        $order_info = Db::table('emp_cookbook_dinner_info')->where('id', $id)->find();
        if (empty($order_info)) {
            $this->error('订单不存在,退餐失败!');
        }
        unset($order_info['ROW_NUMBER']);
        unset($order_info['dinner_createtime']);
        unset($order_info['id']);
        $order_info['source_id'] = $id;
        $order_info['check_status'] = '1';
        $order_info['dinner_createtime'] = date('Y-m-d H:i:s');
        $order_info['message'] = $input;
        $modi_id = Db::table('emp_cookbook_dinner_info_modi')->insert($order_info);
        if (empty($modi_id)) {
            $this->error('订单插入失败,退餐失败!');
        }

        $dept_list = Db::table('t_user_manager_dept_id')->where(['dept_id' => $user_info['Dept_Id'], 'dept_type' => 'userdept', 'company_id' => $company_id])->select();
        foreach ($dept_list as $value) {
            $userInfo = Db::table('Employee_List')->where(['emp_id' => $value['u_id'], 'company_id' => $company_id])->find();
            refundMSC($userInfo['Emp_MircoMsg_Id']);
        } //向部门管理者发送消息
        $this->success('退餐申请提交成功，请耐心等待审核人员审核!', 'index/index/refund');
    }

    /**
     * 退补餐管理人员审核列表
     * @param $count
     * @return string
     */
    function checkList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_detail_modi_check] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        return json($data[0]);
    }

    /**
     * 退餐审核管理
     * @param $id
     * @return string
     */
    function checkOrder($id, $status)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $order_info = Db::table('emp_cookbook_dinner_info_modi')->where('id', $id)->find();
        if (empty($order_info)) {
            $this->error('订单不存在,退餐失败!');
        }
        $data['checker1_id'] = $user_id;
        $data['check1_datetime'] = date("Y-m-d H:i:s");
        $data['check_status'] = $status;
        Db::table('emp_cookbook_dinner_info_modi')->where('id', $id)->update($data);
        if ($status == '-1') {
            $userInfo = Db::table('Employee_List')->where(['emp_id' => $order_info['emp_id'], 'company_id' => $company_id])->find();
            refundNoMSC($userInfo['Emp_MircoMsg_Id']);
            $this->success('拒绝退餐审核提交成功,已通知申请者!', 'index/index/checkList');
        } else {
            $this->success('退餐审核提交成功,请耐心等待审核批准人员审核!', 'index/index/checkList');
        }

    }

    /**
     * 补餐申请列表
     * @param $count
     * @return string
     */
    function patchList()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_no_detail_for_create] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        if(empty($data)){
            $data[0] = '';
        }
        return json($data[0]);
    }


    /**
     * 获取菜谱CLASS数据
     */
    function patchcartProductList($day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $user_info = $this->getClientUserInfo();

        if (empty($day))
            $this->error("数据查询繁忙，请稍后重试");
        $weekArray = array("日", "一", "二", "三", "四", "五", "六");
        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w", strtotime($day))], strtotime($day));

        $where = [];
        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            $dinner_flag = implode(",", $dinner_flag);
            $where['dinner_flag'] = ['in', $dinner_flag];
        }

        $dinnerBaseList = Db::name('dinner_base_info')->where($where)->where(['company_id' => $company_id])->order('dinner_flag asc')->select();
        foreach ($dinnerBaseList as $key=>$val){
            $list = Db::name('canteen_cookbook_price')->where(['company_id'=>$company_id,'dinner_flag'=>$val['dinner_flag'],'sale_datetime'=>$day])->select();
            if(!empty($list)){
                $data['dinnerBaseList'][] = $dinnerBaseList[$key];
            }
        }
        $count = count($data['dinnerBaseList']);
        $data['dinnerBaseCount'] = $this->canteen_W($count);
        if (empty($data['dinnerBaseList']))
            $this->error("餐次信息获取失败，请稍候再试！");

        $price_total = Db::name('emp_cookbook_dinner_info_modi')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day,'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->select();

        $data['price_total'] = number_format($price_total['0']['price_total'], 2);

        $data['price_num'] = Db::name('emp_cookbook_dinner_info_modi')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day,'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->count();

        $data['emp_name'] = $user_info['Emp_MircoMsg_Uid'];
        $data['user_name'] = $user_info['Emp_Name'];

        return json($data);
    }

    /**
     * 获取菜谱CART数据
     */
    function patchcartList($dinner, $day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $user_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('employee_list e', 'u.u_id = e.dept_id and u.company_id = e.company_id and e.emp_id = :emp_id')
            ->bind(['emp_id' => $user_id])
            ->where(['u.dept_type' => 'deptcanteen', 'u.company_id' => $company_id])
            ->field('u.dept_id')
            ->select();
        if (!empty($user_manager)) {
            foreach ($user_manager as $key => $v) {
                $canteen_no[] = $v['dept_id'];
            }
            $canteen_no = implode(",", $canteen_no);
            $where['p.canteen_no'] = ['in', $canteen_no];
        }

        $user_dinner_manager = Db::name('t_user_manager_dept_id')
            ->alias('u')
            ->join('dinner_base_info b', 'u.dept_id = b.dinner_no and u.company_id = b.company_id', 'left')
            ->where(['u.dept_type' => 'userdinner', 'u.u_id' => $user_id, 'u.company_id' => $company_id])
            ->field('dinner_flag')
            ->select();
        if (!empty($user_dinner_manager)) {
            foreach ($user_dinner_manager as $key => $v) {
                $dinner_flag[] = $v['dinner_flag'];
            }
            if (!in_array($dinner, $dinner_flag)) {
                $data['cartList'] = [];
                $data['cartCount'] = '0';
                return json($data);
            }
        }

        $where['p.company_id'] = $company_id;
        $where['p.dinner_flag'] = $dinner;
        $where['p.sale_datetime'] = $day;
        $where['p.status'] = '1';
        $where['p.cookbook_remain_quantity'] = array('neq', '0');
        $data['cartList'] = Db::name('canteen_cookbook_price')//单选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info_modi d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag and d.check_status = 2 and isnull(d.source_id,0)=0', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=0')->select();
        foreach ($data['cartList'] as $key => $val) {
            $data['cartList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }
        $data['cartChoiceList'] = Db::name('canteen_cookbook_price')//多选列表
        ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('emp_cookbook_dinner_info_modi d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag and d.check_status = 2 and isnull(d.source_id,0)=0', 'left')
            ->join('cookbook_meal_type e', 'p.meal_id = e.meal_id and p.company_id = e.company_id', 'left')
            ->bind(['emp_id' => $user_id])
            ->field('p.*,meal_name,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')
            ->order('order_index desc')
            ->where($where)->where('p.choice_flag=1')->select();
        foreach ($data['cartChoiceList'] as $key => $val) {
            $data['cartChoiceList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);
            if (empty($val['cookbook_image'])) {
                $cookbook_img = $this->boohee_food_name($val['cookbook_name']);
                Db::name('cookbook_base_info')->where(['cookbook_no'=>$val['cookbook_no'],'company_id'=>$company_id])->update(['cookbook_image'=>$cookbook_img]);
                $data['cartChoiceList'][$key]['cookbook_image'] = $cookbook_img;
            }
        }

        $data['cartCount'] = Db::name('canteen_cookbook_price')
            ->alias('p')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->where($where)->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info_modi')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $day, 'd.dinner_flag' => $dinner,'d.check_status'=>'2'])
            ->where('isnull(d.source_id,0)=0')
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $data['cookbook_name'] = $select_cookbook['cookbook_name'];
        $data['dinner_name'] = $select_cookbook['dinner_name'];

        return json($data);
    }

    /**
     * 存储商品session
     */
    function patchaddCart()
    {
        $param = $this->request->post();
        if (empty($param))
            $this->error('商品添加失败');
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        $sqlStr = "exec [up_get_remain_money] ?,?,?";
        $money = Db::query($sqlStr, [$company_id, $user_id, '10']);
        if (empty($money))
            $this->error("没有可用余额!");
        if (floatval($param['cookbook_price']) > floatval($money[0][0]['account_remain_money']))
            $this->error("余额不足!");

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info_modi')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag'],'check_status'=>'2'])->where('isnull(source_id,0)=0')
                ->delete();
        }

        $price_info = Db::name('canteen_cookbook_price')
            ->where(['price_id' => $param['price_id'], 'sale_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])
            ->find();
        if (!empty($price_info)) {
            if (floatval($price_info['cookbook_remain_quantity']) < 1)
                $this->error("该商品已售罄!");

            $data['emp_id'] = $user_id;
            $data['company_id'] = $company_id;
            $data['canteen_no'] = $price_info['canteen_no'];
            $data['cookbook_no'] = $price_info['cookbook_no'];
            $data['dinner_flag'] = $price_info['dinner_flag'];
            $data['dinner_quantity'] = 1;
            $data['cook_money'] = $price_info['cookbook_price'];
            $data['dinner_datetime'] = $param['day'];
            $data['dinner_createtime'] = date("Y-m-d H:i:s");
            $position = Db::name('canteen_cookbook_window_position')
                ->where(['sale_datetime' => $param['day'], 'canteen_no' => $price_info['canteen_no'], 'cookbook_no' => $price_info['cookbook_no'], 'dinner_flag' => $price_info['dinner_flag'], 'company_id' => $company_id])->find();
            $data['dinner_machine_no'] = $position['windows_no'];  //窗口号
            $data['dinner_from_type'] = '微信';
            $data['dinner_status'] = 1;
            $data['dinner_choice'] = $param['is_choice'];
            $data['check_status'] = 2;//补餐状态
            Db::name('emp_cookbook_dinner_info_modi')->insert($data);
        } else {
            $this->error("菜品信息不存在,添加订单失败!");
        }

        $price_total = Db::name('emp_cookbook_dinner_info_modi')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->select();

        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info_modi')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info_modi')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $price_info['dinner_flag'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品添加成功', '', $return);
    }

    /**
     * 删除商品session
     */
    function patchdelCart()
    {
        $param = $this->request->post();
        if (empty($param))
            $this->error('商品删除失败');

        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();

        if ($param['is_choice'] == '0') {
            Db::name('emp_cookbook_dinner_info_modi')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '0', 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag'],'check_status'=>'2'])->where('isnull(source_id,0)=0')
                ->delete();
        } else {
            Db::name('emp_cookbook_dinner_info_modi')
                ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_choice' => '1', 'cookbook_no' => $param['cookbook_no'], 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag'],'check_status'=>'2'])->where('isnull(source_id,0)=0')
                ->delete();
        }

        $price_total = Db::name('emp_cookbook_dinner_info_modi')
            ->field('sum(cook_money) as price_total')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->select();
        $return['price_total'] = number_format($price_total['0']['price_total'], 2);

        $return['price_num'] = Db::name('emp_cookbook_dinner_info_modi')
            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->count();

        $select_cookbook = Db::name('emp_cookbook_dinner_info_modi')
            ->alias('d')
            ->join('cookbook_base_info b', 'b.cookbook_no = d.cookbook_no', 'left')
            ->join('dinner_base_info i', 'i.dinner_flag = d.dinner_flag', 'left')
            ->where(['d.company_id' => $company_id, 'd.emp_id' => $user_id, 'd.dinner_datetime' => $param['day'], 'd.dinner_flag' => $param['dinner_flag'],'check_status'=>'2'])
            ->where('isnull(source_id,0)=0')
            ->field('d.*,cookbook_name,dinner_name')
            ->find();
        $return['cookbook_name'] = $select_cookbook['cookbook_name'];
        $return['dinner_name'] = $select_cookbook['dinner_name'];

        $this->success('商品删除成功', '', $return);
    }

    /**
     * 部门餐次管理
     * @param $count
     * @return string
     */
    function getmydeptdinner()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dinner_manager] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        $resort = array();
        foreach ($data[0] as $k => $v) {
            $resort[$v['emp_name']][] = $v;
        }
        $count = Db::name('dinner_base_info')->where(['company_id' => $company_id])->count();
        $json['dinnerBaseCount'] = 49 / $count;
        $json['dinnerList'] = $resort;
        return json($json);
    }

    /*管理员控制部门人员餐次*/
    function deptdinner($dinner_no, $user_id, $chose_flag)
    {
        $company_id = $this->getClientCompanyId();
        if ($chose_flag == '1') {
            $data['u_id'] = $user_id;
            $data['dept_id'] = $dinner_no;
            $data['company_id'] = $company_id;
            $data['dept_type'] = 'userdinner';
            $data_id = Db::name('t_user_manager_dept_id')->insert($data);
            if (empty($data_id)) {
                $this->error('锁定人员餐次失败', 'index/index/dinner');
            }
        } else {
            Db::name('t_user_manager_dept_id')->where(['dept_type' => 'userdinner', 'company_id' => $company_id, 'dept_id' => $dinner_no, 'u_id' => $user_id])->delete();
            $this->success('取消锁定人员餐次成功', 'index/index/dinner');
        }
        $this->success('锁定人员餐次成功', 'index/index/dinner');
    }

    /**
     * 部门订餐分析
     * @param $count
     * @return string
     */
    function getmyanalysis()
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_count_for_meal_name] ?,?";
        $data = Db::query($sqlStr, [$company_id, $user_id]);
        foreach ($data[0] as $key => $val) {
            $data[0][$key]['datetime'] = date('y-m-d', strtotime($val['sale_datetime']));
        }
        return json($data[0]);
    }


    /**
     * 部门订餐分析套餐缩略
     * @param $count
     * @return string
     */
    function getAnalysisEmpMealList($dept_id, $dinner_flag)
    {
        $company_id = $this->getClientCompanyId();
        $list = Db::table('cookbook_meal_type')->distinct(true)->field('meal_short_name')->where('meal_flag', '0')->where('company_id', $company_id)->select();
        $dept_info = Db::table('dept_info')->where(['dept_no' => $dept_id, 'company_id' => $company_id])->find();
        $dinner_info = Db::table('dinner_base_info')->where(['dinner_flag' => $dinner_flag, 'company_id' => $company_id])->find();
        $data['dept_name'] = $dept_info['dept_name'];
        $data['dinner_name'] = $dinner_info['dinner_name'];
        $data['meal_list'] = $list;
        return json($data);
    }

    /**
     * 部门订餐分析详情
     * @param $count
     * @return string
     */
    function getAnalysisEmpList($meal_short_name, $date_str, $dinner_flag, $dept_id)
    {
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_emp_dept_dinner_detail_for_meal_name] ?,?,?,?,?";
        $data = Db::query($sqlStr, [$company_id, $date_str, $dept_id, $dinner_flag, $meal_short_name]);
        if (empty($data)) {
            $data[0] = [];
        }
        return json($data[0]);
    }

    /**
     * 扫码取餐
     * @param $count
     * @return string
     */
    function getMealList($machine_sn)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_machine_info] ?";
        $data = Db::query($sqlStr, ['03'.'||'.$machine_sn.'||||'.$user_id.'||'.$company_id]);
        if (empty($data)) {
            $this->error('获取餐厅菜品数据失败,请稍后重试','index/index/index');
        }
        $return = explode("||",$data[0][0]['return_msg']);
        if($return['4'] != '88'){
            $this->error($return['10'],'index/index/index');
        }else{
            $this->success($return['10']);
        }
    }

    /*确认取餐*/
    function mealBind($machine_sn)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $sqlStr = "exec [up_machine_info] ?";
        $data = Db::query($sqlStr, ['04'.'||'.$machine_sn.'||'.$user_id.'||'.$company_id]);
        $return = explode("||",$data[0][0]['return_msg']);
        $this->success($return['5']);
    }

    /*查看取餐窗口*/
    function getWindowsList($day)
    {
        $user_id = $this->getClientUserId();
        $company_id = $this->getClientCompanyId();
        $where['p.company_id'] = $company_id;
        $where['p.emp_id'] = $user_id;
        $where['p.dinner_datetime'] = $day;
        $data['cartList'] = Db::name('emp_cookbook_dinner_info')
            ->alias('p')
            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')
            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')
            ->join('dinner_base_info i', 'p.dinner_flag = i.dinner_flag and p.company_id = i.company_id', 'left')
            ->join('cookbook_meal_type e', 'b.meal_id = e.meal_id and e.company_id = p.company_id', 'left')
            ->join('canteen_cookbook_window_position f','p.company_id = f.company_id and p.cookbook_no = f.cookbook_no and f.sale_datetime = p.dinner_datetime and f.canteen_no = p.canteen_no and f.dinner_flag = p.dinner_flag','left')
            ->join('canteen_sale_window_base_info g','f.windows_no = g.sale_window_no and f.company_id = p.company_id and g.canteen_no = f.canteen_no')
            ->field('p.*,meal_name,cookbook_name,cookbook_info,cookbook_image,canteen_name,dinner_name,sale_window_name')
            ->order('dinner_flag asc')
            ->where($where)->select();
        return json($data);
    }

    /*获取设备信息*/
    function device($machine_sn)
    {
        $company_id = $this->getClientCompanyId();
        $machine_info = Db::name('machine_list')->where(['company_id'=>$company_id,'machine_sn'=>$machine_sn])->find();
        if(!$machine_info){
            $this->error('设备信息不存在!','/index/index/me');
        }
        $data['canteen_list'] = Db::name('canteen_base_info')->where(['company_id'=>$company_id])->select();
        $data['windows_list'] = Db::name('canteen_sale_window_base_info')->where(['company_id'=>$company_id])->order('sale_window_no asc,sale_window_name desc')->select();
        $param_list = Db::name('machine_param_list')->where(['machine_sn'=>$machine_sn])->select();
        if(!empty($param_list)){
            foreach ($param_list as $key=>$val){
                $data['param_info'][$val['param_name']] = $val['param_value'];
            }
        }else{
            $data['param_info'] = [];
        }
        $data['select_info'] = Db::name('machine_list')->where(['company_id'=>$company_id,'machine_sn'=>$machine_sn])->find();
        if(!empty($data['select_info'])&&$data['select_info']['machine_status'] != 1){
            $this->error('设备被禁用，请联系管理员!','/index/index/me');
        }
        return json($data);
    }

    /*设备注册*/
    function deviceRegister($machine_sn)
    {
        $company_id = $this->getClientCompanyId();
        $machine_info = Db::name('machine_list')->where(['company_id'=>$company_id,'machine_sn'=>$_POST['machine_sn']])->find();
        if(!empty($machine_info)){
            Db::name('machine_list')->where(['company_id'=>$company_id,'machine_sn'=>$_POST['machine_sn']])->update(['canteen_no'=>$_POST['canteen_no'],'window_no'=>$_POST['window_no'],'machine_type'=>$_POST['machine_type']]);
        }else{
            $sqlstr = "exec [up_get_max_id] ?,?,?,?";
            $data = Db::query($sqlstr, ['', 'machine', 'MACH', 0]);
            $machine_id = $data[0][0]['id'];
            Db::name('machine_list')->insert(['company_id'=>$company_id,'machine_id'=>$machine_id,'machine_sn'=>$_POST['machine_sn'],'canteen_no'=>$_POST['canteen_no'],'window_no'=>$_POST['window_no'],'machine_type'=>$_POST['machine_type'],'machine_status'=>'1']);
        }
        Db::name('canteen_sale_window_base_info')->where(['company_id'=>$company_id,'sale_window_no'=>$_POST['window_no'],'canteen_no'=>$_POST['canteen_no']])->update(['machine_sn'=>$_POST['machine_sn']]);
        unset($_POST['canteen_no']);
        unset($_POST['window_no']);
        unset($_POST['machine_sn']);
        foreach ($_POST as $key=>$val){
            $info = Db::name('machine_param_list')->where(['machine_sn'=>$machine_sn,'param_name'=>$key])->find();
            if(empty($info)){
                Db::name('machine_param_list')->insert(['machine_sn'=>$machine_sn,'param_name'=>$key,'param_value'=>$val]);
            }else{
                Db::name('machine_param_list')->where(['machine_sn'=>$machine_sn,'param_name'=>$key])->update(['param_value'=>$val]);
            }
        }
        $this->success('设备注册成功','/index/index/me');
    }
}
