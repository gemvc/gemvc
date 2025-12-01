<?php

namespace Gemvc\Core;

use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

/**
 * @protected  GemLibrary\Http\Request $request
 * @protected  null|string  $error
 * @function   validatePosts(array $post_schema):bool
 */
class Controller
{
    protected Request $request;
    protected ?string $error;

    public function __construct(Request $request)
    {
        $this->error = null;
        $this->request = $request;
    }

    /**
     * columns "id,name,email" only return id name and email
     * @param object $model
     * @param string|null $columns
     * @return JsonResponse
     */
    public function createList(object $model, ?string $columns = null): JsonResponse
    {
        /**@phpstan-ignore-next-line */
        return Response::success($this->_listObjects($model, $columns), $model->getTotalCounts(), 'list of ' . $model->getTable() . ' fetched successfully');
    }

    /**
     * columns "id,name,email" only return id name and email
     * @param object $model
     * @param string|null $columns
     * @return JsonResponse
     */
    public function listJsonResponse(object $model, ?string $columns = null): JsonResponse
    {
        /**@phpstan-ignore-next-line */
        return Response::success($this->_listObjects($model, $columns), $model->getTotalCounts(), 'list of ' . $model->getTable() . ' fetched successfully');
    }

    /**
     * Validates that required properties are set
     * @throws \RuntimeException
     */
    protected function validateRequiredProperties(): void
    {
        if (!method_exists($this, 'getTable') || empty($this->getTable())) {
            throw new \RuntimeException('Table name must be defined in the model');
        }
    }

    /**
     * Handles pagination parameters
     */
    private function _handlePagination(object $model): object
    {
        if (isset($this->request->get["page_number"])) {
            /**@phpstan-ignore-next-line */
            if (!is_numeric(trim($this->request->get["page_number"]))) {
                Response::badRequest("page_number shall be type if integer or number")->show();
                die();
            }
            /**@phpstan-ignore-next-line */
            $page_number = (int) $this->request->get["page_number"];
            if ($page_number < 1) {
                Response::badRequest("page_number shall be positive int")->show();
                die();
            }
            /**@phpstan-ignore-next-line */
            $model->setPage($page_number);
            return $model;
        }
        /**@phpstan-ignore-next-line */
        $model->setPage(1);
        return $model;
    }


    /**
     * Handles sorting/ordering parameters
     */
    private function _handleSortable(object $model): object
    {
        $sort_des = $this->request->getSortable();
        $sort_asc = $this->request->getSortableAsc();
        if ($sort_des) {
            /**@phpstan-ignore-next-line */
            $model->orderBy($sort_des);
        }
        if ($sort_asc) {
            /**@phpstan-ignore-next-line */
            $model->orderBy($sort_asc, true);
        }
        return $model;
    }


    private function _handleFindable(object $model): object
    {
        $array_orderby = $this->request->getFindable();
        if (count($array_orderby) == 0) {
            return $model;
        }
        foreach ($array_orderby as $key => $value) {
            $array_orderby[$key] = $this->_sanitizeInput($value);
        }
        $array_exited_object_properties = get_class_vars(get_class($model));
        foreach ($array_orderby as $key => $value) {
            if (!array_key_exists($key, $array_exited_object_properties)) {
                Response::badRequest("filterable key $key not found in object properties")->show();
                die();
            }
        }
        foreach ($array_orderby as $key => $value) {
            /**@phpstan-ignore-next-line */
            $model->whereLike($key, $value);
        }
        return $model;
    }


    /**
     * Handles all filter types (create where)
     */
    private function _handleSearchable(object $model): object
    {
        $arr_errors = null;
        $array_searchable = $this->request->getFilterable();
        if (count($array_searchable) == 0) {
            return $model;
        }
        foreach ($array_searchable as $key => $value) {
            $array_searchable[$key] = $this->_sanitizeInput($value);
        }
        $array_exited_object_properties = get_class_vars(get_class($model));
        foreach ($array_searchable as $key => $value) {
            if (!array_key_exists($key, $array_exited_object_properties)) {
                Response::badRequest("searchable key $key not found in object properties")->show();
                die();
            }
        }

        foreach ($array_searchable as $key => $value) {
            try {
                $model->$key = $value;
            } catch (\Exception $e) {
                $arr_errors .= $e->getMessage() . ",";
            }
        }

        if ($arr_errors) {
            Response::badRequest($arr_errors)->show();
            die();
        }
        foreach ($array_searchable as $key => $value) {
            /**@phpstan-ignore-next-line */
            $model->where($key, $value);
        }
        return $model;
    }


    /**
     * Basic input sanitization
     */
    private function _sanitizeInput(mixed $input): mixed
    {
        if (is_string($input)) {
            // Remove any null bytes
            $input = str_replace(chr(0), '', $input);
            // Convert special characters to HTML entities
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

        /**
     * @template T of object
     * @param T $model
     * @param string|null $columns
     * @return array<object>
     * array of objects from given model with pagination, sorting and filtering
     * columns "id,name,email" only return id name and email
     */
    private function _listObjects(object $model, ?string $columns = null): array
    {
        if (!method_exists($model, 'select')) {
            Response::internalError('Model must have select method i.e extended from Table or Model Class')->show();
            die();
        }
        if (!method_exists($model, 'run')) {
            Response::internalError('Model must have run() method i.e extended from Table or Model Class')->show();
            die();
        }
        $model = $this->_handleSearchable($model);
        $model = $this->_handleFindable($model);
        $model = $this->_handleSortable($model);
        $model = $this->_handlePagination($model);
        // @phpstan-ignore-next-line
        $result = $model->select($columns)->run();
        if($result === false) {
            // @phpstan-ignore-next-line
            Response::internalError($model->getError())->show();
            die();
        }
        /** @var array<T> $result */
        return $result;
    }
}
