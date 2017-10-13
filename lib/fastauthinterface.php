<?php
    namespace Octo;

    interface Fastauthinterface
    {
        public function login($username, $password);
        public function logout();
        public function getUser($field = null);
        public function getSession();
        public function getLoginPath();
        public function getLogoutPath();
        public function setLoginPath($path);
        public function setLogoutPath($path);
    }
