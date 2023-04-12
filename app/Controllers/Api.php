<?php

namespace App\Controllers;

use App\Models\AuthHeaderModel;
use App\Models\SettingModel;
use App\Models\PackageModel;
use App\Models\UserModel;

class Api extends BaseController
{

    protected $postBody;
    protected $authModel;

    protected $settingModel;
    protected $packageModel;

    protected $userModel;

    private $sessLogin;
    protected $db;

    public function __construct()
    {
        $this->authModel = new AuthHeaderModel();
        $this->settingModel = new SettingModel();
        $this->packageModel = new PackageModel();
        $this->userModel = new UserModel();
        $this->sessLogin = session();
        $this->db = db_connect();
    }

    public function index()
    {
        $json = array(
            "result" => array(),
            "code" => "200",
            "message" => "Success",
        );

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }


    public function get_data()
    {
        $this->postBody = $this->authModel->authHeader($this->request);

        $offset = 0;
        $limit = 10;

        $getLimit = $this->request->getVar('lt');
        if ($getLimit != '') {
            $exp = explode(",", $getLimit);
            $offset = (int) $exp[0];
            $limit = (int) $exp[1];

        }

        //master setting
        $dataSetting = $this->settingModel->allByLimit($limit, $offset);

        //master package detail
        $dataPackage = $this->packageModel->allByLimit($limit, $offset);

        $results = array();
        $results['settings'] = $dataSetting;
        $results['packages'] = $dataPackage;

        $json = array(
            "result" => $results,
            "code" => "200",
            "message" => "Success",
        );

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function check_email_phone()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $arr = array();

        if ($this->postBody['em'] == '' && $this->postBody['ph'] == '') {

        } else {
            $checkExist = null;
            if ($this->postBody['ph'] != '') {
                $checkExist = $this->userModel->getByPhone($this->postBody['ph']);
            } else if ($this->postBody['em'] != '') {
                $checkExist = $this->userModel->getByEmail($this->postBody['em']);
            }

            if ($checkExist != null) {
                $arr = [$checkExist];
            }
        }

        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Data not found",
            );
        } else {
            $json = array(
                "result" => $arr,
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function login()
    {
        $this->sessLogin = session();
        $check = $this->authModel->checkSession($this->sessLogin);
        if (!$check) {
            $json = array(
                "result" => array(),
                "code" => "200",
                "message" => "Success",
            );
            echo json_encode($json);
        }
        $this->postBody = $this->authModel->authHeader($this->request);

        $offset = 0;
        $limit = 10;

        $getLimit = $this->request->getVar('lt');
        if ($getLimit != '') {
            $exp = explode(",", $getLimit);
            $offset = (int) $exp[0];
            $limit = (int) $exp[1];

        }

        //123456    cfc5902918296762903710e9c9a65580
        $passwrd = $this->generatePassword($this->postBody['ps']);
        $dataUser = $this->userModel->loginByEmail($this->postBody['em'], $passwrd);

        if ($this->postBody['is'] != '' && $dataUser['id_user'] != '') {
            $this->postBody['id'] = $dataUser['id_user'];
            $this->userModel->updateUser($this->postBody);
        }

        $arr = $dataUser;
        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Email/Username & Password invalid",
            );
        } else {
            $json = array(
                "result" => $arr,
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    /*
    getByReferralCode() - This function retrieves user data based on the referral code.
    generateReferralCode() - This function generates a unique referral code for a new user.
    updateReferralCode() - This function updates the referral code of a user.
    */


    public function getIdByReferralCode($referral_code)
    {
        $user = $this->db->get_where('tb_user', array('referral_code' => $referral_code))->row();
        if ($user) {
            return $user->id_user;
        } else {
            return null;
        }
    }
    public function isReferralCodeUsed($referral_code)
    {
        $user = $this->db->get_where('tb_user', array('referral_code' => $referral_code))->row();
        if ($user) {
            return ($user->referred_by != null);
        } else {
            return false;
        }
    }
    public function generateReferralCode()
    {
        $referral_code = '';
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($i = 0; $i < 8; $i++) {
            $referral_code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $referral_code;
    }
    public function updateReferralCode($user_id, $new_referral_code)
    {
        $data = array(
            'referral_code' => $new_referral_code
        );

        $this->db->where('id_user', $user_id);
        $this->db->update('tb_user', $data);
    }
    public function generate_referral_code()
    {
        $referral_code = uniqid();
        // Add any additional logic to format or modify the referral code as needed
        return $referral_code;
    }
    public function register()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        if ($this->postBody['ps'] != '' && $this->postBody['em'] != '') {
            $checkExist = $this->userModel->getByEmail($this->postBody['em']);
            if ($checkExist['id_user'] == '') {
                $this->postBody['ps'] = $this->generatePassword($this->postBody['ps']);
                if ($this->postBody['code'] != '') {
                    $checkExistRef = $this->userModel->getByRef($this->postBody['code']);
                    if ($checkExistRef['id_user'] != '') {
                        $this->postBody['code'] = $this->generate_referral_code();
                        $dataUser = $this->userModel->register($this->postBody);
                        $query = "UPDATE tb_user SET referred_by = ? WHERE id_user = ?";
                        $this->db->query($query, array($checkExistRef['id_user'], $dataUser['id_user']));
                        $query = "UPDATE tb_user SET coins = coins + 150 WHERE id_user = ?";
                        $this->db->query($query, array($checkExistRef['id_user']));
                        $json = array(
                            "result" => array($dataUser),
                            "code" => "201",
                            "message" => "User created successfully"
                        );
                        echo json_encode($json);
                    } else {
                        $json = array(
                            "result" => array(),
                            "code" => "404",
                            "message" => "Referrer user ID not found"
                        );
                        echo json_encode($json);
                    }
                } else {
                    $this->postBody['code'] = $this->generate_referral_code();
                    $dataUser = $this->userModel->register($this->postBody);
                    $json = array(
                        "result" => array($dataUser),
                        "code" => "201",
                        "message" => "User created successfully"
                    );
                    echo json_encode($json);
                }
            } else {
                $json = array(
                    "result" => array(),
                    "code" => "302",
                    "message" => "Email/Username already exists"
                );
                header('Content-Type: application/json');
                echo json_encode($json);
            }
        }
    }

    //handle register from third party (google-sign-in) (apple-id)
    public function register_3party()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $arr = array();

        if ($this->postBody['ps'] != '' && $this->postBody['em'] != '') {

            $checkExist = $this->userModel->getByEmail($this->postBody['em']);

            if ($checkExist['id_user'] == '') {
                $this->postBody['ps'] = $this->generatePassword($this->postBody['ps']);
            } else {
                $this->postBody['id'] = $checkExist['id_user'];
                $this->postBody['ps'] = $checkExist['password'];
                $this->postBody['us'] = $checkExist['user_name'];
            }

            $dataUser = $this->userModel->signIn3Party($this->postBody);
            $arr = [$dataUser];
        }

        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Required parameter",
            );
        } else {
            $json = array(
                "result" => $arr,
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }
    //handle register from third party (google-sign-in) (apple-id)

    public function register_phone()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $arr = array();

        if ($this->postBody['ps'] != '' && $this->postBody['ph'] != '') {
            $checkExist = $this->userModel->getByPhone($this->postBody['ph']);

            if ($checkExist['id_user'] == '') {
                $this->postBody['ps'] = $this->generatePassword($this->postBody['ps']);
                $dataUser = $this->userModel->registerByPhone($this->postBody);

                $arr = [$dataUser];
            }
        }

        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Phone number already exist",
            );
        } else {
            $json = array(
                "result" => $arr,
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function pay_package()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $dataUser = $this->userModel->getById($this->postBody['id']);


        if ($this->postBody['is'] != '' && $dataUser['id_user'] != '' && $this->postBody['tp'] != '') {
            $check = $this->userModel->payPackage($this->postBody);
            if ($check != null) {
                $dataUser = $this->userModel->getById($this->postBody['id']);
            } else {
                $json = array(
                    "result" => array(),
                    "code" => "201",
                    "message" => "Process failed",
                );
                header('Content-Type: application/json');
                echo json_encode($json);
                die();
            }
        }

        $arr = $dataUser;
        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Process failed",
            );
        } else {
            $json = array(
                "result" => [$arr],
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function update_package()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $dataUser = $this->userModel->getById($this->postBody['id']);

        if ($this->postBody['is'] != '' && $dataUser['id_user'] != '' && $this->postBody['tp'] != '') {
            $check = $this->userModel->updatePackage($this->postBody);
            if ($check != null) {
                $dataUser = $this->userModel->getById($this->postBody['id']);
            } else {
                $json = array(
                    "result" => array(),
                    "code" => "201",
                    "message" => "Process failed",
                );
                header('Content-Type: application/json');
                echo json_encode($json);
                die();
            }
        }

        $arr = $dataUser;
        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Process failed",
            );
        } else {
            $json = array(
                "result" => [$arr],
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function update_counter()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $dataUser = $this->userModel->getById($this->postBody['id']);

        if ($this->postBody['is'] != '' && $dataUser['id_user'] != '' && $this->postBody['cm'] != '') {
            $this->userModel->updateCounter($this->postBody);
            $dataUser = $this->userModel->getById($this->postBody['id']);
        }

        $arr = $dataUser;
        if (count($arr) < 1) {
            $json = array(
                "result" => $arr,
                "code" => "201",
                "message" => "Email/Username & Password invalid",
            );
        } else {
            $json = array(
                "result" => [$arr],
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function get_user()
    {
        $this->postBody = $this->authModel->authHeader($this->request);

        if ($this->postBody['is'] != '' && $this->postBody['iu'] != '') {
            $this->postBody['id'] = $this->postBody['iu'];
            $this->userModel->updateUser($this->postBody);
        }

        $dataUser = $this->userModel->getById($this->postBody['iu']);

        if ($dataUser['id_user'] == '') {
            $json = array(
                "result" => array(),
                "code" => "201",
                "message" => "Data not found",
            );
        } else {
            $json = array(
                "result" => [$dataUser],
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function update_user()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        $action = $this->postBody['act'];
        $idUser = $this->postBody['iu'];
        $image = $this->postBody['img'];

        if ($this->postBody['is'] != '' && $idUser != '' && $action != '') {
            $this->postBody['id'] = $idUser;

            $datenow = date('YmdHis');

            if ($action == 'update_fullname') {
                $sqlUpdate = "UPDATE tb_user SET display_name='" . $this->postBody['fn'] . "', updated_at='" . $datenow . "'
                    WHERE id_user='" . $idUser . "' ";

                if ($image != '') {
                    $sqlUpdate = "UPDATE tb_user SET display_name='" . $this->postBody['fn'] . "', profile_pic='" . $image . "',
                     updated_at='" . $datenow . "'  WHERE id_user='" . $idUser . "' ";
                }

                $this->userModel->query($sqlUpdate);
            }
        }

        $dataUser = $this->userModel->getById($this->postBody['iu']);

        if ($dataUser['id_user'] == '') {
            $json = array(
                "result" => array(),
                "code" => "201",
                "message" => "Data not found",
            );
        } else {
            $json = array(
                "result" => [$dataUser],
                "code" => "200",
                "message" => "Success",
            );
        }

        //add the header here
        header('Content-Type: application/json');
        echo json_encode($json);
        die();
    }

    public function hash_password()
    {
        $this->postBody = $this->authModel->authHeader($this->request);
        print_r($this->generatePassword($this->postBody['ps']));
    }

    private function generatePassword($password)
    {
        return md5(sha1(hash("sha256", $password)));
    }
}