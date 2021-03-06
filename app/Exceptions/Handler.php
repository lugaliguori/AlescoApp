<?php

namespace App\Exceptions;


use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\ApiResponser;
use Exception;
use Asm89\Stack\CorsService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }
    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    public function handleException ($request, Exception $exception)
    {
         if ($exception instanceof ValidationException){
            return $this->convertValidationExceptionToResponse($exception,$request);
        }

        if ($exception instanceof ModelNotFoundException){
            $modelo = class_basename($exception->getModel());
            return $this->errorResponse("No existe ningun id especificado en el modelo {$modelo}",404);
        }

        if ($exception instanceof NotFoundHttpException){
            return $this->errorResponse('No se encontro la url especificada',404);
        }

         if ($exception instanceof MethodNotAllowedHttpException){
            return $this->errorResponse('El metodo especificado no es valido',405);
        }

        if ($exception instanceof HttpException){
            return $this->errorResponse($exception->getMessage(),$exception->getStatusCode());
        }

        if ($exception instanceof QueryException){
            $codigo = $exception->errorInfo[1];
            
            if ($codigo == 1451) {
                return $this->errorResponse('No se puede eliminar de forma permanete debido a que otro recurso tiene un id foraneo relacionado a el.',409);
            } else{
                return $this->errorResponse('There was an error connecting to database',400);
            }
        }    
        if ($exception instanceof HttpException){
            return $this->errorResponse($exception->getMessage(),$exception->getStatusCode());
        }

        return $this->errorResponse('Falla inesperada intente de nuevo',500);
    }
}
