<?php

/**
 * Plugin which provides a login functionality based on nonces.
 * It can be used to login via a single link from an external server
 * without sharing the actual credentials via the user.
 *
 * @version @package_version@
 * @author Aniket Das <hello@aniket-das.in>
 */
class nonce_login extends rcube_plugin
{
    // registered tasks for this plugin.
    // public $task = 'login';

    // expire time of nonce (in milliseconds).
    private $nonce_expire_time;

    // name of the database table for the nonce based authentication.
    private $db_table_login_nonces;

    function init()
    {
        $rcmail = rcmail::get_instance();

        // check whether the "global_config" plugin is available,
        // otherwise load the config manually.
        $plugins = $rcmail->config->get('plugins');
        $plugins = array_flip($plugins);
        if (!isset($plugins['global_config'])) {
            $this->load_config();
        }

        // load plugin configuration.
        $this->nonce_expire_time = $rcmail->config->get('login_nonce_expire', 15);
        $this->db_table_login_nonces = $rcmail->config->get('db_table_login_nonces', 'login_nonces');

        // register hooks.
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('authenticate', array($this, 'authenticate'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();

        if (isset($_GET['nonce_login'])) {
            if (!isset($_SERVER['PHP_AUTH_USER']) && $_GET['nonce_login']) {
                $rcmail->kill_session();
                $args['action'] = 'login';
                return $args;
            }

            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $nonce = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');

                // calculate expire date for database.
                $expiryTime = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');

                $user = $_SERVER['PHP_AUTH_USER'];
                $pass = $rcmail->encrypt($_SERVER['PHP_AUTH_PW']);
                $host = $_GET['host'] ?: '';

                // insert nonce to database.
                $rcmail->get_dbh()->query(
                    "INSERT INTO " . $rcmail->db->table_name($this->db_table_login_nonces)
                        . " (nonce, expires, user, pass, host)"
                        . " VALUES (?, ?, ?, ?, ?)",
                    $nonce,
                    $expiryTime,
                    $user,
                    $pass,
                    $host
                );

                http_response_code(201);
                header('Location: https://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . '?nonce_login=' . $nonce);
                exit;
            }

            $args['action'] = 'login';
        }

        return $args;
    }

    function authenticate($args)
    {
        if (isset($_GET['nonce_login'])) {
            $rcmail = rcmail::get_instance();

            $nonce = $_GET['nonce_login'];

            $currentTime = (new DateTime())->format('Y-m-d H:i:s');

            // remove all expired nonces from database.
            $rcmail->get_dbh()->query(
                "DELETE FROM " . $rcmail->db->table_name($this->db_table_login_nonces)
                    . " WHERE expires < " . $currentTime
            );
            
            // get nonce data from db.
            $res = $rcmail->get_dbh()->query(
                "SELECT * FROM " . $rcmail->db->table_name($this->db_table_login_nonces)
                    . " WHERE nonce = ?",
                $nonce
            );
            
            if (($data = $rcmail->get_dbh()->fetch_assoc($res))) {
                // set login data.
                $args['user'] = $data['user'];
                $args['pass'] = $rcmail->decrypt($data['pass']);
                $args['host'] = $data['host'];
                $args['cookiecheck'] = false;
                $args['valid'] = true;
                $args['abort'] = false;

                // remove nonce from db.
                $rcmail->get_dbh()->query(
                    "DELETE FROM " . $rcmail->db->table_name($this->db_table_login_nonces)
                        . " WHERE nonce = ?",
                    $nonce
                );
            }
        }

        return $args;
    }
}