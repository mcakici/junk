<?php
class Users {
    use sendmail;
    private $db;
    private $session;

    private $userinfo;

    #login_register_cookie
    private $user_logged_in = false;
    private $user_auto_login_enabled = true;
    private $user_auth_cookie = "uHash";
    private $user_auth_cookie_time = 3600 * 24 * 3; // +3 days

    public function __construct() {
        if (!isset($GLOBALS["db"])) {
            exit("Db Global variable not found! In " . __METHOD__);
        }
        if (!isset($GLOBALS["session"])) {
            exit("Session Global variable not found! In " . __METHOD__);
        }
        $this->db = &$GLOBALS["db"];
        $this->session = &$GLOBALS["session"];

        if ($this->session->get("uid")) {
            $this->user_logged_in = true;
            $this->load_userinfo(null);
        }

        // try login automatically for visitor
        if ($this->user_auto_login_enabled && !$this->user_logged_in) {
            $this->check_login_cookie();
        }
    }

    public function userid() {
        //$this->send_mail("streamerslab@gmail.com","Bay streamers","Selam naber","iyimisin brother.");
        return $this->db->query("SELECT * FROM users WHERE username = ?", 1)->fetchAll();
    }

    public function check_username_exist($username) {
        $reg_username_check = $this->username_str_plain($username);

        if (!$reg_username_check || empty($reg_username_check)) {
            return false;
        }

        $query = $this->db->query("SELECT users.username FROM users WHERE username = ? LIMIT 1", $reg_username_check);
        if ($query->numRows() > 0) {
            //todo: fix this
            if ($this->user_logged_in && $_SESSION['uname'] == $reg_username_check) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function get_userinfo($var){
        //var_dump($this->userinfo);
        return isset($this->userinfo[$var]) ? $this->userinfo[$var] : false;
    }

    public function load_userinfo($uid = null) {
        if ($uid !== null) {
            $query = $this->db->query("SELECT * FROM users WHERE uid=?", $uid);
        } else {
            $query = $this->db->query("SELECT * FROM users WHERE uid=?", $this->session->get("uid"));
        }
        $results = $query->singleArray();
        $this->userinfo = $results;
    }

    public function generateSalt($len = 15) {
        $salt = '';
        for ($i = 0; $i < $len; $i++) {
            $salt .= chr(rand(33, 126));
        }
        return $salt;
    }

    public function hashPassword($pwd, $salt = '') {
        return password_hash($salt . $pwd, PASSWORD_BCRYPT, ["cost" => 10]); //cost logarithmic 2^10
    }

    public function setCookie($cookieName, $cookieValue, $expire, $sslOnly = false, $httpOnly = false) {
        @setcookie($cookieName, $cookieValue, $expire, "/", "", $sslOnly, $httpOnly);
    }

    public function removeCookie($cookieName) {
        @setcookie($cookieName, null, -1, '/');
    }

    public function register($reg_mail, $reg_fullname, $reg_pass) {
        if ((!isset($reg_pass) && $reg_pass !== null) || !isset($reg_fullname) || (!isset($reg_mail) && $reg_mail !== null)) {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/', $reg_mail)) {
            return false;
        }

        $fullnameLen = mb_strlen($reg_fullname, 'UTF-8');
        if ($fullnameLen > 60 || $fullnameLen < 1) {
            return false;
        }

        $passLen = mb_strlen($reg_pass, 'UTF-8');
        if ($passLen > 100 || $passLen < 1) {
            return false;
        }

        $emailLen = mb_strlen($reg_mail, 'UTF-8');
        if ($emailLen > 80 || $emailLen < 5) {
            return false;
        }

        $checkUserExistance = $this->db->query("SELECT uid FROM users WHERE email=? LIMIT 1", $reg_mail);
        if ($checkUserExistance->numRows()) {
            return false;
        }

        $username = $this->username_str_plain($reg_fullname);
        $userPwdSalt = $this->generateSalt(15);
        $userPwdHash = $this->hashPassword($reg_pass, $userPwdSalt);
        $currentTime = time();

        $insertUser = $this->db->query("INSERT INTO users (username,email,pwdHash,pwdSalt,pwdCreatedTime,registerIp,registerTime) VALUES (?,?,?,?,?,?,?)", $username, $reg_mail, $userPwdHash, $userPwdSalt, $currentTime, IP, $currentTime);

        if ($insertUser) {
            $userInsertID = $insertUser->lastInsertID();
            $this->_login_verified($userInsertID, $reg_mail, false, $userPwdHash);
        } else {
            //register error
            return false;
        }

    }

    private function _login_verified($uid, $email, $rememberMe, $passCode = '') {
        if ($this->user_logged_in || empty($email) || empty($uid)) {
            return false;
        }

        //delete old user sessions if exist
        $this->session->destroyUserSession($uid);
        //set session uid
        $this->session->set("uid", $uid);

        //echo 'login verified.<br>';

        $this->user_logged_in = true;
        $this->load_userinfo($uid);

        if ($rememberMe == 1 || isset($_COOKIE[$this->user_auth_cookie])) {
            //delete old auto login cookie
            $this->removeCookie($this->user_auth_cookie);
            //set new one
            $newCookieHash = hash('sha256', time() . '-------' . $email);
            $loginHashCreatedTime = time();
            $this->setCookie($this->user_auth_cookie, $email . '_' . $newCookieHash . '_' . $uid, time() + $this->user_auth_cookie_time, false, true); // 7 days
        }
        $this->db->query("UPDATE users SET lastLoginIp=?, lastLoginTime=?, loginHash=?, loginHashCreatedTime=? WHERE uid=?", IP, time(), (isset($newCookieHash) ? $newCookieHash : null), (isset($loginHashCreatedTime) ? $loginHashCreatedTime : null), $uid);

        return true;
    }

    public function login($email, $userPassword, $rememberMe = false) {
        if ($this->user_logged_in || empty($email)) {
            return false;
        }

        $login_query = $this->db->query("SELECT uid,username,pwdHash,pwdSalt,email,status FROM users WHERE (email=? OR username=?) LIMIT 1", $email, $email);
        if ($login_query->numRows() > 0) {
            $row_data = $login_query->singleArray();

            if (password_verify($row_data["pwdSalt"] . $userPassword, $row_data["pwdHash"])) {
                //login success
                $passCode = substr($row_data["pwdHash"], 30, 10);
                $this->_login_verified($row_data["uid"], $row_data["email"], $rememberMe, $passCode);
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    public function check_login_cookie() {
        if (!$this->user_auto_login_enabled) {
            return;
        }

        if (isset($_COOKIE[$this->user_auth_cookie]) && !$this->session->get("uid")) {
            preg_match('/(\S{1,150})_(\S{1,80})_([0-9]{1,25})$/', $_COOKIE[$this->user_auth_cookie], $_parsed_cookie_str);

            $query = $this->db->query("SELECT uid,email,username,loginHash,loginHashCreatedTime FROM users WHERE status=1 AND uid=? AND email=? LIMIT 1"
                , $_parsed_cookie_str[3], $_parsed_cookie_str[1]);

            if ($query->numRows() > 0) {
                $row = $query->singleArray();
                if (!empty($row["loginHash"]) && !empty($_parsed_cookie_str[2])
                    && hash_equals($row["loginHash"], $_parsed_cookie_str[2])
                    && (intval($row["loginHashCreatedTime"]) + $this->user_auth_cookie_time) > time()) {
                    $this->_login_verified($row["uid"], $row["email"], true);
                } else {
                    if (isset($this->user_auth_cookie)) {
                        @setcookie($this->user_auth_cookie, null, -1, '/');
                        $this->db->query("UPDATE users SET loginHash=NULL, loginHashCreatedTime=NULL WHERE uid=?", $row["uid"]);
                    }
                }
            } else {
                //delete old autologin cookie if exists
                if (isset($this->user_auth_cookie)) {
                    @setcookie($this->user_auth_cookie, null, -1, '/');
                }
            }
        }
    }

    public function logout() {
        if (isset($_SESSION['uid'])) {
            /*
            if (isset($_SESSION["uid"])) {
            unset($_SESSION['uid']);
            }

            if (isset($_SESSION["perm"])) {
            unset($_SESSION['perm']);
            }

            if (isset($_SESSION["gplus_me"])) {
            unset($_SESSION['gplus_me']);
            }

            if (isset($_SESSION["linkedin_user"])) {
            unset($_SESSION['linkedin_user']);
            }

            if (isset($_SESSION["FBRLH_state"])) {
            unset($_SESSION['FBRLH_state']);
            }

            if (isset($_SESSION["contact_securitycode"])) {
            unset($_SESSION['contact_securitycode']);
            }

            if (isset($_SESSION["access_token"])) {
            unset($_SESSION['access_token']);
            } */

            @session_destroy();

            $this->user_logged_in = false;

            //delete auto-login cookie if exist
            if (isset($_COOKIE[$this->user_auth_cookie])) {
                @setcookie($this->user_auth_cookie, null, -1, '/');
            }
        }
    }

    public function email_verify($uid, $code) {
        @header("X-Robots-Tag: noindex, nofollow", true);

        if ($this->userData && $this->userData["emailverified"]) {
            // return 'showalert("error","Onaylamaya çalıştığınız eposta adresi zaten onaylanmış durumdadır.");';
            return false;
        } else if ($this->userData) {
            // return 'showalert("success","Eposta adresiniz başarıyla onaylanmıştır.");';
            $this->db->query("UPDATE users SET email_verified=1 WHERE status=1 AND email_verified=0 AND uid=?", $uid);
            //$userinfo = get_user_info($_SESSION["uid"],1);
        }
    }

    public function username_str_plain($var) {
        // Gets rid of the non SEO friendly characters in the variable
        $var = mb_strtolower(trim($var), "UTF-8");
        $var = str_replace('ı', 'i', $var);
        $var = str_replace('I', 'i', $var);
        $var = str_replace('İ', 'i', $var);
        $var = str_replace('ç', 'c', $var);
        $var = str_replace('Ç', 'c', $var);
        $var = str_replace('ğ', 'g', $var);
        $var = str_replace('Ğ', 'g', $var);
        $var = str_replace('ö', 'o', $var);
        $var = str_replace('Ö', 'o', $var);
        $var = str_replace('ş', 's', $var);
        $var = str_replace('Ş', 's', $var);
        $var = str_replace('Ü', 'u', $var);
        $var = str_replace('ü', 'u', $var);
        $var = str_replace('â', 'a', $var);
        $var = str_replace('é', 'e', $var);
        $var = str_replace('î', 'i', $var);
        $var = str_replace('ê', 'e', $var);
        $var = str_replace('ä', 'a', $var);
        $var = str_replace('ó', 'o', $var);
        $var = str_replace('û', 'u', $var);

        $var = str_replace("þ", "s", $var);
        $var = str_replace("Þ", "S", $var);
        $var = str_replace("ç", "c", $var);
        $var = str_replace("Ç", "C", $var);
        $var = str_replace("ý", "i", $var);
        $var = str_replace("Ý", "I", $var);
        $var = str_replace("ð", "g", $var);
        $var = str_replace("Ð", "G", $var);

        $var = str_replace('&#305;', 'i', $var);
        $var = str_replace('&#350;', 's', $var);
        $var = str_replace('&#287;', 'g', $var);
        $var = str_replace('&#199;', 'c', $var);
        $var = str_replace('&#351;', 's', $var);
        $var = str_replace('&Ccedil;', 'c', $var);
        $var = str_replace('&ccedil;', 'c', $var);
        $var = str_replace('\.', '', $var);
        $var = str_replace(' \?', '', $var);
        $var = str_replace('\?', '', $var);
        $var = str_replace('\!', '', $var);
        $var = str_replace('#', '', $var);
        $var = str_replace('\%', '', $var);
        $var = str_replace('\&', '', $var);
        $var = str_replace('#', '', $var);
        $var = str_replace(',', '', $var);
        $var = str_replace('/', '', $var);
        $var = str_replace(';', '', $var);
        $var = str_replace("\,", "", $var);
        $var = str_replace("\/", "", $var);
        $var = str_replace("\[", "", $var);
        $var = str_replace("\]", "", $var);
        $var = str_replace("\'", "", $var);
        $var = str_replace("\.", "", $var);
        $var = str_replace("\-", "", $var);
        $var = str_replace("  ", "", $var);
        $var = preg_replace('/[?]/', '', $var);
        $var = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($var, ENT_QUOTES, 'UTF-8'));
        $var = preg_replace("~ +~i", "", $var);
        $var = preg_replace("/[^a-z0-9]/i", "", $var);
        return $var;
    }
}