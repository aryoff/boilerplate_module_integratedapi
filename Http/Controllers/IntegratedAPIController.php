<?php

namespace Modules\IntegratedAPI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

define('AUTHORIZATION', 'Authorization');
define('FAILED', 'failed');
class IntegratedAPIController extends Controller
{
    public function APICallback(Request $request)
    {
        $response = new \stdClass;
        $data = (object) $request->all();
        $ip = $request->ip();
        $id = $this->propertyCheckAssign($data, 'id', false);
        try {
            if ($id) {
                $id = Crypt::decrypt($id);
            }
            $header = '';
            if ($request->hasHeader(AUTHORIZATION)) {
                $header = $request->header(AUTHORIZATION);
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }
            $username = substr($header, 0, strpos($header, ':'));
            $password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
            $source = DB::select("SELECT parameter->'field' AS parameter,dwh_partner_id FROM dwh_sources CROSS JOIN (SELECT :ip AS ip,:username AS username,:password AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip, 'username' => $username, 'password' => $password]); //ambil parameter dari table source sesuai dengan id
            if (count($source) === 1) {
                // $this->executeInputInteraction($source, (object) $request->all(), $id);
                // $classname = 'Modules\IntegratedAPI\Http\Controllers\IntegratedAPIController';
                // $API = new $classname();
                // $APIData = json_decode($affected_data[0]->data);
                // $API->send(2, $APIData); //Kirim data ke dwh

            } else { //Source select failed
                Log::critical('Failed to authenticate from ' . $ip);
                $response->status = FAILED;
            }
        } catch (DecryptException $decryptErr) { //Decryption failed
            Log::critical('Failed to decrypt ID from ' . $ip);
            $response->status = FAILED;
        }

        $response->status = 'Success';
        return $response;
    }
    function send(int $id, object $data)
    {
        $response = new \stdClass;
        $response->status = 'Failed';
        try {
            $query = DB::select("SELECT profile FROM integratedapi_outbound_profiles WHERE id=?", [$id]);
            if (count($query) === 1) {
                $profile = json_decode($query[0]->profile);
                $url = $profile->url;
                $header = $this->propertyCheckAssign($profile, 'header', array());
                $auth = $this->propertyCheckAssign($profile, 'auth');
                $username = $this->propertyCheckAssign($profile, 'username');
                $password = $this->propertyCheckAssign($profile, 'password');
                $fields = $this->fieldConversion($profile, $data);
                if (property_exists($profile, 'mode')) {
                    switch ($profile->mode) {
                        case 'post':
                            $response = $this->cURLPost($url, $fields, $header, $auth, $username, $password);
                            break;
                        case 'get':
                            $response = $this->cURLGet($url, $fields);
                            break;
                        default:
                            # code...
                            break;
                    }
                } else {
                    $response = $this->cURLPost($url, $fields, $header, $auth, $username, $password);
                }
            }
        } catch (QueryException $e) {
            Log::error($e);
        }
        return $response;
    }
    function propertyCheckAssign(object $object, string $element, $default_value = null)
    {
        if (property_exists($object, $element)) {
            return $object->{$element};
        } else {
            return $default_value;
        }
    }
    function fieldConversion(object $object, object $data)
    {
        $fields = $object->fields;
        if (property_exists($object, 'field_conversion')) {
            foreach ($object->field_conversion as $element) { //Konversi data
                if (property_exists($data, $element->source)) {
                    $fields->{$element->target} = $data->{$element->source};
                }
            }
        } else {
            foreach ($data as $key => $value) { //default semua data include
                $fields->{$key} = $value;
            }
        }
        return $fields;
    }
    function cURLPost(string $URL, object $postfields, array $header = array(), string $auth = null, string $username = null, string $password = null)
    {
        $curl = curl_init();
        $option = array(
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPAUTH => CURLAUTH_ANY,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        );
        if ($auth != null) {
            switch ($auth) {
                case 'basic':
                    $option[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                    break;
                case 'digest':
                    $option[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;
                    break;
                case 'ntlm':
                    $option[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;
                    break;
                default:
                    $option[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
                    break;
            }
        }
        if ($username != null && $password != null) {
            $option[CURLOPT_USERPWD] = "$username:$password";
        }
        if ($header != array()) {
            $option[CURLOPT_HTTPHEADER] = $header;
        }
        curl_setopt_array($curl, $option);
        $post_result = json_decode(curl_exec($curl));
        curl_close($curl);
        return $post_result;
    }
    function cURLGet(string $URL, object $getfields)
    {
        $get_url = $URL . '?';
        foreach ($getfields as $key => $value) {
            $get_url .= $key . '=' . $value . '&';
        }
        $get_url = substr($get_url, 0, strlen($get_url) - 1);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $get_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $get_result = curl_exec($curl);
        curl_close($curl);
        return json_decode($get_result);
    }
}