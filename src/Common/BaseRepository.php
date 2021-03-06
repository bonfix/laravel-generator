<?php

namespace InfyOm\Generator\Common;

use Exception;
use Auth;

abstract class BaseRepository extends \Prettus\Repository\Eloquent\BaseRepository
{
    public function findWithoutFail($id, $columns = ['*'])
    {
        try {
            return $this->find($id, $columns);
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     */
    public function delete($id)
    {
        $attributes = array(
          'id'=>$id, 'deleted_by'=>Auth::id()
        );
        parent::update($attributes, $id);
        parent::delete($id);
    }

    public function create(array $attributes)
    {
        $newAttributes = array();
        $hasField = false;
        foreach ($attributes as $k=>&$v)
        {
            if($v == "")
                //$v = null;
                $newAttributes[$k] = null;
            else
                $newAttributes[$k] = $v;
            if($k == "created_by" && $v != null)
            {
                $hasField = true;
            }
        }
        if(!$hasField)
        {
           try
           {
               $id = Auth::id();
               $newAttributes["created_by"] = $id;
           }catch (Exception $e){}
        }
        $attributes = $newAttributes;

        // Have to skip presenter to get a model not some data
        $temporarySkipPresenter = $this->skipPresenter;
        $this->skipPresenter(true);
        $model = parent::create($attributes);
        $this->skipPresenter($temporarySkipPresenter);

        $model = $this->updateRelations($model, $attributes);
        $model->save();

        return $this->parserResult($model);
    }

    public function update(array $attributes, $id)
    {
        //fix "" issue with int cols
//        foreach ($attributes as $k=>&$v)
//        {
//            if($v == "")
//                $v = null;
//            if($k == "updated_by" && $v == null)
//            {
//                $id = Auth::id();
//                $v = $id;
//            }
//        }
        $newAttributes = array();
        $hasField = false;
        foreach ($attributes as $k=>&$v)
        {
            if($v == "")
                //$v = null;
                $newAttributes[$k] = null;
            else
                $newAttributes[$k] = $v;
            if($k == "updated_by" && $v != null)
            {
                $hasField = true;
            }
        }
        if(!$hasField)
        {
            try
            {
                $id = Auth::id();
                $newAttributes["updated_by"] = $id;
            }catch (Exception $e){}
        }
        $attributes = $newAttributes;

        // Have to skip presenter to get a model not some data
        $temporarySkipPresenter = $this->skipPresenter;
        $this->skipPresenter(true);
        $model = parent::update($attributes, $id);
        $this->skipPresenter($temporarySkipPresenter);

        $model = $this->updateRelations($model, $attributes);
        $model->save();

        return $this->parserResult($model);
    }

    public function updateRelations($model, $attributes)
    {
        foreach ($attributes as $key => $val) {
            if (isset($model) &&
                method_exists($model, $key) &&
                is_a(@$model->$key(), 'Illuminate\Database\Eloquent\Relations\Relation')
            ) {
                $methodClass = get_class($model->$key($key));
                switch ($methodClass) {
                    case 'Illuminate\Database\Eloquent\Relations\BelongsToMany':
                        $new_values = array_get($attributes, $key, []);
                        if (array_search('', $new_values) !== false) {
                            unset($new_values[array_search('', $new_values)]);
                        }
                        $model->$key()->sync(array_values($new_values));
                        break;
                    case 'Illuminate\Database\Eloquent\Relations\BelongsTo':
                        $model_key = $model->$key()->getForeignKey();
                        $new_value = array_get($attributes, $key, null);
                        $new_value = $new_value == '' ? null : $new_value;
                        $model->$model_key = $new_value;
                        break;
                    case 'Illuminate\Database\Eloquent\Relations\HasOne':
                        break;
                    case 'Illuminate\Database\Eloquent\Relations\HasOneOrMany':
                        break;
                    case 'Illuminate\Database\Eloquent\Relations\HasMany':
                        $new_values = array_get($attributes, $key, []);
                        if (array_search('', $new_values) !== false) {
                            unset($new_values[array_search('', $new_values)]);
                        }

                        list($temp, $model_key) = explode('.', $model->$key($key)->getForeignKey());

                        foreach ($model->$key as $rel) {
                            if (!in_array($rel->id, $new_values)) {
                                $rel->$model_key = null;
                                $rel->save();
                            }
                            unset($new_values[array_search($rel->id, $new_values)]);
                        }

                        if (count($new_values) > 0) {
                            $related = get_class($model->$key()->getRelated());
                            foreach ($new_values as $val) {
                                $rel = $related::find($val);
                                $rel->$model_key = $model->id;
                                $rel->save();
                            }
                        }
                        break;
                }
            }
        }

        return $model;
    }
}
