<?php /* MEMORY storage engine table max size = "max_heap_table_size" default 16mb approx 8000 records */
/* Last modified at 17-08-2020 - MÇ - Secure php session handler */
class Session {
    # SETTINGS
    private $sessionCookieName = "userAuth";
    private $startAutomatically = false; // true = start session for each visitor, false = only triggers when you use set function (after a success login)
    private $cookieNameForLoggedIn = "userVerified"; // if $startAutomatically variable is FALSE then use this cookie as a flag

    # VARS
    private $db;
    private $ip;
    private $ua;
    private $keySalt = "";
    private $userid = null;

    function __construct() {
        ini_set('session.cookie_httponly', 1); //force to use SESSION cookie httponly (disable javascript access to cookie)
        ini_set('session.use_strict_mode', 1); //will reject users custom session ids if they change it
        session_name($this->sessionCookieName);
        session_set_save_handler(
            array($this, "_open"),
            array($this, "_close"),
            array($this, "_read"),
            array($this, "_write"),
            array($this, "_destroy"),
            array($this, "_gc"),
            array($this, "_createSID"), //PHP 5.5.1+
            array($this, "_isSessionTokenValid") //PHP 7+
        );
        if ((isset($_COOKIE[$this->cookieNameForLoggedIn]) && isset($_COOKIE[session_name()])) || $this->startAutomatically) {
            $this->startSession();
        }
    }

    public function _open($sess_path, $sess_name) {
        global $db;
        //$this->lifeTime = get_cfg_var("session.gc_maxlifetime");
        // DB connection settings (use different db user with delete privilege for more precaution)
        $this->db = new mysqli("localhost", "root", "", "test");
        /* check connection */
        if ($this->db->connect_errno) {
            printf("Connect failed: %s\n", $this->db->connect_error);
            exit();
        }

        $this->db->set_charset("utf8");
        $this->ip = $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
        $this->ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        return true;
    }

    public function _close() {
        $this->_gc(ini_get('session.gc_maxlifetime'));
        return $this->db->close();
    }

    public function _read($client_hash) {
        $sess_id = $this->_getSessionId($client_hash);

        $query = $this->db->prepare("SELECT sessdata FROM sessions WHERE sessid=? LIMIT 1");
        $query->bind_param("s", $sess_id);
        $query->execute();
        $result = $query->get_result();
        $query->close();

        return ($result->num_rows ? $result->fetch_assoc()["sessdata"] : '');
    }

    public function _write($client_hash, $sess_data) {
        $time = time();
        $sess_id = $this->_getSessionId($client_hash);
        $isbot = $this->isBot();

        $query = $this->db->prepare("REPLACE INTO sessions SET sessid = ?, lastactivity = ?, sessdata = ?, useragent = ?, host = ?, isbot = ?");
        $query->bind_param("sdsssd", $sess_id, $time, $sess_data, $this->ua, $this->ip, $isbot);
        $qStatus = $query->execute();
        $query->close();

        return ($qStatus ? true : false);
    }

    public function _destroy($sess_id, $oldsessid = null) {
        if ($oldsessid !== null) {
            $sess_id = $oldsessid;
        } else {
            $sess_id = $this->_getSessionId($sess_id);
        }

        $query = $this->db->prepare("DELETE FROM sessions WHERE sessid = ?");
        $query->bind_param("s", $sess_id);
        $qStatus = $query->execute();
        $query->close();

        if(!$this->startAutomatically){
            @setcookie($this->sessionCookieName, null, -1, '/');
            @setcookie($this->cookieNameForLoggedIn, null, -1, '/');
        }

        return ($qStatus ? true : false);
    }

    public function _gc($sess_maxlifetime) {
        //garbage collection
        $old = time() - $sess_maxlifetime;
        $query = $this->db->query("DELETE FROM sessions WHERE lastactivity < " . intval($old));
        return ($query ? true : false);
    }

    public function _createSID() {
        $newSessID = md5(uniqid(microtime(), true));
        if (!$this->startAutomatically && !empty($this->keySalt)) {
            return hash_hmac("md5", $newSessID, $newSessID . $this->ip . $this->ua) . ($this->userid ? '_' . $this->userid : '');
        }
        return $newSessID;
    }

    // before using _set function, using this will change hash token value for each user
    public function addSalt($additionalString = '',$userid = null) {
        $this->userid = $userid;
        $this->keySalt = $additionalString;

        /* delete old session record if startAutomatically is active and additional key salt is not empty */
        if ($this->startAutomatically && !empty($this->keySalt)) {
            //should be the same with _getSessionId hash_hmac without keySalt variable
            $oldsessid = hash_hmac("md5", session_id(), session_id() . $this->ip . $this->ua . '');
            $this->_destroy(null, $oldsessid);
        }
    }

    private function _getSessionId($sess_id) {
        /* for token key, hashing ip and useragent not enough for full security (vulnerable from LAN, spoofing)
        Someone can steal and login with user authorization token but if you use _addSalt function to add additional string to token, this will not be vulnerable anymore */
        if (!$this->startAutomatically) {
            @preg_match('/([-,a-zA-Z0-9]{32})_([0-9]{1,10})$/', $sess_id, $_parsed_session_str);
            return hash_hmac("md5", $_parsed_session_str[1], $_parsed_session_str[1] . $this->ip . $this->ua . ($_parsed_session_str[2] ? '_' . $_parsed_session_str[2] : ''));
        } else {
            return hash_hmac("md5", $sess_id, $sess_id . $this->ip . $this->ua);
        }

    }

    public function _checkUser($uid){
        $query = $this->db->prepare("SELECT pwdHash FROM users WHERE uid=? LIMIT 1");
        $query->bind_param("d", $uid);
        $query->execute();
        $result = $query->get_result();
        $query->close();

        return ($result->num_rows ? md5(substr($result->fetch_assoc()["pwdHash"], 30, 10)) : '');
    }

    private function _isSessionTokenValid($sess_id) {
        return preg_match('/^([-,a-zA-Z0-9]{32}|[-,a-zA-Z0-9]{32}_[0-9]{1,10})$/', $sess_id) > 0;
    }

    private function isBot() {
        return (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT']) ? 1 : 0);
    }

    private function startSession() {
        //check if session already started
        if (session_status() !== 2) {
            session_start();
            if (!$this->startAutomatically) {
                @setcookie($this->cookieNameForLoggedIn, 1, 0, '/', "", false, true);
            }
        }
    }

    public function get($var) {
        return isset($_SESSION[$var]) ? $_SESSION[$var] : false;
    }

    public function set($var, $value) {
        $this->startSession();
        $_SESSION[$var] = $value;
        return $_SESSION[$var];
    }
}