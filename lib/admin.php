<?php
    namespace Octo;

    class Admin extends Authentication
    {
        protected $ns = 'admin', $actual = 'admin.user', $entity = 'adminUser';
    }
