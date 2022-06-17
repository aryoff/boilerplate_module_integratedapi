<?php

namespace Modules\IntegratedAPI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegratedAPIController extends Controller
{
    function send(int $id, object $data)
    {
        $response = new \stdClass;
        $response->status = 'Failed';
        try {
            $query = DB::select("SELECT profile FROM integratedapi_outbound_profiles WHERE id=?", [$id]);
            if (count($query) === 1) {
                Log::info($query[0]->profile);
                $profile = json_decode($query[0]->profile);
                $url = $profile->url;
                $header = array();
                if (property_exists($profile, 'header')) {
                    $header = $profile->header;
                }
                $auth = null;
                if (property_exists($profile, 'auth')) {
                    $auth = $profile->auth;
                }
                $username = null;
                if (property_exists($profile, 'username')) {
                    $username = $profile->username;
                }
                $password = null;
                if (property_exists($profile, 'password')) {
                    $password = $profile->password;
                }
                $fields = $profile->fields;
                foreach ($data as $key => $value) { //TODO $data perlu di definisikan di client atau di konversi disini ???
                    $fields->{$key} = $value;
                }
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
            # code
        }
        return $response;
    }
    public function APICallback(Request $request)
    {
        $response = (object) $request->all();
        $response->status = 'Success';
        return $response;
    }
    function cURLPost(string $URL, object $postfields, array $header = array(), $auth = null, $username = null, $password = null)
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
                    $auth_mode = CURLAUTH_BASIC;
                    break;
                case 'digest':
                    $auth_mode = CURLAUTH_DIGEST;
                    break;
                case 'ntlm':
                    $auth_mode = CURLAUTH_NTLM;
                    break;
                default:
                    $auth_mode = CURLAUTH_ANY;
                    break;
            }
            $option['CURLOPT_HTTPAUTH'] = $auth_mode;
        }
        if ($username != null && $password != null) {
            $option['CURLOPT_USERPWD'] = "$username:$password";
        }
        if ($header != array()) {
            $option['CURLOPT_HTTPHEADER'] = $header;
        }
        curl_setopt_array($curl, $option);
        $post_result = json_decode(curl_exec($curl));
        curl_close($curl);
        return $post_result;
    }
    function cURLGet($URL, $getfields)
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