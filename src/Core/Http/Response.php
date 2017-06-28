<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2017/6/15
 * Time: 下午3:56
 */

namespace Core\Http;
use Conf\Event;
use Core\Dispatcher;
use Core\Http\Message\Response as HttpResponse;
use Core\Http\Message\Status;

class Response extends HttpResponse
{
    private $isEndResponse = 0;
    protected static $instance;
    /*
     * Core Instance is a singleTon in a request lifecycle
     * @return Response instance
     */
    static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new Response();
        }
        return self::$instance;
    }
    function end(){
        if(!$this->isEndResponse){
            $this->isEndResponse = 1;
            $status = $this->getStatusCode();
            $reason = $this->getReasonPhrase();
            //状态码有固定格式。
            header('HTTP/1.1 '.$status.' '.$reason);
            // 确保FastCGI模式下正常
            header('Status:'.$status.' '.$reason);
            $headers = $this->getHeaders();
            foreach ($headers as $header => $val){
                foreach ($val as $sub){
                    header($header .':'.$sub);
                }
            }
            echo $this->getBody()->__toString();
            $this->getBody()->close();
            return true;
        }else{
            return false;
        }
    }

    function isEndResponse(){
        return $this->isEndResponse;
    }
    function write($obj){
        if(!$this->isEndResponse()){
            if(is_object($obj)){
                if(method_exists($obj,"__toString")){
                    $obj = $obj->__toString();
                }else{
                    $obj = json_encode($obj,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                }
            }else if(is_array($obj)){
                $obj = json_encode($obj,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $this->getBody()->write($obj);
            return true;
        }else{
            trigger_error("response has end");
            return false;
        }
    }
    function writeJson($statusCode = 200,$result,$msg = ''){
        if(!$this->isEndResponse()){
            $this->getBody()->rewind();
            $data = Array(
                "code"=>$statusCode,
                "result"=>$result,
                "msg"=>$msg
            );
            $this->getBody()->write(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $this->withHeader('Content-type','application/json;charset=utf-8');
            $this->withStatus($statusCode);
            return true;
        }else{
            trigger_error("response has end");
            return false;
        }
    }
    function redirect($url){
        if(!$this->isEndResponse()){
            //仅支持header重定向  不做meta定向
            $this->withStatus(Status::CODE_MOVED_TEMPORARILY);
            $this->withHeader('Location',$url);
        }else{
            trigger_error("response has end");
        }
    }
    public function setCookie($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = null){
        if(!$this->isEndResponse()){
            //仅支持header重定向  不做meta定向
            $temp = " {$name}={$value};";
            if($expire != null){
                $temp .= " Expires=".date("D, d M Y H:i:s",$expire) . ' GMT;';
                $maxAge = $expire - time();
                $temp .=" Max-Age={$maxAge};";
            }
            if($path != null){
                $temp .= " Path={$path};";
            }
            if($domain != null){
                $temp .= " Domain={$domain};";
            }
            if($secure != null){
                $temp .=" Secure;";
            }
            if($httponly != null){
                $temp .=" HttpOnly;";
            }
            $this->withAddedHeader('Set-Cookie',$temp);
            return true;
        }else{
            trigger_error("response has end");
            return false;
        }

    }
    function forward($pathTo,array $attribute = array()){
        if(!$this->isEndResponse()){
            $request = Request::getInstance();
            $response = Response::getInstance();
            $request->getUri()->withPath($pathTo);
            foreach ($attribute as $key => $value){
                $request->withAttribute($key,$value);
            }
            //执行OnRequest事件
            Event::getInstance()->onRequest($request,$response);
            Dispatcher::getInstance()->dispatch($request,$response);
        }else{
            trigger_error("response has end");
        }
    }
}