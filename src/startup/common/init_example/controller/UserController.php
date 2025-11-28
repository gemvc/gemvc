<?php

namespace App\Controller;

use App\Model\UserModel;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\JWTToken;
use Gemvc\Http\Response;
use stdClass;

class UserController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Create new User
     * 
     * @return JsonResponse
     */
    public function create(): JsonResponse
    {   
        //'password'=>'setPassword()' call the setPassword() method in the UserModel class
        $model = $this->request->mapPostToObject(new UserModel(),['email'=>'email','name'=>'name','description'=>'description','password'=>'setPassword()']);
        if(!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        return $model->createModel();
    }

    /**
     * Get User by ID
     * 
     * @return JsonResponse
     */
    public function read(): JsonResponse
    {
        $model = $this->request->mapPostToObject(new UserModel());
        if(!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        return $model->readModel();
    }

    /**
     * Update existing User
     * 
     * @return JsonResponse
     */
    public function update(): JsonResponse
    {
        $model = $this->request->mapPostToObject(new UserModel());
        if(!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        return $model->updateModel();
    }

    /**
     * Delete User
     * 
     * @return JsonResponse
     */
    public function delete(): JsonResponse
    {
        $model = $this->request->mapPostToObject(new UserModel());
        if(!$model) {
            return $this->request->returnResponse();
        }
        return $model->deleteModel();
    }

    /**
     * Get list of Users with filtering and sorting
     * 
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $model = new UserModel();
        return $this->createList($model);
    }

    public function loginByEmailPassword(): JsonResponse
    {
        //return Response::success($this->request->post, 1,"Token is valid");
        $model = new UserModel();
        $email = $this->request->post['email'];
        $password = $this->request->post['password'];
        return $model->loginByEmailPassword($email, $password);
    }

    public function validateToken(): JsonResponse
    {
        
        $token = $this->request->getJwtToken();
        if (!$token) {
            return Response::forbidden("No token provided");
        }
        if(!$token->verify()){
            return Response::unauthorized("Invalid or expired token");
        }
        return Response::success(null, 1,"Token is valid");
    }

    public function renewToken(): JsonResponse
    {
        $token = $this->request->getJwtToken();
        if (!$token) {
            return Response::forbidden("No token provided");
        }
        $token = $token->verify();
        if(!$token){
            return Response::unauthorized("Invalid or expired token");
        }
        $tokenType = $token->GetType();
        $new_token = null;
        if($tokenType === 'refresh'){
            $new_token = $token->renew($_ENV['REFRESH_TOKEN_VALIDATION_IN_SECONDS']);
        }
        if($tokenType === 'access') {
            $new_token = $token->renew($_ENV['ACCESS_TOKEN_VALIDATION_IN_SECONDS']);
        }
        if($tokenType === 'login') {
            $new_token = $token->renew($_ENV['LOGIN_TOKEN_VALIDATION_IN_SECONDS']);
        }
        $std = new stdClass();
        $std->token = $new_token;
        return Response::success($std, 1,$tokenType."token renewed successfully");  
    }

} 