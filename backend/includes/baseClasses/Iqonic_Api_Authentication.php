<?php

namespace Includes\baseClasses;

use Firebase\JWT\JWT;
use WP_Error;

class Iqonic_Api_Authentication
{
    private $tokenurl;

    private $validatetokenurl;

    private $response = array();

    public function _construct()
    {

        $this->tokenurl = $this->iqonic_get_token_url();
        $this->validatetokenurl = $this->iqonic_get_validate_token_url();
    }

    public function iqonic_validate_request($user)
    {
        //return $user;
        $data = $this->iqonic_generate_token($user);


        if ($data['response']['code'] == 200) {
            $token = (array)json_decode($data['body'], true);

            $valdata = $this->iqonic_validate_token($token['token']);
            //$valdata = $this->iqonic_validate_token('l;l;l;');
            if ($valdata['response']['code'] == 200) {
                return $this->response = array(
                    "user_id" => $token['user_id'],
                    "code" => "Authorized Request",
                    "status" => 200,
                    "message" => "valid token"
                );
            } else {
                $error = (array)json_decode($valdata['body'], true);

                return $this->response = array(
                    "code" => $error['code'],
                    "message" => utf8_encode($error['message']),
                    "data" => array(
                        "status" => $error['data']['status']
                    )
                );
            }
        } else {
            $error = (array)json_decode($data['body'], true);

            return $this->response = array(
                "code" => $error['code'],
                "message" => utf8_encode($error['message']),
                "data" => array(
                    "status" => $error['data']['status']
                )
            );
        }
    }

    private function iqonic_get_token_url()
    {
        return get_home_url() . "/wp-json/jwt-auth/v1/token";
    }
    private function iqonic_get_validate_token_url()
    {
        return get_home_url() . "/wp-json/jwt-auth/v1/token/validate";
    }

    private function iqonic_generate_token($data)
    {
        return $response = wp_remote_post($this->iqonic_get_token_url(), array(
            'body' => $data
        ));
    }

    public function iqonic_validate_token($token)
    {
        $response = wp_remote_post($this->iqonic_get_validate_token_url(), array(
            'body' => null,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            )

        ));

        return $response;
    }

    public function iqonic_validate_social($data)
    {
        $data = $this->iqonic_generate_token($data);
        return $data['body'];
    }

    public function iqonic_otp_login_token($user)
    {

        if (!$user) {
            $error_code = $user->get_error_code();
            return new WP_Error(
                '[jwt_auth] ' . $error_code,
                $user->get_error_message($error_code),
                array(
                    'status' => 403,
                )
            );
        }

        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        $issuedAt   = time();
        $notBefore  = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
        $expire     = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

        $token = array(
            'iss'   => get_bloginfo('url'),
            'iat'   => $issuedAt,
            'nbf'   => $notBefore,
            'exp'   => $expire,
            'data'  => array(
                'user' => array(
                    'id' => $user->ID,
                ),
            )
        );

        /** Let the user modify the token data before the sign. */
        $token = JWT::encode(apply_filters('jwt_auth_token_before_sign', $token, $user), $secret_key, 'HS256');

        /** The token is signed, now create the object with no sensible user data to the client*/
        $data = array(
            'token'             => $token,
            'user_email'        => $user->user_email,
            'user_nicename'     => $user->user_nicename,
            'user_display_name' => $user->display_name
        );

        /** Let the user modify the data before send it back */
        return apply_filters('jwt_auth_token_before_dispatch', $data, $user);
    }
}
