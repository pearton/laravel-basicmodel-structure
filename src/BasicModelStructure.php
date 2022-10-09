<?php
/**
 * BasicModelStructure.php
 * Created on 2021/8/12 16:28
 * Created by Lxd.
 * QQ: 790125098
 */

namespace Peartonlixiao\LaravelOrmTool;

use App\Models\BaseModel;
use App\Models\Frame\Permission;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
            if(!Schema::hasColumn(self::getTableName(),$statusKey)){
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
        return ['eq' => [],'like' => [],'in' => []];
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
        if(Schema::hasColumn(self::getTableName(),'created_user')){
            return $this->belongsTo('App\Models\Frame\User','created_user','id');
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
     * 作用方法:model表名获取
     * Created by Lxd.
     * @return mixed
     */
    public static function getTableName()
    {
        return DB::getConfig('prefix').(new self())->getTable();
    }

    /**
     * 作用方法:获取排序值_根据最大ID+1
     * @Author Pearton <pearton@126.com>
     * @return int
     */
    public static function getSortIdMax(): int
    {
        return (new self())->max(self::getPrimaryKey()) + 1;
    }

    /**
     * 作用方法:作用方法:获取排序值_根据最大排序+1
     * @Author Pearton <pearton@126.com>
     * @param $field
     * @return int
     */
    public static function getSort($field = 'sort'): int
    {
        return (new self())->max($field) + 1;
    }

    /**
     * 主键检索
     * Created by Lxd.
     * @param int $primaryKey
     * @param false $field
     * @return mixed
     * @throws Exception
     */
    public function findOne(int $primaryKey,$field = false)
    {
        if($field){
            if(!Schema::hasColumn(self::getTableName(),$field)){
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
     * @param array $ormSearch
     * @param bool $toArray
     * @return mixed
     * @throws Exception
     */
    public static function findByField(array $params,array $ormSearch = [],$toArray = false)
    {
        $query = (new self());

        try {
            //orm关联查询
            $query = self::buildOrmRelation($query,$ormSearch);
        }catch (Exception $e){
            throw new Exception("查询orm关系查询异常:{$e->getMessage()}");
        }

        $re = self::buildQuery($query,$params)->first();
        return ($toArray && $re) ? $re->toArray() : $re;
    }

    /**
     * model检索
     * Created by Lxd
     * @param array $params |检索数组参数
     * @param array $ormSearch |laravel Orm关系查询
     * @param false $toArray |数组化
     * @param false $obj |非数组化(数组化指定所有数据,该参用以说明获取所有数据但不数组化)
     * @return mixed
     * @throws Exception
     */
    public function search(array $params,array $ormSearch = [],$toArray = false,$obj = false)
    {
        $query = $this;

        try {
            //orm关联查询
            $query = $this::buildOrmRelation($query,$ormSearch);
        }catch (Exception $e){
            throw new Exception("查询orm关系查询异常:{$e->getMessage()}");
        }

        try {
            //链式查询构建
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

        if(isset($params['fast_data']) && $params['fast_data']){
            $date = BaseiModelTool::getInstance()->getDateRange($params['fast_data'],$params['time_horizon'] ?? '');

            if($date['date1'] && $date['date2']){
                if(!Schema::hasColumn(self::getTableName(),$field)){
                    throw new Exception("当前Model不存在字段{$field}");
                }
                array_push($where_custom,[$field,'>=',$date['date1']]);
                array_push($where_custom,[$field,'<=',$date['date2']]);
            }
        }
        return $where_custom;
    }

    /**
     * 作用方法:构建基于laravel Orm的关联关系查询
     * Created by Lxd.
     * @param $modelQuery
     * @param array $ormSearch
     * @return mixed
     */
    private static function buildOrmRelation($modelQuery,$ormSearch = [])
    {
        //with关联关系查询
        if(isset($ormSearch['with'])){
            $modelQuery = $modelQuery->with($ormSearch['with']);
        }
        //withCount关联关系查询
        if(isset($ormSearch['withCount'])){
            $modelQuery = $modelQuery->withCount($ormSearch['withCount']);
        }
        //has关联关系查询
        if(isset($ormSearch['has'])){
            foreach ($ormSearch['has'] as $v){
                if(isset($v[1]) && isset($v[2])){
                    $modelQuery = $modelQuery->has($v[0],$v[1],$v[2]);
                }else{
                    $modelQuery = $modelQuery->has($v[0]);
                }
            }
        }
        //whereHas关联关系查询
        if(isset($ormSearch['whereHas'])){
            foreach ($ormSearch['whereHas'] as $v){
                $modelQuery = $modelQuery->whereHas($v[0],$v[1]);
            }
        }
        //doesntHave关联关系查询
        if(isset($ormSearch['doesntHave'])){
            foreach ($ormSearch['doesntHave'] as $v){
                $modelQuery = $modelQuery->doesntHave($v[0]);
            }
        }
        //whereDoesntHave关联关系查询
        if(isset($ormSearch['whereDoesntHave'])){
            foreach ($ormSearch['whereDoesntHave'] as $v){
                $modelQuery = $modelQuery->whereDoesntHave($v[0],$v[1]);
            }
        }

        return $modelQuery;
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
                    //没找到声明,默认eq查询|值=0也将用于条件检索 所以注意 如无必要,请不要定义value=0的枚举值
                    if(Schema::hasColumn(self::getTableName(),$k) && ($v || $v == "0")){
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
        if(isset($params['where_raw']) && $params['where_raw']){
            if(is_array($params['where_raw'])){
                foreach ($params['where_raw'] as $v){
                    $modelQuery = $modelQuery->whereRaw($v);
                }
            }else {
                $modelQuery = $modelQuery->whereRaw($params['where_raw']);
            }
        }
        if(isset($params['order'])){
            foreach ($params['order'] as $v){
                $modelQuery = $modelQuery->orderBy($v[0],$v[1]);
            }
        }else{
            if(Schema::hasColumn(self::getTableName(),'sort')){
                $modelQuery = $modelQuery->orderBy('sort','desc')->orderBy('id','desc');
            }elseif(Schema::hasColumn(self::getTableName(),'id')){
                $modelQuery = $modelQuery->orderBy('id','desc');
            }
        }
        if(isset($params['select']) && $params['select']){
            $modelQuery->select($params['select']);
        }
        if(isset($params['selectRaw']) && $params['selectRaw']){
            $modelQuery = $modelQuery->selectRaw($params['selectRaw']);
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
            if(!Schema::hasColumn(self::getTableName(),$statusKey)){
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
     * @param string $hintKeyWord
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
                if($v && !is_string($v) && !is_int($v) && !is_float($v)){
                    throw new Exception("{$k}字段期望属性错误,非string类型");
                }
                if(Schema::hasColumn(self::getTableName(),$k)){
                    $model->$k = $v;
                }
            }
            if(Schema::hasColumn(self::getTableName(),'created_user')){
                #该处如需要记录后台创建人,则需要开发者根据自己业务进行修改
                #自行确定使用的鉴权方式,以获得后台操作人ID
                if(function_exists('getAuth')){
                    $model->created_user = isset(getAuth()->id) ? getAuth()->id : 0;
                }
            }
            if(Schema::hasColumn(self::getTableName(),'sort') && (!isset($params['sort']) || !$params['sort'])){
                #自动写入排序字段
                $model->sort = self::getSort();
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
            if(!isset($params[self::getPrimaryKey()])){
                throw new Exception("未传递表主键值");
            }
            $info = $this->findOne($params[self::getPrimaryKey()]);
            unset($params[self::getPrimaryKey()]);
            foreach ($params as $k=>$v){
                if($v && !is_string($v) && !is_int($v) && !is_float($v)){
                    throw new Exception("{$k}字段期望属性错误,非string类型");
                }
                if(Schema::hasColumn(self::getTableName(),$k)){
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

    /**
     * 作用方法:排序修改
     * Created by Lxd.
     * @param $params
     * @param string $successText
     * @return array
     */
    public function updateSort(array $params,string $successText = '更新成功'):array
    {
        $params['field'] = $params['field'] ?? 'sort';

        $idKey = self::getPrimaryKey();

        try {
            if(!isset($params[$idKey])){
                throw new Exception('未传递表主键');
            }

            if(!Schema::hasColumn(self::getTableName(),$params['field'])){
                throw new Exception("当前Model不存在排序字段{$params['field']}");
            }
            $rules = [
                $idKey => ['required','integer',Rule::exists($this::getTableName(),$idKey)],
                'value' => 'required|max:255',
                'field' => 'nullable|max:50'
            ];
            $customAttributes = [
                $idKey => '主键',
                'value' => '排序值',
                'field' => '字段',
            ];
            $validator = Validator::make($params, $rules, [],$customAttributes);
            if ($validator->fails()) {
                $msg = $validator->errors()->first();
                throw new Exception($msg);
            }

            $updateData = [
                $params['field']  =>$params['value'],
                'updated_at'  =>getDateTime()
            ];
            $res = DB::table($this::getTableName())->where($idKey,$params[$idKey])->update($updateData);
            if($res){
                return ['code' =>200, 'msg' => $successText,'updateId' => $params[$idKey]];
            }
            throw new Exception("网络异常,更新失败.");
        }catch (Exception $e){
            return ['code'=>456,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 作用方法:字段修改
     * Created by Lxd.
     * @param array $params
     * @param string $successText
     * @return array
     */
    public function updateField(array $params,string $successText = '更新成功'):array
    {
        $params['is_unique'] = $params['is_unique'] ?? 'false';
        $params['can_null'] = $params['can_null'] ?? 'false';

        $params['table'] = self::getTableName();
        $idKey = self::getPrimaryKey();

        try {
            if(!isset($params[$idKey])){
                throw new Exception('未传递表主键');
            }
            if(!Schema::hasColumn(self::getTableName(),$params['field'])){
                throw new Exception("当前Model不存在字段{$params['field']}");
            }

            $rules = [
                'value' => 'nullable|max:255',
                'field' => 'required|max:255',
                'is_unique' => 'required',
                'can_null' => 'required'
            ];
            if(!Schema::hasColumn($params['table'],'deleted_at')){
                $rules[$idKey] = ['required','integer',Rule::exists($params['table'],$idKey)];
            }else{
                $rules[$idKey] = ['required','integer',Rule::exists($params['table'],$idKey)->where(function ($query){
                    $query->whereNull('deleted_at');
                })];
            }
            $customAttributes = [
                'table'=>'数据表',
                $idKey => '主键',
                'value' => '值',
                'field' => '字段',
                'is_unique' => '是否唯一',
                'can_null' => '是否可为空'
            ];
            $validator = Validator::make($params, $rules, [],$customAttributes);
            if ($validator->fails()) {
                $msg = $validator->errors()->first();
                throw new Exception($msg);
            }
            if($params['can_null'] !== 'true' && !$params['value']){
                throw new Exception('请填写值内容');
            }
            if($params['is_unique'] == 'true'){
                #是否已存在校验
                if(DB::table($params['table'])->where($idKey,'<>',$params[$idKey])->where($params['field'],$params['value'])->count()){
                    throw new Exception("当前值:{$params['value']}已存在");
                }
            }
            $updateData = [
                $params['field']  =>$params['value'],
                'updated_at'  =>getDateTime()
            ];
            $res = DB::table($params['table'])->where($idKey,$params[$idKey])->update($updateData);
            if($res){
                switch ($params['table']){
                    case 'permissions':
                        #更新权限缓存
                        (new Permission())->cache();
                        break;
                    case 'navigations':
                        #更新redis
                        (new BaseModel())->redisOperation(BaseModel::$redisKeyArr['navigation'],'set');
                        break;
                }
                return ['code' =>200, 'msg' => $successText,'updateId' => $params[$idKey]];
            }
            throw new Exception("网络异常,更新失败.");
        }catch (Exception $e){
            return ['code'=>456,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 作用方法:批量更新-基于model实例化
     * @Author Pearton <pearton@126.com>
     * @param array $multipleData
     * @return bool
     */
    public function updateAllBatchByModel(array $multipleData):bool
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . $this->getTable(); // 表名
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
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