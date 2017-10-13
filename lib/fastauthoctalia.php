<?php
    namespace Octo;

    class Fastauthoctalia implements Fastauthinterface
    {
        public function login($username, $password)
        {
            $user = em('fastUser')->findBy('username', $username)->first();

            if ($user && password_verify($password, $user['password'])) {
                $userSession = arrayable($user) ? $user->toArray() : $user;

                $this->getSession()->set("fast.user", $userSession);

                return true;
            }

            return false;
        }

        public function logout()
        {
            $this->getSession()->erase("fast.user");
        }

        public function getUser($field = null)
        {
            $user = $this->getSession()->get("fast.user");

            if ($field && $user) {
                return isAke($user, $field, null);
            }

            return $user
                ? (new Fastuser($user))
                ->fn('logout', function () { return actual('fast')->getAuth()->logout();})
                : null
            ;
        }

        public function getSession()
        {
            return actual('fast')->getSession();
        }

        public function getLoginPath()
        {
            return actual("fast.auth.login_path");
        }

        public function getLogoutPath()
        {
            return actual("fast.auth.logout_path");
        }

        public function setLoginPath($path)
        {
            actual("fast.auth.login_path", $path);

            return $this;
        }

        public function setLogoutPath($path)
        {
            actual("fast.auth.logout_path", $path);

            return $this;
        }
    }
