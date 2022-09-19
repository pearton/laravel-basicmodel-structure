<?php
/**
 * Power by pearton/uppdd.com
 * Author Pearton <pearton@126.com>
 * Originate in pearton:Basic_orm-tool ID.
 * BaseiModelTool.php
 * Date: 2022/9/2
 * Time: 9:54
 */

namespace Peartonlixiao\LaravelOrmTool;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BaseiModelTool
{
    private static $instance;

    private function __construct(){}

    private function __clone(){}

    public static function getInstance():BaseiModelTool
    {
        if(!(self::$instance instanceof self)){
            self::$instance = new static;
        }
        return self::$instance;
    }

    /**
     * 作用方法:批量更新-基于表名
     * @Author Pearton <pearton@126.com>
     * @Time 2022/9/2 10:04
     * @param string $tableName
     * @param array $multipleData
     * @return bool
     */
    public function updateAllBatchByTable(string $tableName,array $multipleData):bool
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            if(!Schema::hasColumn($tableName,$referenceColumn)){
                throw new Exception("当前Model不存在状态字段{$referenceColumn}");
            }
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            foreach ($updateColumn as $uColumn) {
                $updateSql .= $uColumn . " = CASE ";
                foreach ($multipleData as $data) {
                    $updateSql .= "WHEN " . $referenceColumn . " = " . $data[$referenceColumn] . " THEN '" . $data[$uColumn] . "' ";
                }
                $updateSql .= "ELSE " . $uColumn . " END, ";
            }

            // 更新条件，以逗号分隔
            $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $whereIn = implode(',', $whereIn);

            $updateSql = rtrim($updateSql, ", ") . " WHERE " . $referenceColumn . " IN (" . $whereIn . ")";

            return DB::update(DB::raw($updateSql));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 作用方法:获取时间段
     * @Author Pearton <pearton@126.com>
     * @Time 2022/9/19 10:26
     * @param string $dateDesc
     * @param string $dateHorizon
     * @return array
     */
    public function getDateRange(string $dateDesc,string $dateHorizon): array
    {
        $date1 = false;
        $date2 = false;
        $time = time();
        switch ($dateDesc){
            case 'date_day':        //今天
                $date1 = date('Y-m-d H:i:s',strtotime(date('Y-m-d')));
                $date2 = date('Y-m-d').' 23:59:59';
                break;
            case 'date_yesterday':  //昨天
                $date1 = date('Y-m-d H:i:s',strtotime('-1 day',strtotime(date('Y-m-d'))));
                $date2 = date('Y-m-d',strtotime($date1)).' 23:59:59';
                break;
            case 'date_week':       //本周
                $date1 = date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m", $time),date("d", $time)-date("w", $time)+1,date("Y", $time)));
                $date2 = date("Y-m-d H:i:s",mktime(23,59,59,date("m", $time),date("d", $time)-date("w", $time)+7,date("Y", $time)));
                break;
            case 'date_month':      //本月
                $date1 = date('Y-m-d H:i:s',mktime(0,0,0,date("m",$time),1,date("Y",$time)));
                $date2 = date('Y-m-d H:i:s',mktime(23,59,59,date("m",$time),date("t",strtotime($date1)),date("Y",$time)));
                break;
            case 'date_lastmonth':  //上月
                $date1 = date('Y-m-d H:i:s',mktime(0,0,0,date("m",strtotime('-1 month',$time)),1,date("Y",$time)));
                $date2 = date('Y-m-d H:i:s',mktime(23,59,59,date("m",strtotime('-1 month',$time)),date("t",strtotime($date1)),date("Y",$time)));
                break;
            case 'date_year':       //今年
                $date1 = date('Y-m-d H:i:s',mktime(0,0,0,1,1,date("Y",$time)));
                $date2 = date('Y-m-d H:i:s',mktime(23,59,59,12,31,date("Y",$time)));
                break;
            case 'date_other':      //自定义时间
                if(!isset($dateHorizon) || !$dateHorizon){
                    $date1 = false;
                    $date2 = false;
                }else{
                    $date = explode(' - ',$dateHorizon);
                    if(!$date){
                        $date1 = false;
                        $date2 = false;
                    }else{
                        $date1 = $date[0];
                        $date2 = $date[1];
                    }
                }
        }

        return compact('date1','date2');
    }
}