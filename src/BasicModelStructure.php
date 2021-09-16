<?php
/**
 * BasicModelStructure.php
 * Created on 2021/8/12 16:28
 * Created by Lxd.
 * QQ: 790125098
 */

namespace Peartonlixiao\LaravelOrmTool;

use Exception;
use Illuminate\Support\Facades\Schema;

/**
 * Model基本方法构造|仅允许Model类实现
 * Trait BasicModelStructure
 * @package App\Tool\traits
 */
trait BasicModelStructure
{
    private static $deteleValidate = "App\Validate\DeleteDataValidate";
    protected $frontDndStatus = false;

    /**
     * 作用方法:注册status(可自定义为其他字段|但常量固定名称)的前台检索全局变量
     * Created by Lxd.
     * @throws Exception
     */
    protected static function boot()
    {
        parent::boot();

        $_that = new self();
        if($_that->frontDndStatus && request()->segment(1) == 'api'){
            $statusKey = property_exists($_that,'statusKey')  ? $_that->statusKey : 'status';
            if(!Schema::hasColumn($_that->getTable(),$statusKey)){
                throw new Exception("当前Model不存在状态字段{$statusKey},请务开启frontDndStatus为true");
            }
            //开启前端status字段显示作用域(前台(api端)仅允许检索出状态(status)为正常的数据)
            static::addGlobalScope('status',function (Builder $builder){
                $builder->where('status',self::STATUS_YES);
            });
        }
    }

    /**
     * Created by Lxd
     * @return array
     */
    protected function fieldRule():array
    {
        return [];
    }

    /**
     * 获取实现model名称下验证器指定验证方法
     * @param $method
     * @return array|bool
     */
    protected static function getValidataMethod(string $method)
    {
        $sourceModel = explode('\\',__CLASS__);
        $validataObj = end($sourceModel).'DataValidate';
        $validataObj = "App\Validate\\{$validataObj}";

        if(class_exists($validataObj) && method_exists($validataObj,$method)){
            return ['validataObj' => $validataObj,'method' => $method];
        }

        return false;
    }

    /**
     * 创建人关联
     * Created by Lxd
     * @return false|\Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function User()
    {
        if(Schema::hasColumn($this->getTable(),'created_user')){
            return $this->belongsTo('App\Models\User','created_user','id');
        }
        return false;
    }

    /**
     * model主键获取
     * @return mixed
     */
    public static function getPrimaryKey()
    {
        return (new self())->primaryKey;
    }

    /**
     * @return mixed
     */
    public function getSort()
    {
        return $this->max($this::getPrimaryKey()) + 1;
    }

    /**
     * 主键检索
     * Created by Lxd.
     * @param int $primaryKey
     * @param false $field
     * @return false
     * @throws Exception
     */
    public function findOne(int $primaryKey,$field = false)
    {
        if($field){
            if(!Schema::hasColumn($this->getTable(),$field)){
                throw new Exception("当前数据表未包含字段{$field}");
            }
            return $this->find($primaryKey)->$field ?? false;
        }
        return $this->find($primaryKey);
    }

    /**
     * 指定条件检索单条数据
     * Created by Lxd
     * @param array $params
     * @param array $withEl
     * @param bool $toArray
     * @return mixed
     */
    public function findByField(array $params,array $withEl = [],$toArray = false)
    {
        $query = $this;
        if($withEl){
            $query = $query::with($withEl);
        }
        $re = $this::buildQuery($query,$params)->first();
        return ($toArray && $re) ? $re->toArray() : $re;
    }

    /**
     * model检索
     * Created by Lxd
     * @param array $params
     * @param array $withEl
     * @param false $toArray
     * @param false $obj
     * @return mixed
     * @throws Exception
     */
    public function search(array $params,array $withEl = [],$withCount = null,$toArray = false,$obj = false)
    {
        $query = $this;
        if($withEl){
            $query = $query::with($withEl);
        }
        try {
            $query = $this::buildQuery($query,$params);
        }catch (Exception $e){
            throw new Exception("查询构建异常:{$e->getMessage()}");
        }
        if($toArray){
            if($obj){
                return $query->get();
            }else{
                //获取所有数据数组集合
                return $query->get()->toArray();
            }
        }
        if($withCount){
            $query = $query->withCount($withCount);
        }
        //检索条数
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        //数据结果集获取
        return $query->paginate($limit)->appends($params);
    }

    /**
     * 搜索特殊条件拼接[时间等]
     * 时间搜索组件/格式/字段固定 可查看项目内已调用该方法的页面视图格式
     * Created by Lxd.
     * Created on 2021/5/13 10:41
     * @param array $params
     * @param string $field
     * @return array
     * @throws Exception
     */
    public function searchWhereCustomJoint(array $params,string $field = 'created_at'):array
    {
        $where_custom = [];
        $date1 = false;
        $date2 = false;
        if(isset($params['fast_data']) && $params['fast_data']){
            $time = time();
            switch ($params['fast_data']){
                case 'date_day':    //只看今天的
                    $date1 = date('Y-m-d H:i:s',strtotime(date('Y-m-d')));
                    $date2 = date('Y-m-d H:i:s',strtotime('+1 day'));
                    break;
                case 'date_week':   //本周
                    $date1 = date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m", $time),date("d", $time)-date("w", $time)+1,date("Y", $time)));
                    $date2 = date("Y-m-d H:i:s",mktime(23,59,59,date("m", $time),date("d", $time)-date("w", $time)+7,date("Y", $time)));
                    break;
                case 'date_month':  //本月
                    $date1 = date('Y-m-d H:i:s',mktime(0,0,0,date("m",$time),1,date("Y",$time)));
                    $date2 = date('Y-m-d H:i:s',mktime(23,59,59,date("m",$time),date("t",strtotime($date1)),date("Y",$time)));
                    break;
                case 'date_other':  //自定义时间
                    if(!isset($params['time_horizon']) || !$params['time_horizon']){
                        $date1 = false;
                        $date2 = false;
                    }else{
                        $date = explode(' - ',$params['time_horizon']);
                        if(!$date){
                            $date1 = false;
                            $date2 = false;
                        }else{
                            $date1 = $date[0];
                            $date2 = $date[1];
                        }
                    }
            }
            if($date1 && $date2){
                if(!Schema::hasColumn($this->getTable(),$field)){
                    throw new Exception("当前Model不存在字段{$field}");
                }
                array_push($where_custom,[$field,'>',$date1]);
                array_push($where_custom,[$field,'<',$date2]);
            }
        }
        return $where_custom;
    }

    /**
     * 构建链式检索条件
     * Created by Lxd
     * @param $modelQuery
     * @param $params
     * @return mixed
     */
    private static function buildQuery($modelQuery,$params)
    {
        foreach ($params as $k=>$v){
            $fundKey = (new self())->_getFieldRuleKey($k);
            if((!$fundKey || !$v) && $k !== 'lishu'){
                if(!$fundKey){
                    //没找到声明,默认eq查询
                    if(Schema::hasColumn((new self())->getTable(),$k) && $v){
                        $modelQuery = $modelQuery->where($k,$v);
                    }
                }
                continue;
            }
            /**查询构建**/
            switch ($fundKey){
                case 'like':
                    $modelQuery = $modelQuery->where($k,'like',"%{$v}%");
                    break;
                case 'in':
                    $modelQuery = $modelQuery->whereIn($k,$v);
                    break;
                default:
                    $modelQuery = $modelQuery->where($k,$v);
            }
        }
        if(isset($params['where_custom']) && $params['where_custom']){
            $modelQuery = $modelQuery->where($params['where_custom']);
        }
        if(isset($params['where_custom_in']) && $params['where_custom_in']){
            foreach ($params['where_custom_in'] as $v){
                $modelQuery = $modelQuery->whereIn($v[0],$v[1]);
            }
        }
        if(isset($params['order'])){
            foreach ($params['order'] as $v){
                $modelQuery = $modelQuery->orderBy($v[0],$v[1]);
            }
        }else{
            if(Schema::hasColumn((new self())->getTable(),'sort')){
                $modelQuery = $modelQuery->orderBy('sort','asc');
            }else{
                $modelQuery = $modelQuery->orderBy('id','desc');
            }
        }
        return $modelQuery;
    }

    /**
     * Created by Lxd
     * @param string $key
     * @return false|int|string
     */
    private function _getFieldRuleKey(string $key)
    {
        $field = (new self())->fieldRule();
        foreach ($field as $k=>$v){
            if(!is_array($v)){
                continue;
            }
            if(in_array($key,$v)){
                return $k;
            }
        }
        return false;
    }

    /**
     * 仅用于model下业务需求符合:数据枚举仅两个值[开或关]
     * 默认字段为:status,可通过当前实现类属性自定义字段名,属性名为statusKey
     * 确保当前实现类已定义STATUS_YES和STATUS_NO常量
     * Created by Lxd
     * @param $primaryKey
     * @return array
     */
    public function statusUpdate(int $primaryKey):array
    {
        if(!$primaryKey){
            return ['code'=>456,'msg'=>'数据已被删除'];
        }
        try {
            $info = $this->findOne($primaryKey);
            if(!$info){
                return ['code'=>404,'msg'=>'数据已被删除'];
            }
            $statusKey = property_exists($this,'statusKey')  ? $this->statusKey : 'status';
            if(!Schema::hasColumn($this->getTable(),$statusKey)){
                throw new Exception("当前Model不存在状态字段{$statusKey}");
            }
            $className = __CLASS__;
            if(!defined("$className::STATUS_YES") || !defined("$className::STATUS_NO")){
                throw new Exception("非法调用状态方法(model未定义状态常量)");
            }
            $info->$statusKey = $info->$statusKey == self::STATUS_YES ? self::STATUS_NO : self::STATUS_YES;

            $info->save();
            return ['code'=>200,'msg'=>'状态修改成功'];
        }catch (Exception $e){
            return ['code'=>500,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 数据删除[未定义相对应的删除方法验证器,则直接删除数据]
     * 删除验证器定义:sele::$deteleValidate类下,按照Model名字拼接Delete组成方法名
     * Created by Lxd
     * @param int $primaryKey
     * @return array
     */
    public function deleteData(int $primaryKey,$hintKeyWord = '删除'):array
    {
        if(!$primaryKey){
            return ['code'=>456,'msg'=>'数据已被删除'];
        }
        try {
            $sourceModel = explode('\\',__CLASS__);
            $validateFunc = end($sourceModel).'Delete';
            if(
                method_exists(self::$deteleValidate,$validateFunc) &&
                (new \ReflectionMethod(self::$deteleValidate,$validateFunc))->isStatic())
            {
                /** @noinspection PhpUndefinedVariableInspection */
                $validate = self::$deteleValidate::$validateFunc([self::getPrimaryKey() => $primaryKey],$msg);
                if($validate !== true){
                    return ['code'=>456, 'msg'=>$msg];
                }
            }
            if($this::where(self::getPrimaryKey(),$primaryKey)->delete()){
                return ['code'=>200, 'msg'=>"{$hintKeyWord}成功"];
            }
            return ['code' => 456, 'msg' => "网络异常,{$hintKeyWord}失败"];
        }catch (Exception $e){
            return ['code'=>500,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 数据插入
     * Created by Lxd.
     * Created on 2021/4/18 11:30
     * @param array $params
     * @param bool $validata
     * @param string $successText
     * @return array
     */
    public function createData(array $params,$validata = true,$successText = '添加成功'):array
    {
        try {
            if($validata && $validataObj = self::getValidataMethod('create')){
                /** @noinspection PhpUndefinedVariableInspection */
                $validate = $validataObj['validataObj']::create($params,$msg);
                if($validate !== true){
                    return ['code'=>456, 'msg'=>$msg];
                }
            }
            //规避循环调用该方法直接$this未释放导致多次循环仅插入一条数据且频繁更新
            $model = new self();
            foreach ($params as $k=>$v){
                if($v && !is_string($v) && !is_int($v)){
                    throw new Exception("{$k}字段期望属性错误,非string类型");
                }
                if(Schema::hasColumn($model->getTable(),$k)){
                    $model->$k = $v;
                }
            }
            if(Schema::hasColumn($model->getTable(),'created_user')){
                $model->created_user = getAuth()->id;
            }
            if(!$model->save()){
                throw new Exception('网络异常,入库失败');
            }
        }catch (Exception $e){
            return ['code'=>302,'msg'=>$e->getMessage()];
        }
        $primaryKey = self::getPrimaryKey();
        return ['code' =>200, 'msg' => $successText,'insertId' => $model->$primaryKey];
    }

    /**
     * 数据修改
     * Created by Lxd
     * @param array $params
     * @param bool $validata
     * @param string $successText
     * @return array
     */
    public function updateData(array $params,$validata = true,$successText = '修改成功'):array
    {
        try {
            if($validata && $validataObj = self::getValidataMethod('update')){
                /** @noinspection PhpUndefinedVariableInspection */
                $validate = $validataObj['validataObj']::update($params,$msg);
                if($validate !== true){
                    return ['code'=>456, 'msg'=>$msg];
                }
            }
            $info = $this->findOne($params[self::getPrimaryKey()]);
            unset($params[self::getPrimaryKey()]);
            foreach ($params as $k=>$v){
                if($v && !is_string($v) && !is_int($v)){
                    throw new Exception("{$k}字段期望属性错误,非string类型");
                }
                if(Schema::hasColumn($this->getTable(),$k)){
                    $info->$k = $v;
                }
            }
            if(!$info->save()){
                throw new Exception('网络异常,入库失败');
            }
        }catch (Exception $e){
            return ['code'=>302,'msg'=>$e->getMessage()];
        }
        $primaryKey = $info->primaryKey;
        return ['code' =>200, 'msg' => $successText,'updateId' => $info->$primaryKey];
    }
}