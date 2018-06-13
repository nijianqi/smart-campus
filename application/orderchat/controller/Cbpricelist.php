<?php

namespace app\orderchat\controller;

use controller\BasicAdmin;
use service\LogService;
use service\DataService;
use think\Db;
use PHPExcel_IOFactory;
use PHPExcel;

/**
 * 系统用户管理控制器
 * Class User
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 18:12
 */
class CbPricelist extends BasicAdmin
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'canteen_cookbook_price';

    /**
     * 周菜谱列表
     */
    public function index()
    {
        // 设置页面标题
        $this->title = '周菜谱列表管理';
        // 获取到所有GET参数
        $get = $this->request->get();

        if (isset($get['canteen_no']) && $get['canteen_no'] !== '') {
            $canteen_no = $get['canteen_no'];
        } else {
            $canteen_no = ' ';
        }

        if (isset($get['week_id']) && $get['week_id'] !== '') {
            $week_id = $get['week_id'];
        } else {
            $week_id = ' ';
        }
        $sqlstr = "exec [up_canteen_week_count] ?,?,?,?";
        $return = Db::query($sqlstr, [session('company_id'), $canteen_no, $week_id, session('user.id')]);
        if (empty($return[0])) {
            $this->error('数据不存在');
        }
        $canteens = Db::name('canteen_base_info')->where('company_id', session('company_id'));
        if (session('user.create_by') != '10001') {
            $canteens->where(' exists (select 1 from t_user_manager_dept_id b where canteen_base_info.canteen_no=b.dept_id and canteen_base_info.company_id=b.company_id and u_id=:emp_id)')->bind(['emp_id' => session('user.id')]);
        }
        $this->assign('canteens', $canteens->column('canteen_no,canteen_name'));
        $weeks = Db::name('week_day_list')->column('id,week_num,start_datetime,end_datetime');
        $this->assign('weeks', $weeks);

        $this->assign('list', $return[0]);
        return $this->fetch();
    }


    /**
     * 周菜谱编辑
     */
    public function edit()
    {
        LogService::write('订餐管理', '执行周菜谱编辑操作');
        if ($this->request->isGet()) {
            $sqlstr = "exec [up_canteen_week_detail] ?,?,?,?";
            $data = Db::query($sqlstr, [session('company_id'), $_GET['canteen_no'], $_GET['week_num'], session('user.id')]);
            if (empty($data)) {
                $this->error('数据不存在');
            }
            $list = [];
            foreach ($data[0] as $val) {
                $list[$val['week_name']][$val['dinner_name']][$val['meal_id']] = $val;
            }
            $canteens = Db::name('canteen_base_info')->where(['canteen_no' => $_GET['canteen_no'], 'company_id' => session('company_id')])->find();
            $weeks = Db::name('week_day_list')->where(['week_num' => $_GET['week_num']])->find();
            $this->assign('canteens', $canteens);
            $this->assign('weeks', $weeks);
            $this->assign('list', $list);
        } else {
            if (!empty($_POST)) {
                $dt_start = strtotime($_POST['start_datetime']);
                $dt_end = strtotime($_POST['end_datetime']);
                while ($dt_start <= $dt_end) {
                    $date[] = date('Y-m-d', $dt_start);
                    $dt_start = strtotime('+1 day', $dt_start);
                }
                $weekarray = array("日", "一", "二", "三", "四", "五", "六");
                foreach ($date as $val) {
                    $week[] = '周' . $weekarray[date("w", strtotime($val))];
                }
                $date_time = [];
                foreach ($date as $val){
                    $date_time['周' . $weekarray[date("w", strtotime($val))]] = $val;
                }
                $canteen_no = $_POST['canteen_no'];

                $dinner_list = Db::table('dinner_base_info')->where('company_id', session('company_id'))->select();
                $meal_list = Db::table('cookbook_meal_type')->where(['company_id' => session('company_id'), 'meal_flag' => '0'])->select();


                foreach ($week as $val) {
                    foreach ($dinner_list as $value) {
                        foreach ($meal_list as $meal) {
                            if (!empty($_POST[$val . $value['dinner_name'] . $meal['meal_id']])) {
                                $data = [];
                                $data['canteen_no'] = $canteen_no;
                                $data['cookbook_no'] = $_POST[$val . $value['dinner_name'] . $meal['meal_id']];
                                $cookbook_info =  Db::table('cookbook_base_info')->where(['cookbook_no'=>$data['cookbook_no'],'company_id'=>session('company_id')])->find();
                                $data['cookbook_price'] = $cookbook_info['price'];
                                $data['sale_datetime'] = $date_time[$val];
                                $price_id = Db::query('select dbo.a_get_datetimestrguid()');
                                $data['price_id'] = $price_id[0][''];
                                $data['status'] = '1';
                                $data['price_flag'] = $cookbook_info['price_flag'];
                                $data['dinner_flag'] =$value['dinner_flag'];
                                $data['cookbook_info'] = $cookbook_info['cookbook_info'];
                                $data['company_id'] = session('company_id');
                                Db::table('canteen_cookbook_price')->insert($data);
                            }
                        }
                    }
                }
              $this->success('发布周菜谱成功','');
            }
        }
        return $this->_form($this->table, 'form', 'id');
    }

    /**
     * 表单数据默认处理
     * @param array $data
     */
    public function _form_filter(&$data)
    {
        $meals = Db::name('cookbook_meal_type')->where(['company_id' => session('user.company_id'), 'meal_flag' => '0'])->select();
        foreach ($meals as $val) {
            $cookbooks = Db::name('cookbook_base_info')->where('meal_id', $val['meal_id'])->where('company_id', session('user.company_id'))->select();
            $this->assign('cookbooks' . $val['meal_id'], $cookbooks);
        }
        $this->assign('meals', $meals);
    }

}
