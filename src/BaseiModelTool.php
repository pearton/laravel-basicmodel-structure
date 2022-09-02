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
}